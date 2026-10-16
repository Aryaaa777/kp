<?php

namespace Tests\Feature;

use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\User;
use App\Services\SubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test notifikasi in-app status pengajuan (FR-201).
 *
 * Kanal: `database` saja (email/WA ditunda — Q-011).
 */
class SubmissionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSubmission(): array
    {
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Asli']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $service = app(SubmissionService::class);

        $submission = $service->createDraft($pengelola, $cooperative, 'koperasi_induk', [
            'nama' => 'Koperasi Baru',
        ]);
        $service->submit($submission, $pengelola);
        $service->startReview($submission, $admin);
        $service->approve($submission, $admin);

        return [$submission, $pengelola, $admin];
    }

    public function test_approval_notifies_submitter(): void
    {
        [$submission, $pengelola] = $this->approvedSubmission();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $pengelola->id,
        ]);

        $notification = $pengelola->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('approved', $notification->data['status']);
        $this->assertSame($submission->id, $notification->data['submission_id']);
    }

    public function test_rejection_notifies_submitter_with_reason(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $admin = User::factory()->admin()->create();

        $submission = Submission::factory()->underReview()->create([
            'cooperative_id' => $cooperative->id,
            'submitted_by' => $pengelola->id,
        ]);

        app(SubmissionService::class)->reject($submission, $admin, 'Tidak sesuai.');

        $notification = $pengelola->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('rejected', $notification->data['status']);
        $this->assertSame('Tidak sesuai.', $notification->data['reason']);
    }

    public function test_pengelola_can_view_and_mark_notifications_read(): void
    {
        [, $pengelola] = $this->approvedSubmission();

        $this->actingAs($pengelola)
            ->get(route('pengelola.notifikasi.index'))
            ->assertOk()
            ->assertSee('Pengajuan disetujui');

        $this->actingAs($pengelola)
            ->post(route('pengelola.notifikasi.baca-semua'))
            ->assertRedirect();

        $this->assertSame(0, $pengelola->unreadNotifications()->count());
    }

    public function test_pengelola_only_sees_own_notifications(): void
    {
        [, $pengelola] = $this->approvedSubmission();

        $cooperativeB = Cooperative::factory()->create();
        $lain = User::factory()->pengelola($cooperativeB->id)->create();

        $this->actingAs($lain)
            ->get(route('pengelola.notifikasi.index'))
            ->assertOk()
            ->assertDontSee('Pengajuan disetujui');

        // Notifikasi tetap milik submitter asli, bukan pengelola lain.
        $this->assertSame(0, $lain->notifications()->count());
        $this->assertGreaterThan(0, $pengelola->notifications()->count());
    }
}
