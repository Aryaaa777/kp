<?php

namespace Tests\Feature;

use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test otorisasi workflow pengajuan (FR-140/141, NFR-SEC-004/005).
 *
 * Fokus: RBAC server-side + isolasi antar koperasi (anti-IDOR). Menyembunyikan tombol di
 * Blade bukan pengamanan — semua aksi diverifikasi di server.
 */
class SubmissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('pengelola.pengajuan.index'))->assertRedirect(route('login'));
        $this->get(route('admin.pengajuan.index'))->assertRedirect(route('login'));
        $this->get(route('admin.audit-log.index'))->assertRedirect(route('login'));
    }

    public function test_pengelola_cannot_access_admin_queue(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)->get(route('admin.pengajuan.index'))->assertForbidden();
        $this->actingAs($pengelola)->get(route('admin.audit-log.index'))->assertForbidden();
    }

    public function test_admin_cannot_access_pengelola_area(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('pengelola.pengajuan.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('pengelola.pengajuan.create'))->assertForbidden();
    }

    public function test_pengelola_cannot_view_other_cooperative_submission(): void
    {
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $pengelolaA = User::factory()->pengelola($cooperativeA->id)->create();

        $submissionB = Submission::factory()->create(['cooperative_id' => $cooperativeB->id]);

        // Anti-IDOR: pengajuan koperasi lain → 403.
        $this->actingAs($pengelolaA)
            ->get(route('pengelola.pengajuan.show', $submissionB))
            ->assertForbidden();
    }

    public function test_pengelola_cannot_edit_or_submit_other_cooperative_submission(): void
    {
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $pengelolaA = User::factory()->pengelola($cooperativeA->id)->create();

        $submissionB = Submission::factory()->create([
            'cooperative_id' => $cooperativeB->id,
            'status' => 'draft',
        ]);

        $this->actingAs($pengelolaA)
            ->get(route('pengelola.pengajuan.edit', $submissionB))
            ->assertForbidden();

        $this->actingAs($pengelolaA)
            ->post(route('pengelola.pengajuan.submit', $submissionB))
            ->assertForbidden();
    }

    public function test_pengelola_cannot_decide_submissions(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $submission = Submission::factory()->underReview()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($pengelola)
            ->post(route('admin.pengajuan.decide', $submission), ['decision' => 'approve'])
            ->assertForbidden();
    }

    public function test_admin_can_view_any_submission(): void
    {
        $cooperative = Cooperative::factory()->create();
        $admin = User::factory()->admin()->create();
        $submission = Submission::factory()->submitted()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->get(route('admin.pengajuan.show', $submission))
            ->assertOk()
            ->assertSee($cooperative->nama);
    }
}
