<?php

namespace Tests\Feature;

use App\Exceptions\EmptySubmissionException;
use App\Exceptions\SubmissionTransitionException;
use App\Models\ActivityLog;
use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use App\Services\SubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature test workflow pengajuan & verifikasi (FR-140…143).
 *
 * AC-WF-01: data resmi TIDAK berubah saat pengajuan dibuat/dikirim (BR-014, PRD §12.3).
 * AC-WF-02: perubahan baru diterapkan ke data resmi saat disetujui.
 * AC-WF-03: keputusan perbaikan/penolakan wajib beralasan.
 * AC-WF-04: persetujuan menulis data resmi + riwayat nilai lama → baru.
 * AC-WF-05: transisi status di luar matriks PRD §12.2 ditolak.
 *
 * @see docs/PRD.md §12, FR-140…143
 */
class SubmissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubmissionService
    {
        return app(SubmissionService::class);
    }

    public function test_draft_does_not_change_official_data(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = $this->service()->createDraft(
            $pengelola,
            $cooperative,
            'koperasi_induk',
            ['nama' => 'Koperasi Usulan', 'email' => $cooperative->email],
        );

        $this->assertSame('draft', $submission->status);
        $this->assertDatabaseHas('submissions', ['id' => $submission->id, 'status' => 'draft']);

        // AC-WF-01: data resmi tetap.
        $this->assertSame('Koperasi Asli', $cooperative->fresh()->nama);

        // Hanya field yang berubah yang disimpan.
        $this->assertSame(['nama'], $submission->items->pluck('field')->all());
        $this->assertSame('Koperasi Asli', $submission->items->first()->old_value);
        $this->assertSame('Koperasi Usulan', $submission->items->first()->new_value);
    }

    public function test_submitted_submission_does_not_change_official_data(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = $this->service()->createDraft(
            $pengelola,
            $cooperative,
            'koperasi_induk',
            ['nama' => 'Koperasi Usulan'],
        );

        $this->service()->submit($submission, $pengelola);

        $this->assertSame('submitted', $submission->fresh()->status);

        // AC-WF-02: data resmi belum berubah sebelum disetujui.
        $this->assertSame('Koperasi Asli', $cooperative->fresh()->nama);
    }

    public function test_approve_applies_changes_and_records_audit(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $submission = $this->service()->createDraft(
            $pengelola,
            $cooperative,
            'koperasi_induk',
            ['nama' => 'Koperasi Baru'],
        );

        $this->service()->submit($submission, $pengelola);
        $this->service()->startReview($submission, $admin);
        $this->service()->approve($submission, $admin);

        $submission->refresh();

        // AC-WF-04: data resmi diperbarui.
        $this->assertSame('Koperasi Baru', $cooperative->fresh()->nama);
        $this->assertSame('approved', $submission->status);
        $this->assertNotNull($submission->applied_at);
        $this->assertSame($admin->id, $submission->reviewed_by);

        // Audit trail dengan nilai lama → baru.
        $log = ActivityLog::query()->where('action', 'approve_submission')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Koperasi Asli', $log->properties['changes'][0]['old']);
        $this->assertSame('Koperasi Baru', $log->properties['changes'][0]['new']);
    }

    public function test_review_decision_requires_reason(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $submission = Submission::factory()->underReview()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);

        // AC-WF-03: alasan wajib — tanpa alasan → error, status tidak berubah.
        $this->actingAs($admin)
            ->from(route('admin.pengajuan.show', $submission))
            ->post(route('admin.pengajuan.decide', $submission), ['decision' => 'needs_revision'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('under_review', $submission->fresh()->status);

        // Dengan alasan → transisi berhasil.
        $this->actingAs($admin)
            ->post(route('admin.pengajuan.decide', $submission), [
                'decision' => 'rejected',
                'reason' => 'Data tidak sesuai dokumen resmi.',
            ])
            ->assertRedirect(route('admin.pengajuan.show', $submission));

        $this->assertSame('rejected', $submission->fresh()->status);
        $this->assertSame('Data tidak sesuai dokumen resmi.', $submission->fresh()->reason);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'draft',
        ]);

        // AC-WF-05: draft → approved tidak sah.
        $this->expectException(SubmissionTransitionException::class);
        $this->service()->approve($submission, $admin);
    }

    public function test_empty_submission_is_rejected(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Sama']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->expectException(EmptySubmissionException::class);

        // Semua nilai sama dengan data resmi → tidak ada perubahan.
        $this->service()->createDraft($pengelola, $cooperative, 'koperasi_induk', [
            'nama' => 'Koperasi Sama',
        ]);
    }

    public function test_apply_failure_rolls_back_and_keeps_status(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $submission = Submission::factory()->underReview()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);

        // `nama` NOT NULL di DB — penerapan nilai null akan gagal di tengah transaksi.
        SubmissionItem::factory()->create([
            'submission_id' => $submission->id,
            'field' => 'nama',
            'old_value' => 'Koperasi Asli',
            'new_value' => null,
        ]);

        try {
            $this->service()->approve($submission, $admin);
            $this->fail('Penerapan seharusnya gagal dan me-rollback transaksi.');
        } catch (\Throwable $exception) {
            $this->assertNotInstanceOf(SubmissionTransitionException::class, $exception);
        }

        // Rollback: status tetap `under_review`, data resmi tidak berubah, tanpa audit approve.
        $this->assertSame('under_review', $submission->fresh()->status);
        $this->assertSame('Koperasi Asli', $cooperative->fresh()->nama);
        $this->assertNull($submission->fresh()->applied_at);
        $this->assertSame(0, ActivityLog::query()->where('action', 'approve_submission')->count());
    }

    public function test_archive_uses_soft_delete_not_hard_delete(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'draft',
        ]);

        $this->service()->archive($submission, $pengelola);

        // F5-10 (L1): arsip = status `archived` + soft delete (deleted_at).
        $archived = Submission::withTrashed()->findOrFail($submission->id);
        $this->assertSame('archived', $archived->status);
        $this->assertNotNull($archived->deleted_at);

        // Tidak tampil di query default, tetapi baris tetap ada (bukan hard delete).
        $this->assertNull(Submission::query()->find($submission->id));
        $this->assertNotNull(DB::table('submissions')->where('id', $submission->id)->value('id'));
    }

    public function test_review_decision_reason_must_not_be_only_whitespace(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $submission = Submission::factory()->underReview()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);

        // L5: string hanya spasi ditolak.
        $this->actingAs($admin)
            ->from(route('admin.pengajuan.show', $submission))
            ->post(route('admin.pengajuan.decide', $submission), [
                'decision' => 'needs_revision',
                'reason' => '     ',
            ])
            ->assertSessionHasErrors('reason');

        // L5: string kosong ditolak.
        $this->actingAs($admin)
            ->from(route('admin.pengajuan.show', $submission))
            ->post(route('admin.pengajuan.decide', $submission), [
                'decision' => 'rejected',
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        // L5: melebihi batas panjang ditolak.
        $this->actingAs($admin)
            ->from(route('admin.pengajuan.show', $submission))
            ->post(route('admin.pengajuan.decide', $submission), [
                'decision' => 'rejected',
                'reason' => str_repeat('a', 1001),
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame('under_review', $submission->fresh()->status);
    }

    public function test_review_decision_reason_is_trimmed_when_stored(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $submission = Submission::factory()->underReview()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.pengajuan.decide', $submission), [
                'decision' => 'needs_revision',
                'reason' => '  Lengkapi alamat kantor.  ',
            ])
            ->assertRedirect(route('admin.pengajuan.show', $submission));

        $this->assertSame('Lengkapi alamat kantor.', $submission->fresh()->reason);
        $this->assertSame('Lengkapi alamat kantor.', $submission->fresh()->reviews()->latest('id')->first()->note);
    }
}
