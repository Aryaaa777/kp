<?php

namespace App\Services;

use App\Exceptions\EmptySubmissionException;
use App\Exceptions\SubmissionTransitionException;
use App\Exceptions\SubmissionVersionException;
use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\SubmissionStatusNotification;
use App\Services\Submissions\SubmissionModuleHandler;
use App\Services\Submissions\SubmissionModules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Layanan workflow pengajuan & verifikasi (FR-140…143, PRD §12).
 *
 * State machine dijaga di sini (`Submission::TRANSITIONS`); transisi tidak sah → exception.
 * Setiap operasi tulis (`submit`, `updateDraft`, `startReview`, `approve`, `requestRevision`,
 * `reject`, `archive`) dijalankan di dalam satu DB transaction: record pengajuan **diambil
 * ulang dengan `lockForUpdate()`** dan status diperiksa berdasarkan data terbaru di dalam
 * transaksi, lalu pembaruan status memakai penjaga optimistik (conditional update pada
 * status lama). Ini mencegah dua keputusan bersamaan sama-sama berhasil memproses status
 * yang sama (M1).
 *
 * **Pembuatan pengajuan juga diserialkan (T-1).** `createDraft()` & `createRevision()` mengunci
 * baris **koperasi** (`lockForUpdate()` — anchor stabil yang selalu ada, berbeda dari record
 * submission yang pada pembuatan pertama belum ada) **sebelum** memeriksa konflik/pengajuan aktif
 * dan menyimpan record, semuanya di dalam satu transaksi. Urutan lock selalu `cooperatives` dulu
 * (konsisten di semua jalur pembuatan), sehingga dua permintaan bersamaan untuk koperasi yang sama
 * berjalan berurutan dan aturan "satu pengajuan aktif per koperasi+modul" tidak dapat dilanggar
 * oleh balapan (race). Ini pengamanan **tingkat aplikasi**: SQLite mengabaikan `FOR UPDATE`, dan
 * mekanisme ini **bukan** pengganti constraint basis data. Lihat `docs/DECISIONS.md` §18.
 *
 * Penerapan perubahan ke data resmi (FR-143) dilakukan dalam transaksi yang sama dengan
 * pencatatan riwayat (FR-150) & audit (FR-151); bila penerapan gagal, seluruh transaksi
 * di-rollback (status pengajuan **tidak** berubah menjadi `approved`).
 *
 * PROVISIONAL — Q-004 (verifikasi berjenjang) & Q-006 (whitelist field) belum dikonfirmasi.
 *
 * @see docs/PRD.md §12
 * @see docs/DECISIONS.md §12 (keputusan F5)
 */
class SubmissionService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Buat draft pengajuan. Field yang sama dengan data resmi tidak disimpan (hanya diff).
     *
     * @param  array<string, mixed>  $inputs
     */
    public function createDraft(User $actor, Cooperative $cooperative, string $module, array $inputs, ?string $title = null): Submission
    {
        $handler = SubmissionModules::handler($module);

        return DB::transaction(function () use ($actor, $cooperative, $module, $inputs, $title, $handler): Submission {
            // Serialisasi per koperasi (T-1): kunci anchor stabil lebih dulu, lalu periksa
            // konflik & buat record di dalam transaksi yang sama.
            $this->lockCooperativeAnchor($cooperative);
            $this->assertNoConflictingActive($cooperative, $module);

            $probe = $this->transientSubmission($cooperative, $module);
            $items = $this->buildItems($handler, $probe, $inputs);

            if ($items === []) {
                throw EmptySubmissionException::noChanges();
            }

            $submission = new Submission;
            $submission->cooperative_id = $cooperative->id;
            $submission->submitted_by = $actor->id;
            $submission->module = $module;
            $submission->title = $title ?: $this->defaultTitle($module, $cooperative);
            $submission->status = 'draft';
            $submission->save();

            $this->syncItems($submission, $items);
            $this->recordReview($submission, $actor, null, 'draft', null);
            $this->logger->log('create_submission', $submission, $actor, [
                'module' => $module,
                'changes' => $items,
            ]);

            return $submission;
        });
    }

    /**
     * Buat **versi perbaikan** dari pengajuan `needs_revision` (FR-031/FR-142, D-1).
     *
     * Record lama **tidak** diubah: ia tetap menyimpan `submission_items` & `submission_reviews`
     * sebagai riwayat, sementara record baru (`status = draft`) menunjuk versi induk lewat
     * `parent_submission_id`. Perbaikan hanya boleh dibuat satu kali per versi induk (induk yang
     * sudah punya versi turunan → `alreadySuperseded`), dan tidak boleh ada pengajuan aktif lain
     * untuk koperasi/modul yang sama.
     *
     * @param  array<string, mixed>  $inputs  Nilai usulan (biasanya sudah terisi dari versi induk).
     */
    public function createRevision(User $actor, Submission $parent, array $inputs, ?string $title = null): Submission
    {
        return DB::transaction(function () use ($actor, $parent, $inputs, $title): Submission {
            // Serialisasi per koperasi (T-1): kunci anchor stabil sebelum memeriksa status induk.
            $this->lockCooperativeAnchor((int) $parent->cooperative_id);

            // Status & ketersediaan induk dibaca ulang di dalam transaksi agar tidak memakai
            // model binding yang basi (mis. induk diarsipkan di permintaan lain).
            $lockedParent = Submission::query()->find($parent->getKey());

            if (! $lockedParent instanceof Submission) {
                throw SubmissionVersionException::parentUnavailable($parent);
            }

            $cooperative = $lockedParent->cooperative;

            if (! $cooperative instanceof Cooperative) {
                throw SubmissionTransitionException::targetUnavailable($lockedParent);
            }

            if ($lockedParent->status !== 'needs_revision') {
                throw SubmissionVersionException::parentNotRevisable($lockedParent);
            }

            if ($lockedParent->isSuperseded()) {
                throw SubmissionVersionException::alreadySuperseded($lockedParent);
            }

            $module = $lockedParent->module;

            $this->assertNoConflictingActive($cooperative, $module, ignoreIds: [(int) $lockedParent->getKey()]);

            $handler = SubmissionModules::handler($module);
            $probe = $this->transientSubmission($cooperative, $module);
            $items = $this->buildItems($handler, $probe, $inputs);

            if ($items === []) {
                throw EmptySubmissionException::noChanges();
            }

            $submission = new Submission;
            $submission->cooperative_id = $lockedParent->cooperative_id;
            $submission->parent_submission_id = $lockedParent->getKey();
            $submission->submitted_by = $actor->id;
            $submission->module = $module;
            $submission->title = $title ?: ($lockedParent->title ?: $this->defaultTitle($module, $cooperative));
            $submission->status = 'draft';
            $submission->save();

            $this->syncItems($submission, $items);
            $this->recordReview($submission, $actor, null, 'draft', null);
            $this->logger->log('create_submission_revision', $submission, $actor, [
                'module' => $module,
                'parent_submission_id' => $lockedParent->getKey(),
                'changes' => $items,
            ], 'Versi perbaikan pengajuan dibuat.');

            return $submission;
        });
    }

    /**
     * Perbarui draft pengajuan (hanya saat status editable). Status diperiksa ulang pada
     * data terbaru di dalam transaksi (anti race dengan `submit`).
     *
     * @param  array<string, mixed>  $inputs
     */
    public function updateDraft(Submission $submission, User $actor, array $inputs, ?string $title = null): Submission
    {
        return DB::transaction(function () use ($submission, $actor, $inputs, $title): Submission {
            $locked = $this->lockSubmission($submission);
            $this->assertEditable($locked);

            $handler = SubmissionModules::handler($locked->module);
            $locked->loadMissing('cooperative');
            $items = $this->buildItems($handler, $locked, $inputs);

            if ($items === []) {
                throw EmptySubmissionException::noChanges();
            }

            $this->syncItems($locked, $items);

            if ($title !== null) {
                $locked->title = $title;
            }

            $locked->save();

            $this->logger->log('update_submission', $locked, $actor, ['changes' => $items]);

            return $locked;
        });
    }

    /**
     * Kirim pengajuan untuk diverifikasi (FR-140).
     */
    public function submit(Submission $submission, User $actor): Submission
    {
        return DB::transaction(function () use ($submission, $actor): Submission {
            $locked = $this->lockSubmission($submission);
            $this->assertEditable($locked);

            if ($locked->items()->count() === 0) {
                throw EmptySubmissionException::noChanges();
            }

            return $this->performTransition($locked, $actor, 'submitted', null, 'submit_submission');
        });
    }

    /**
     * Admin mengambil pengajuan untuk ditinjau (PRD §12.2).
     */
    public function startReview(Submission $submission, User $admin): Submission
    {
        return $this->transition($submission, $admin, 'under_review', null, 'start_review_submission');
    }

    /**
     * Setujui & terapkan perubahan ke data resmi (FR-143) dalam satu transaksi.
     *
     * Bila `handler->apply()` gagal, transaksi di-rollback dan status tetap `under_review`.
     * Nilai lama untuk audit diambil dari data resmi **tepat sebelum** penerapan.
     */
    public function approve(Submission $submission, User $admin): Submission
    {
        $approved = DB::transaction(function () use ($submission, $admin): Submission {
            $locked = $this->lockSubmission($submission);
            $this->assertTransition($locked, 'approved');

            $handler = SubmissionModules::handler($locked->module);
            $locked->loadMissing(['cooperative', 'items']);

            // L3: jangan terapkan perubahan bila koperasi target tidak tersedia.
            if (! $locked->cooperative instanceof Cooperative) {
                throw SubmissionTransitionException::targetUnavailable($locked);
            }

            // M2: hanya field whitelist handler yang boleh diterapkan.
            $allowed = array_keys($handler->fields());
            $proposed = $this->proposedValues($locked);
            $values = array_intersect_key($proposed, array_flip($allowed));
            $ignored = array_values(array_diff(array_keys($proposed), $allowed));

            $before = $handler->currentValues($locked);
            $changes = $this->changesForAudit($locked, $before, $allowed);

            // FR-143: tulis ke data resmi di dalam transaksi.
            $handler->apply($locked, $values, $admin);

            $from = $locked->status;

            $this->applyStatusUpdate($locked, $from, [
                'status' => 'approved',
                'reason' => null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'applied_at' => now(),
            ]);

            $locked->refresh();

            $this->recordReview($locked, $admin, $from, 'approved', null);

            // FR-150/151: riwayat nilai lama → baru.
            $properties = ['changes' => $changes];

            if ($ignored !== []) {
                // Catat field tak berizin yang diabaikan (defense-in-depth M2) agar dapat diaudit.
                $properties['ignored_fields'] = $ignored;
            }

            $this->logger->log('approve_submission', $locked, $admin, $properties, 'Pengajuan disetujui dan diterapkan ke data resmi.');

            return $locked;
        });

        $this->notifySubmitter($approved);

        return $approved;
    }

    /**
     * Minta perbaikan (FR-142) — alasan wajib.
     */
    public function requestRevision(Submission $submission, User $admin, string $reason): Submission
    {
        $updated = $this->transition($submission, $admin, 'needs_revision', $reason, 'request_revision_submission');
        $this->notifySubmitter($updated);

        return $updated;
    }

    /**
     * Tolak pengajuan (FR-142) — alasan wajib.
     */
    public function reject(Submission $submission, User $admin, string $reason): Submission
    {
        $updated = $this->transition($submission, $admin, 'rejected', $reason, 'reject_submission');
        $this->notifySubmitter($updated);

        return $updated;
    }

    /**
     * Arsipkan pengajuan (BR-011, F5-10): status `archived` **dan** soft delete (deleted_at).
     * Data & riwayat (item, review, audit) tetap tersimpan; pengajuan tidak diakses lagi
     * melalui endpoint normal.
     */
    public function archive(Submission $submission, User $actor): Submission
    {
        return DB::transaction(function () use ($submission, $actor): Submission {
            $locked = $this->lockSubmission($submission);
            $this->performTransition($locked, $actor, 'archived', null, 'archive_submission');

            $locked->delete();

            return $locked;
        });
    }

    /**
     * Nilai usulan (field → new_value) dari item pengajuan.
     *
     * @return array<string, string|null>
     */
    public function proposedValues(Submission $submission): array
    {
        $submission->loadMissing('items');

        $values = [];

        foreach ($submission->items as $item) {
            $values[$item->field] = $item->new_value;
        }

        return $values;
    }

    /**
     * Nilai resmi saat ini untuk modul pengajuan (key = nama field).
     *
     * @return array<string, string|null>
     */
    public function officialValues(Cooperative $cooperative, string $module): array
    {
        return SubmissionModules::handler($module)
            ->currentValues($this->transientSubmission($cooperative, $module));
    }

    /**
     * Transisi status generik: transaksi + lock + penjagaan matriks transisi (PRD §12.2).
     */
    private function transition(Submission $submission, User $actor, string $to, ?string $reason, string $action): Submission
    {
        return DB::transaction(function () use ($submission, $actor, $to, $reason, $action): Submission {
            $locked = $this->lockSubmission($submission);

            return $this->performTransition($locked, $actor, $to, $reason, $action);
        });
    }

    /**
     * Inti transisi — dipanggil hanya oleh pemanggil yang sudah memegang lock + transaksi.
     */
    private function performTransition(Submission $locked, User $actor, string $to, ?string $reason, string $action): Submission
    {
        $this->assertTransition($locked, $to);

        $from = $locked->status;

        $attributes = ['status' => $to];

        if ($to === 'needs_revision' || $to === 'rejected') {
            $attributes['reason'] = $reason;
            $attributes['reviewed_by'] = $actor->id;
            $attributes['reviewed_at'] = now();
        }

        if ($to === 'submitted') {
            // Sinkronkan ulang status peninjauan saat pengajuan dikirim (ulang).
            $attributes['reviewed_by'] = null;
            $attributes['reviewed_at'] = null;
        }

        $this->applyStatusUpdate($locked, $from, $attributes);
        $locked->refresh();

        $this->recordReview($locked, $actor, $from, $to, $reason);
        $this->logger->log($action, $locked, $actor, [
            'from' => $from,
            'to' => $to,
            'reason' => $reason,
        ]);

        return $locked;
    }

    /**
     * Ambil ulang pengajuan dari DB dengan `lockForUpdate()` (row lock pada driver yang
     * mendukung; no-op pada SQLite). Membuang status stale hasil route model binding.
     */
    private function lockSubmission(Submission $submission): Submission
    {
        return Submission::query()
            ->whereKey($submission->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Kunci baris koperasi (anchor stabil) untuk menyerialkan pembuatan pengajuan (T-1).
     *
     * Mengapa koperasi, bukan submission? Pada pembuatan pertama belum ada baris submission untuk
     * dikunci, sehingga mengunci submission tidak menutup balapan. Baris koperasi selalu ada dan
     * sama untuk semua jalur pembuatan (draft biasa & versi revisi) pada koperasi itu, sehingga
     * urutan lock selalu konsisten (`cooperatives` lebih dulu) dan tidak menimbulkan deadlock.
     * `withTrashed()` dipakai agar lock tetap didapat walaupun koperasi di-soft-delete (barisnya
     * masih ada di basis data).
     *
     * Catatan: pengamanan **tingkat aplikasi**. SQLite mengabaikan `FOR UPDATE` (no-op), dan ini
     * **bukan** pengganti constraint basis data (mis. unique index bersyarat). Lihat
     * `docs/DECISIONS.md` §18.
     */
    private function lockCooperativeAnchor(Cooperative|int $cooperative): void
    {
        $id = $cooperative instanceof Cooperative ? $cooperative->getKey() : $cooperative;

        if ($id === null) {
            return;
        }

        Cooperative::withTrashed()->whereKey($id)->lockForUpdate()->first();
    }

    /**
     * Penjaga optimistik: update status hanya berhasil bila status di basis data masih
     * sama dengan status yang diharapkan (`$from`). Lapis kedua setelah `lockForUpdate()`.
     *
     * @param  string  $from  Status yang diharapkan (lama) di basis data.
     * @param  array<string, mixed>  $attributes
     */
    private function applyStatusUpdate(Submission $submission, string $from, array $attributes): void
    {
        $affected = Submission::query()
            ->whereKey($submission->getKey())
            ->where('status', $from)
            ->update($attributes);

        if ($affected === 0) {
            throw SubmissionTransitionException::stale($submission, $from);
        }
    }

    private function assertTransition(Submission $submission, string $to): void
    {
        if (! $submission->canTransitionTo($to)) {
            throw SubmissionTransitionException::invalid($submission, $to);
        }
    }

    private function assertEditable(Submission $submission): void
    {
        if (! $submission->isEditable()) {
            throw SubmissionTransitionException::notEditable($submission);
        }
    }

    /**
     * Tolak pembuatan pengajuan baru bila sudah ada pengajuan **aktif** untuk koperasi & modul
     * yang sama. Pengajuan `needs_revision` dihitung aktif **hanya bila belum digantikan** versi
     * turunan — induk yang sudah diperbaiki tidak lagi menghambat pengajuan berikutnya.
     *
     * **Wajib dipanggil di dalam transaksi yang sudah memegang lock koperasi**
     * (`lockCooperativeAnchor()`) agar pemeriksaan tidak rentan balapan (T-1).
     *
     * @param  list<int>  $ignoreIds  ID yang dikecualikan (mis. versi induk yang sedang diperbaiki).
     */
    private function assertNoConflictingActive(Cooperative $cooperative, string $module, array $ignoreIds = []): void
    {
        $exists = Submission::query()
            ->where('cooperative_id', $cooperative->getKey())
            ->where('module', $module)
            ->when($ignoreIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $ignoreIds))
            ->where(function (Builder $query): void {
                $query->whereIn('status', ['draft', 'submitted', 'under_review'])
                    ->orWhere(function (Builder $inner): void {
                        $inner->where('status', 'needs_revision')->whereDoesntHave('children');
                    });
            })
            ->exists();

        if ($exists) {
            throw SubmissionVersionException::activeExists($cooperative, $module);
        }
    }

    /**
     * @param  array<int, array{field: string, old_value: string|null, new_value: string|null}>  $items
     */
    private function syncItems(Submission $submission, array $items): void
    {
        $submission->items()->delete();

        foreach ($items as $item) {
            $submission->items()->create($item);
        }

        $submission->unsetRelation('items');
    }

    private function recordReview(Submission $submission, User $user, ?string $from, string $to, ?string $note): void
    {
        $submission->reviews()->create([
            'user_id' => $user->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<int, array{field: string, old_value: string|null, new_value: string|null}>
     */
    private function buildItems(SubmissionModuleHandler $handler, Submission $submission, array $inputs): array
    {
        $current = $handler->currentValues($submission);
        $items = [];

        foreach ($handler->fields() as $field => $definition) {
            // Field yang tidak dikirim dianggap TIDAK diajukan (bukan dikosongkan). Form
            // pengajuan selalu mengirim seluruh field, sehingga nilai kosong eksplisit
            // berarti pengosongan.
            if (! array_key_exists($field, $inputs)) {
                continue;
            }

            $new = $this->normalizeInput($inputs[$field]);
            $old = $current[$field] ?? null;

            if ((string) $new === (string) $old) {
                continue;
            }

            $items[] = ['field' => $field, 'old_value' => $old, 'new_value' => $new];
        }

        return $items;
    }

    /**
     * @param  array<string, string|null>  $before
     * @param  array<int, string>  $allowed
     * @return array<int, array{field: string, old: string|null, new: string|null}>
     */
    private function changesForAudit(Submission $submission, array $before, array $allowed): array
    {
        $changes = [];

        foreach ($submission->items as $item) {
            if (! in_array($item->field, $allowed, true)) {
                continue;
            }

            $changes[] = [
                'field' => $item->field,
                'old' => $before[$item->field] ?? null,
                'new' => $item->new_value,
            ];
        }

        return $changes;
    }

    private function normalizeInput(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : (string) $value;
    }

    private function transientSubmission(Cooperative $cooperative, string $module): Submission
    {
        $submission = new Submission(['module' => $module]);
        $submission->cooperative_id = $cooperative->id;
        $submission->setRelation('cooperative', $cooperative);

        return $submission;
    }

    private function defaultTitle(string $module, Cooperative $cooperative): string
    {
        $label = SubmissionModules::labels()[$module] ?? $module;

        return "Perubahan {$label} — {$cooperative->nama}";
    }

    /**
     * Kirim notifikasi in-app setelah transaksi berhasil. Kegagalan notifikasi **tidak**
     * membatalkan keputusan yang sudah ter-commit; kegagalan dicatat untuk diagnosis.
     */
    private function notifySubmitter(Submission $submission): void
    {
        $submission->loadMissing('submitter');

        $submitter = $submission->submitter;

        if ($submitter === null) {
            return;
        }

        try {
            $submitter->notify(new SubmissionStatusNotification($submission));
        } catch (Throwable $exception) {
            report($exception);

            $this->logger->log('notification_failed', $submission, null, [
                'error' => $exception->getMessage(),
                'status' => $submission->status,
            ], 'Notifikasi status pengajuan gagal dikirim.');
        }
    }
}
