<?php

namespace Tests\Feature;

use App\Exceptions\SubmissionTransitionException;
use App\Models\ActivityLog;
use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\SubmissionItem;
use App\Models\User;
use App\Services\SubmissionService;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Feature test pengerasan F5: defense-in-depth whitelist (M2), kegagalan notifikasi (L2),
 * koperasi target tidak tersedia (L3), dan unique constraint item (L4).
 */
class SubmissionHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function service(): SubmissionService
    {
        return app(SubmissionService::class);
    }

    public function test_non_whitelisted_item_fields_are_ignored_on_apply(): void
    {
        $cooperative = Cooperative::factory()->create([
            'nama' => 'Koperasi Asli',
            'status_verifikasi' => 'draft',
        ]);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();
        $originalIdentity = $cooperative->nomor_identitas;

        $submission = Submission::factory()->underReview()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);

        // Item sah.
        SubmissionItem::factory()->create([
            'submission_id' => $submission->id,
            'field' => 'nama',
            'old_value' => 'Koperasi Asli',
            'new_value' => 'Koperasi Baru',
        ]);

        // Item tak berizin disuntikkan langsung di lapisan DB (bukan via HTTP).
        SubmissionItem::factory()->create([
            'submission_id' => $submission->id,
            'field' => 'status_verifikasi',
            'old_value' => 'draft',
            'new_value' => 'verified',
        ]);
        SubmissionItem::factory()->create([
            'submission_id' => $submission->id,
            'field' => 'nomor_identitas',
            'old_value' => $originalIdentity,
            'new_value' => 'HACKED-IDENTITY',
        ]);

        $this->service()->approve($submission, $admin);

        $cooperative->refresh();
        $this->assertSame('Koperasi Baru', $cooperative->nama);
        $this->assertSame('draft', $cooperative->status_verifikasi);
        $this->assertSame($originalIdentity, $cooperative->nomor_identitas);

        // Audit hanya memuat perubahan yang benar-benar diterapkan + daftar field diabaikan.
        $log = ActivityLog::query()->where('action', 'approve_submission')->firstOrFail();
        $this->assertSame(['nama'], array_column($log->properties['changes'], 'field'));
        $this->assertEqualsCanonicalizing(
            ['status_verifikasi', 'nomor_identitas'],
            $log->properties['ignored_fields'] ?? [],
        );
    }

    public function test_notification_failure_does_not_fail_committed_decision(): void
    {
        $this->app->bind(Dispatcher::class, fn () => new class implements Dispatcher
        {
            public function send($notifiables, $notification)
            {
                throw new RuntimeException('Layanan notifikasi tidak tersedia.');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null)
            {
                throw new RuntimeException('Layanan notifikasi tidak tersedia.');
            }
        });

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

        // Tidak boleh melempar walaupun notifikasi gagal.
        $this->service()->approve($submission, $admin);

        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertSame('Koperasi Baru', $cooperative->fresh()->nama);
        $this->assertSame(1, ActivityLog::query()->where('action', 'approve_submission')->count());

        // Kegagalan dicatat (dapat didiagnosis), bukan catch kosong.
        $this->assertSame(1, ActivityLog::query()->where('action', 'notification_failed')->count());
        $this->assertSame(0, $pengelola->notifications()->count());
    }

    public function test_approve_fails_safely_when_cooperative_target_is_unavailable(): void
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

        $cooperative->delete();

        try {
            $this->service()->approve($submission, $admin);
            $this->fail('Approve seharusnya gagal bila koperasi target tidak tersedia.');
        } catch (SubmissionTransitionException $exception) {
            $this->assertStringContainsString('tidak tersedia', $exception->getMessage());
        }

        $this->assertSame('under_review', $submission->fresh()->status);
        $this->assertNull($submission->fresh()->applied_at);
        $this->assertSame(0, ActivityLog::query()->where('action', 'approve_submission')->count());
        $this->assertSame('Koperasi Asli', Cooperative::withTrashed()->find($cooperative->id)->nama);
    }

    public function test_edit_submission_returns_not_found_when_cooperative_unavailable(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $submission = Submission::factory()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
            'status' => 'draft',
        ]);

        $cooperative->delete();

        $this->actingAs($pengelola)
            ->get(route('pengelola.pengajuan.edit', $submission))
            ->assertNotFound();
    }

    public function test_submission_item_field_is_unique_per_submission(): void
    {
        $submission = Submission::factory()->create();

        SubmissionItem::factory()->create([
            'submission_id' => $submission->id,
            'field' => 'nama',
        ]);

        $this->expectException(QueryException::class);

        SubmissionItem::factory()->create([
            'submission_id' => $submission->id,
            'field' => 'nama',
        ]);
    }
}
