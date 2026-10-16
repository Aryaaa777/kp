<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\SubmissionReview;
use App\Models\User;
use App\Services\SubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test semantik arsip pengajuan (L1): arsip = status `archived` + soft delete.
 *
 * BR-011 / F5-10: arsip bukan hapus permanen. Riwayat (item, review, audit) tetap ada,
 * pengajuan tidak dapat diedit/dikirim/diputuskan lagi melalui endpoint normal.
 */
class SubmissionArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubmissionService
    {
        return app(SubmissionService::class);
    }

    public function test_archive_soft_deletes_and_preserves_history(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'draft',
        ]);
        SubmissionItem::factory()->create(['submission_id' => $submission->id]);

        $this->service()->archive($submission, $pengelola);

        $archived = Submission::withTrashed()->findOrFail($submission->id);

        $this->assertSame('archived', $archived->status);
        $this->assertNotNull($archived->deleted_at);
        $this->assertNull(Submission::query()->find($submission->id));

        // Riwayat tetap tersimpan.
        $this->assertSame(1, SubmissionItem::query()->where('submission_id', $submission->id)->count());
        $this->assertSame(1, SubmissionReview::query()->where('submission_id', $submission->id)->where('to_status', 'archived')->count());
        $this->assertSame(1, ActivityLog::query()->where('action', 'archive_submission')->count());
    }

    public function test_archived_submission_is_hidden_from_default_listing(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);

        $this->service()->archive($submission, $pengelola);

        $this->actingAs($pengelola)
            ->get(route('pengelola.pengajuan.index'))
            ->assertOk()
            ->assertDontSee('Pengajuan perubahan data induk');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->get(route('admin.pengajuan.index'))
            ->assertOk()
            ->assertDontSee('Pengajuan perubahan data induk');
    }

    public function test_archived_submission_is_not_accessible_via_normal_endpoints(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'draft',
        ]);

        $this->service()->archive($submission, $pengelola);

        // Admin: detail, edit, keputusan → 404.
        $this->actingAs($admin)->get(route('admin.pengajuan.show', $submission))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.pengajuan.decide', $submission), ['decision' => 'approve'])->assertNotFound();

        // Pengelola: detail, edit, kirim ulang → 404.
        $this->actingAs($pengelola)->get(route('pengelola.pengajuan.show', $submission))->assertNotFound();
        $this->actingAs($pengelola)->get(route('pengelola.pengajuan.edit', $submission))->assertNotFound();
        $this->actingAs($pengelola)->post(route('pengelola.pengajuan.submit', $submission))->assertNotFound();
    }

    public function test_archived_submission_does_not_block_new_submission(): void
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $archived = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);
        $this->service()->archive($archived, $pengelola);

        $this->actingAs($pengelola)->post(route('pengelola.pengajuan.store'), [
            'module' => 'koperasi_induk',
            'nama' => 'Koperasi Baru',
            'jenis_koperasi' => $cooperative->jenis_koperasi,
        ])->assertRedirect();

        $this->assertSame(1, Submission::query()->where('cooperative_id', $cooperative->id)->count());
        $this->assertSame(2, Submission::withTrashed()->where('cooperative_id', $cooperative->id)->count());
    }
}
