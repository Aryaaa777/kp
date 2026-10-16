<?php

namespace Tests\Feature;

use App\Models\Cooperative;
use App\Models\HealthAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test modul penilaian kesehatan koperasi (FR-080/081).
 *
 * PROVISIONAL — periode tahunan; skor/predikat manual (tanpa formula resmi). Lihat DECISIONS §10.
 *
 * Cakupan: CRUD admin, validasi periode/tanggal & keunikan (AC-PER-01), isolasi antar koperasi
 * (anti-IDOR), pengelola read-only (BR-010), guest ditolak, dan mass assignment cooperative_id.
 */
class HealthAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->get(route('admin.koperasi.kesehatan.index', $cooperative))->assertRedirect(route('login'));
        $this->get(route('pengelola.kesehatan.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_health_index(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        HealthAssessment::factory()->forPeriod(2023)->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.kesehatan.index', $cooperative))
            ->assertOk()
            ->assertSee('2023');
    }

    public function test_admin_can_create_health_assessment(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.kesehatan.store', $cooperative), [
                'period_year' => 2024,
                'period' => 'annual',
                'assessment_date' => '2024-12-20',
                'score' => 85.5,
                'predicate' => 'Sehat (contoh)',
                'status' => 'final',
                'notes' => 'Catatan uji.',
            ])
            ->assertRedirect(route('admin.koperasi.kesehatan.index', $cooperative));

        $this->assertDatabaseHas('health_assessments', [
            'cooperative_id' => $cooperative->id,
            'period_year' => 2024,
            'period' => 'annual',
            'status' => 'final',
            'assessor_id' => $admin->id,
        ]);
    }

    public function test_cooperative_id_from_input_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();

        $this->actingAs($admin)->post(route('admin.koperasi.kesehatan.store', $cooperative), [
            'cooperative_id' => $other->id,
            'period_year' => 2024,
            'period' => 'annual',
            'assessment_date' => '2024-12-20',
        ]);

        $this->assertDatabaseHas('health_assessments', ['cooperative_id' => $cooperative->id]);
        $this->assertDatabaseMissing('health_assessments', ['cooperative_id' => $other->id]);
    }

    public function test_duplicate_period_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        HealthAssessment::factory()->forPeriod(2024)->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->from(route('admin.koperasi.kesehatan.create', $cooperative))
            ->post(route('admin.koperasi.kesehatan.store', $cooperative), [
                'period_year' => 2024,
                'period' => 'annual',
                'assessment_date' => '2024-12-21',
            ])
            ->assertSessionHasErrors('period_year');

        $this->assertSame(1, HealthAssessment::where('cooperative_id', $cooperative->id)->count());
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.kesehatan.create', $cooperative))
            ->post(route('admin.koperasi.kesehatan.store', $cooperative), [])
            ->assertSessionHasErrors(['period_year', 'period', 'assessment_date']);
    }

    public function test_future_assessment_date_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.kesehatan.create', $cooperative))
            ->post(route('admin.koperasi.kesehatan.store', $cooperative), [
                'period_year' => 2024,
                'period' => 'annual',
                'assessment_date' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('assessment_date');
    }

    public function test_admin_can_update_health_assessment(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $assessment = HealthAssessment::factory()->forPeriod(2024)->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->put(route('admin.koperasi.kesehatan.update', [$cooperative, $assessment]), [
                'score' => 91.25,
                'predicate' => 'Sangat Sehat (contoh)',
            ])
            ->assertRedirect(route('admin.koperasi.kesehatan.index', $cooperative));

        $this->assertDatabaseHas('health_assessments', [
            'id' => $assessment->id,
            'score' => 91.25,
            'predicate' => 'Sangat Sehat (contoh)',
        ]);
    }

    public function test_admin_can_archive_health_assessment(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $assessment = HealthAssessment::factory()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->delete(route('admin.koperasi.kesehatan.destroy', [$cooperative, $assessment]))
            ->assertRedirect(route('admin.koperasi.kesehatan.index', $cooperative));

        $this->assertSoftDeleted('health_assessments', ['id' => $assessment->id]);
    }

    public function test_cross_cooperative_scoped_binding_returns_404(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $assessmentB = HealthAssessment::factory()->create(['cooperative_id' => $cooperativeB->id]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.kesehatan.show', [$cooperativeA, $assessmentB]))
            ->assertNotFound();
    }

    public function test_pengelola_can_view_own_health(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $assessment = HealthAssessment::factory()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($pengelola)
            ->get(route('pengelola.kesehatan.index'))
            ->assertOk();

        $this->actingAs($pengelola)
            ->get(route('pengelola.kesehatan.show', $assessment))
            ->assertOk();
    }

    public function test_pengelola_cannot_view_other_cooperative_health(): void
    {
        $cooperative = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $foreign = HealthAssessment::factory()->create(['cooperative_id' => $other->id]);

        $this->actingAs($pengelola)
            ->get(route('pengelola.kesehatan.show', $foreign))
            ->assertForbidden();
    }

    public function test_pengelola_cannot_write_health(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        // Tidak ada route tulis pengelola; akses ke route admin ditolak middleware role.
        $this->actingAs($pengelola)
            ->post(route('admin.koperasi.kesehatan.store', $cooperative), [
                'period_year' => 2024,
                'period' => 'annual',
                'assessment_date' => '2024-12-20',
            ])
            ->assertForbidden();
    }

    public function test_unlinked_pengelola_index_is_forbidden(): void
    {
        $pengelola = User::factory()->pengelola(null)->create();

        $this->actingAs($pengelola)
            ->get(route('pengelola.kesehatan.index'))
            ->assertForbidden();
    }

    public function test_admin_can_restore_archived_health_assessment(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $assessment = HealthAssessment::factory()->create(['cooperative_id' => $cooperative->id]);
        $assessment->delete();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.kesehatan.restore', [$cooperative, $assessment]))
            ->assertRedirect(route('admin.koperasi.kesehatan.index', $cooperative));

        $this->assertNotSoftDeleted('health_assessments', ['id' => $assessment->id]);
    }

    public function test_restore_is_forbidden_for_pengelola_and_guest(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $assessment = HealthAssessment::factory()->create(['cooperative_id' => $cooperative->id]);
        $assessment->delete();

        $this->post(route('admin.koperasi.kesehatan.restore', [$cooperative, $assessment]))
            ->assertRedirect(route('login'));

        $this->actingAs($pengelola)
            ->post(route('admin.koperasi.kesehatan.restore', [$cooperative, $assessment]))
            ->assertForbidden();

        $this->assertSoftDeleted('health_assessments', ['id' => $assessment->id]);
    }

    public function test_restore_cross_cooperative_returns_404(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $assessmentB = HealthAssessment::factory()->create(['cooperative_id' => $cooperativeB->id]);
        $assessmentB->delete();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.kesehatan.restore', [$cooperativeA, $assessmentB]))
            ->assertNotFound();

        $this->assertSoftDeleted('health_assessments', ['id' => $assessmentB->id]);
    }

    public function test_archived_period_stays_unique_until_restored(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $assessment = HealthAssessment::factory()->forPeriod(2024)->create(['cooperative_id' => $cooperative->id]);
        $assessment->delete();

        // Periode masih dipakai baris terarsip → pembuatan ulang ditolak (unique tetap berlaku).
        $this->actingAs($admin)
            ->from(route('admin.koperasi.kesehatan.create', $cooperative))
            ->post(route('admin.koperasi.kesehatan.store', $cooperative), [
                'period_year' => 2024,
                'period' => 'annual',
                'assessment_date' => '2024-12-20',
            ])
            ->assertSessionHasErrors('period_year');

        $this->assertSame(1, HealthAssessment::withTrashed()->count());

        // Setelah dipulihkan, baris tetap tunggal (tidak ada duplikasi).
        $this->actingAs($admin)
            ->post(route('admin.koperasi.kesehatan.restore', [$cooperative, $assessment]))
            ->assertRedirect(route('admin.koperasi.kesehatan.index', $cooperative));

        $this->assertSame(1, HealthAssessment::withTrashed()->count());
        $this->assertNotSoftDeleted('health_assessments', ['id' => $assessment->id]);
    }

    public function test_archived_record_is_not_accessible_via_normal_endpoints(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $assessment = HealthAssessment::factory()->create(['cooperative_id' => $cooperative->id]);
        $assessment->delete();

        $this->actingAs($admin)
            ->get(route('admin.koperasi.kesehatan.show', [$cooperative, $assessment]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.koperasi.kesehatan.edit', [$cooperative, $assessment]))
            ->assertNotFound();
    }

    public function test_archived_record_is_not_visible_to_pengelola(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $assessment = HealthAssessment::factory()->create(['cooperative_id' => $cooperative->id]);
        $assessment->delete();

        $this->actingAs($pengelola)
            ->get(route('pengelola.kesehatan.show', $assessment))
            ->assertNotFound();
    }
}
