<?php

namespace Tests\Feature;

use App\Models\Cooperative;
use App\Models\GovernanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test modul tata kelola koperasi (FR-090).
 *
 * PROVISIONAL — periode tahunan; indikator belum resmi. Lihat DECISIONS §10.
 */
class GovernanceRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->get(route('admin.koperasi.tata-kelola.index', $cooperative))->assertRedirect(route('login'));
        $this->get(route('pengelola.tata-kelola.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_governance_index(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        GovernanceRecord::factory()->forPeriod(2023)->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.tata-kelola.index', $cooperative))
            ->assertOk()
            ->assertSee('2023');
    }

    public function test_admin_can_create_governance_record(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.tata-kelola.store', $cooperative), [
                'period_year' => 2024,
                'period' => 'annual',
                'meeting_count' => 5,
                'governance_score' => 80,
                'summary' => 'Ringkasan uji.',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.koperasi.tata-kelola.index', $cooperative));

        $this->assertDatabaseHas('governance_records', [
            'cooperative_id' => $cooperative->id,
            'period_year' => 2024,
            'period' => 'annual',
            'meeting_count' => 5,
            'recorded_by' => $admin->id,
        ]);
    }

    public function test_cooperative_id_from_input_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();

        $this->actingAs($admin)->post(route('admin.koperasi.tata-kelola.store', $cooperative), [
            'cooperative_id' => $other->id,
            'period_year' => 2024,
            'period' => 'annual',
        ]);

        $this->assertDatabaseHas('governance_records', ['cooperative_id' => $cooperative->id]);
        $this->assertDatabaseMissing('governance_records', ['cooperative_id' => $other->id]);
    }

    public function test_duplicate_period_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        GovernanceRecord::factory()->forPeriod(2024)->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->from(route('admin.koperasi.tata-kelola.create', $cooperative))
            ->post(route('admin.koperasi.tata-kelola.store', $cooperative), [
                'period_year' => 2024,
                'period' => 'annual',
            ])
            ->assertSessionHasErrors('period_year');

        $this->assertSame(1, GovernanceRecord::where('cooperative_id', $cooperative->id)->count());
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.tata-kelola.create', $cooperative))
            ->post(route('admin.koperasi.tata-kelola.store', $cooperative), [])
            ->assertSessionHasErrors(['period_year', 'period']);
    }

    public function test_admin_can_update_governance_record(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $record = GovernanceRecord::factory()->forPeriod(2024)->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->put(route('admin.koperasi.tata-kelola.update', [$cooperative, $record]), [
                'meeting_count' => 7,
                'status' => 'final',
            ])
            ->assertRedirect(route('admin.koperasi.tata-kelola.index', $cooperative));

        $this->assertDatabaseHas('governance_records', [
            'id' => $record->id,
            'meeting_count' => 7,
            'status' => 'final',
        ]);
    }

    public function test_admin_can_archive_governance_record(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $record = GovernanceRecord::factory()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->delete(route('admin.koperasi.tata-kelola.destroy', [$cooperative, $record]))
            ->assertRedirect(route('admin.koperasi.tata-kelola.index', $cooperative));

        $this->assertSoftDeleted('governance_records', ['id' => $record->id]);
    }

    public function test_cross_cooperative_scoped_binding_returns_404(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $recordB = GovernanceRecord::factory()->create(['cooperative_id' => $cooperativeB->id]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.tata-kelola.show', [$cooperativeA, $recordB]))
            ->assertNotFound();
    }

    public function test_pengelola_can_view_own_governance(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $record = GovernanceRecord::factory()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($pengelola)->get(route('pengelola.tata-kelola.index'))->assertOk();
        $this->actingAs($pengelola)->get(route('pengelola.tata-kelola.show', $record))->assertOk();
    }

    public function test_pengelola_cannot_view_other_cooperative_governance(): void
    {
        $cooperative = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $foreign = GovernanceRecord::factory()->create(['cooperative_id' => $other->id]);

        $this->actingAs($pengelola)
            ->get(route('pengelola.tata-kelola.show', $foreign))
            ->assertForbidden();
    }

    public function test_pengelola_cannot_write_governance(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)
            ->post(route('admin.koperasi.tata-kelola.store', $cooperative), [
                'period_year' => 2024,
                'period' => 'annual',
            ])
            ->assertForbidden();
    }

    public function test_unlinked_pengelola_index_is_forbidden(): void
    {
        $pengelola = User::factory()->pengelola(null)->create();

        $this->actingAs($pengelola)->get(route('pengelola.tata-kelola.index'))->assertForbidden();
    }

    public function test_admin_can_restore_archived_governance_record(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $record = GovernanceRecord::factory()->create(['cooperative_id' => $cooperative->id]);
        $record->delete();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.tata-kelola.restore', [$cooperative, $record]))
            ->assertRedirect(route('admin.koperasi.tata-kelola.index', $cooperative));

        $this->assertNotSoftDeleted('governance_records', ['id' => $record->id]);
    }

    public function test_restore_is_forbidden_for_pengelola_and_guest(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $record = GovernanceRecord::factory()->create(['cooperative_id' => $cooperative->id]);
        $record->delete();

        $this->post(route('admin.koperasi.tata-kelola.restore', [$cooperative, $record]))
            ->assertRedirect(route('login'));

        $this->actingAs($pengelola)
            ->post(route('admin.koperasi.tata-kelola.restore', [$cooperative, $record]))
            ->assertForbidden();

        $this->assertSoftDeleted('governance_records', ['id' => $record->id]);
    }

    public function test_restore_cross_cooperative_returns_404(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $recordB = GovernanceRecord::factory()->create(['cooperative_id' => $cooperativeB->id]);
        $recordB->delete();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.tata-kelola.restore', [$cooperativeA, $recordB]))
            ->assertNotFound();

        $this->assertSoftDeleted('governance_records', ['id' => $recordB->id]);
    }

    public function test_archived_period_stays_unique_until_restored(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $record = GovernanceRecord::factory()->forPeriod(2024)->create(['cooperative_id' => $cooperative->id]);
        $record->delete();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.tata-kelola.create', $cooperative))
            ->post(route('admin.koperasi.tata-kelola.store', $cooperative), [
                'period_year' => 2024,
                'period' => 'annual',
            ])
            ->assertSessionHasErrors('period_year');

        $this->assertSame(1, GovernanceRecord::withTrashed()->count());

        $this->actingAs($admin)
            ->post(route('admin.koperasi.tata-kelola.restore', [$cooperative, $record]))
            ->assertRedirect(route('admin.koperasi.tata-kelola.index', $cooperative));

        $this->assertSame(1, GovernanceRecord::withTrashed()->count());
        $this->assertNotSoftDeleted('governance_records', ['id' => $record->id]);
    }

    public function test_archived_record_is_not_accessible_via_normal_endpoints(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $record = GovernanceRecord::factory()->create(['cooperative_id' => $cooperative->id]);
        $record->delete();

        $this->actingAs($admin)
            ->get(route('admin.koperasi.tata-kelola.show', [$cooperative, $record]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.koperasi.tata-kelola.edit', [$cooperative, $record]))
            ->assertNotFound();
    }

    public function test_archived_record_is_not_visible_to_pengelola(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $record = GovernanceRecord::factory()->create(['cooperative_id' => $cooperative->id]);
        $record->delete();

        $this->actingAs($pengelola)
            ->get(route('pengelola.tata-kelola.show', $record))
            ->assertNotFound();
    }

    public function test_complete_checklist_is_marked_patuh(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.tata-kelola.store', $cooperative), [
                'period_year' => 2025,
                'period' => 'annual',
                'meeting_count' => 2,
                'rat_on_time' => '1',
                'financial_report_available' => '1',
                'basic_documents_complete' => '1',
                'status' => 'final',
            ])
            ->assertRedirect(route('admin.koperasi.tata-kelola.index', $cooperative));

        $record = GovernanceRecord::query()->where('cooperative_id', $cooperative->id)->firstOrFail();

        $this->assertSame('patuh', $record->compliance_status);
        $this->assertSame('Patuh', $record->complianceStatusLabel());
    }

    public function test_deviation_requires_notes(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.tata-kelola.store', $cooperative), [
                'period_year' => 2025,
                'period' => 'annual',
                'meeting_count' => 1,
                'rat_on_time' => '1',
                'financial_report_available' => '0',
                'basic_documents_complete' => '1',
                'status' => 'final',
            ])
            ->assertSessionHasErrors('compliance_notes');

        $this->assertDatabaseCount('governance_records', 0);

        $this->actingAs($admin)
            ->post(route('admin.koperasi.tata-kelola.store', $cooperative), [
                'period_year' => 2025,
                'period' => 'annual',
                'meeting_count' => 1,
                'rat_on_time' => '1',
                'financial_report_available' => '0',
                'basic_documents_complete' => '1',
                'compliance_notes' => 'Laporan keuangan belum tersedia saat evaluasi.',
                'status' => 'final',
            ])
            ->assertRedirect(route('admin.koperasi.tata-kelola.index', $cooperative));

        $this->assertDatabaseHas('governance_records', [
            'cooperative_id' => $cooperative->id,
            'compliance_status' => 'deviasi',
        ]);
    }
}
