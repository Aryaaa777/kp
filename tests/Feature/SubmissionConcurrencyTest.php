<?php

namespace Tests\Feature;

use App\Exceptions\SubmissionTransitionException;
use App\Models\ActivityLog;
use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use App\Services\SubmissionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test konkurensi/alur balapan workflow pengajuan (M1).
 *
 * Membuktikan bahwa keputusan atau operasi tulis yang saling berkonflik tidak dapat
 * sama-sama berhasil memproses status pengajuan yang sama, dan tidak menghasilkan
 * review/audit/notifikasi ganda.
 *
 * Catatan batasan: `lockForUpdate()` adalah no-op pada SQLite (driver pengujian), sehingga
 * tes ini tidak membuktikan serialisasi sesungguhnya. Yang diuji adalah penjagaan
 * logis: pengambilan ulang record di dalam transaksi + pemeriksaan transisi atas status
 * terbaru + conditional update optimistik. Penguncian baris nyata (MySQL/MariaDB) belum
 * dibuktikan lewat eksekusi langsung.
 */
class SubmissionConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubmissionService
    {
        return app(SubmissionService::class);
    }

    public function test_second_approval_on_stale_instance_is_rejected_without_duplicates(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $submission = Submission::factory()->underReview()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);
        SubmissionItem::factory()->create([
            'submission_id' => $submission->id,
            'field' => 'nama',
            'old_value' => 'Koperasi Asli',
            'new_value' => 'Koperasi Baru',
        ]);

        $this->service()->approve($submission, $admin);

        // Percobaan kedua memakai instance STALE (status in-memory masih `under_review`).
        try {
            $this->service()->approve($submission, $admin);
            $this->fail('Keputusan kedua seharusnya ditolak karena status sudah berubah.');
        } catch (SubmissionTransitionException $exception) {
            $this->assertStringContainsString('tidak sah', $exception->getMessage());
        }

        // Tidak ada efek ganda.
        $this->assertSame(1, ActivityLog::query()->where('action', 'approve_submission')->count());
        $this->assertSame(1, $submission->fresh()->reviews()->where('to_status', 'approved')->count());
        $this->assertSame(1, $pengelola->notifications()->count());
        $this->assertSame('approved', $submission->fresh()->status);
    }

    public function test_update_after_submit_on_stale_instance_is_rejected(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'draft',
        ]);
        SubmissionItem::factory()->create([
            'submission_id' => $submission->id,
            'field' => 'nama',
            'old_value' => 'Koperasi Asli',
            'new_value' => 'Koperasi Baru',
        ]);

        $this->service()->submit($submission, $pengelola);

        // Instance lama masih `draft`; pengeditan harus ditolak.
        try {
            $this->service()->updateDraft($submission, $pengelola, ['nama' => 'Koperasi Revisi']);
            $this->fail('Update setelah submit seharusnya ditolak.');
        } catch (SubmissionTransitionException $exception) {
            $this->assertStringContainsString('tidak dapat diubah', $exception->getMessage());
        }

        $this->assertSame('Koperasi Baru', SubmissionItem::query()->first()->new_value);
    }

    public function test_double_submit_is_rejected(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'draft',
        ]);
        SubmissionItem::factory()->create(['submission_id' => $submission->id]);

        $this->service()->submit($submission, $pengelola);

        try {
            $this->service()->submit($submission, $pengelola);
            $this->fail('Submit kedua seharusnya ditolak.');
        } catch (SubmissionTransitionException $exception) {
            $this->assertStringContainsString('tidak dapat diubah', $exception->getMessage());
        }

        $this->assertSame(1, ActivityLog::query()->where('action', 'submit_submission')->count());
    }

    public function test_archive_twice_does_not_duplicate_history(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'draft',
        ]);

        $this->service()->archive($submission, $pengelola);

        try {
            $this->service()->archive($submission, $pengelola);
            $this->fail('Arsip kedua seharusnya tidak menemukan record aktif.');
        } catch (ModelNotFoundException $exception) {
            // diharapkan: pengajuan sudah tidak ada di query default (soft delete)
        }

        $this->assertSame(1, ActivityLog::query()->where('action', 'archive_submission')->count());
        $this->assertSame(1, Submission::withTrashed()->find($submission->id)->reviews()->where('to_status', 'archived')->count());
    }
}
