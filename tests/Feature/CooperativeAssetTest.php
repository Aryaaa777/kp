<?php

namespace Tests\Feature;

use App\Models\Cooperative;
use App\Models\CooperativeAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test modul aset koperasi (FR-100/101).
 *
 * PROVISIONAL — snapshot tahunan; quantity/acquisition_date/condition adalah field tambahan.
 * Lihat DECISIONS §10.
 */
class CooperativeAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->get(route('admin.koperasi.aset.index', $cooperative))->assertRedirect(route('login'));
        $this->get(route('pengelola.aset.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_view_asset_index(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        CooperativeAsset::factory()->forPeriod(2024)->create([
            'cooperative_id' => $cooperative->id,
            'asset_type' => 'Tanah',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.aset.index', $cooperative))
            ->assertOk()
            ->assertSee('Tanah');
    }

    public function test_admin_can_create_asset(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.koperasi.aset.store', $cooperative), [
                'period_year' => 2024,
                'asset_type' => 'Bangunan',
                'asset_name' => 'Gedung kantor',
                'quantity' => 1,
                'value' => 750000000,
                'acquisition_date' => '2019-06-01',
                'condition' => 'baik',
                'description' => 'Data uji.',
            ])
            ->assertRedirect(route('admin.koperasi.aset.index', $cooperative));

        $this->assertDatabaseHas('cooperative_assets', [
            'cooperative_id' => $cooperative->id,
            'period_year' => 2024,
            'asset_type' => 'Bangunan',
            'recorded_by' => $admin->id,
        ]);
    }

    public function test_cooperative_id_from_input_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();

        $this->actingAs($admin)->post(route('admin.koperasi.aset.store', $cooperative), [
            'cooperative_id' => $other->id,
            'period_year' => 2024,
            'asset_type' => 'Tanah',
            'value' => 1000,
        ]);

        $this->assertDatabaseHas('cooperative_assets', ['cooperative_id' => $cooperative->id]);
        $this->assertDatabaseMissing('cooperative_assets', ['cooperative_id' => $other->id]);
    }

    public function test_store_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.aset.create', $cooperative))
            ->post(route('admin.koperasi.aset.store', $cooperative), [])
            ->assertSessionHasErrors(['period_year', 'asset_type', 'value']);
    }

    public function test_negative_value_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.aset.create', $cooperative))
            ->post(route('admin.koperasi.aset.store', $cooperative), [
                'period_year' => 2024,
                'asset_type' => 'Tanah',
                'value' => -5,
            ])
            ->assertSessionHasErrors('value');
    }

    public function test_invalid_condition_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.koperasi.aset.create', $cooperative))
            ->post(route('admin.koperasi.aset.store', $cooperative), [
                'period_year' => 2024,
                'asset_type' => 'Tanah',
                'value' => 1000,
                'condition' => 'kondisi-tidak-dikenal',
            ])
            ->assertSessionHasErrors('condition');
    }

    public function test_history_across_periods_does_not_overwrite_previous(): void
    {
        // AC-PER-02: periode berbeda tidak menimpa nilai sebelumnya.
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();

        CooperativeAsset::factory()->forPeriod(2023)->create([
            'cooperative_id' => $cooperative->id,
            'asset_type' => 'Tanah',
            'value' => 1000000,
        ]);

        $this->actingAs($admin)->post(route('admin.koperasi.aset.store', $cooperative), [
            'period_year' => 2024,
            'asset_type' => 'Tanah',
            'value' => 2000000,
        ]);

        $this->assertSame(2, CooperativeAsset::where('cooperative_id', $cooperative->id)->count());
        $this->assertDatabaseHas('cooperative_assets', ['period_year' => 2023, 'value' => 1000000]);
        $this->assertDatabaseHas('cooperative_assets', ['period_year' => 2024, 'value' => 2000000]);
    }

    public function test_admin_can_update_asset(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $asset = CooperativeAsset::factory()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->put(route('admin.koperasi.aset.update', [$cooperative, $asset]), [
                'value' => 123456789.00,
                'condition' => 'rusak_berat',
            ])
            ->assertRedirect(route('admin.koperasi.aset.index', $cooperative));

        $this->assertDatabaseHas('cooperative_assets', [
            'id' => $asset->id,
            'value' => 123456789.00,
            'condition' => 'rusak_berat',
        ]);
    }

    public function test_admin_can_archive_asset(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperative = Cooperative::factory()->create();
        $asset = CooperativeAsset::factory()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($admin)
            ->delete(route('admin.koperasi.aset.destroy', [$cooperative, $asset]))
            ->assertRedirect(route('admin.koperasi.aset.index', $cooperative));

        $this->assertSoftDeleted('cooperative_assets', ['id' => $asset->id]);
    }

    public function test_cross_cooperative_scoped_binding_returns_404(): void
    {
        $admin = User::factory()->admin()->create();
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $assetB = CooperativeAsset::factory()->create(['cooperative_id' => $cooperativeB->id]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.aset.show', [$cooperativeA, $assetB]))
            ->assertNotFound();
    }

    public function test_pengelola_can_view_own_assets(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $asset = CooperativeAsset::factory()->create(['cooperative_id' => $cooperative->id]);

        $this->actingAs($pengelola)->get(route('pengelola.aset.index'))->assertOk();
        $this->actingAs($pengelola)->get(route('pengelola.aset.show', $asset))->assertOk();
    }

    public function test_pengelola_cannot_view_other_cooperative_assets(): void
    {
        $cooperative = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();
        $foreign = CooperativeAsset::factory()->create(['cooperative_id' => $other->id]);

        $this->actingAs($pengelola)
            ->get(route('pengelola.aset.show', $foreign))
            ->assertForbidden();
    }

    public function test_pengelola_cannot_write_assets(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)
            ->post(route('admin.koperasi.aset.store', $cooperative), [
                'period_year' => 2024,
                'asset_type' => 'Tanah',
                'value' => 1000,
            ])
            ->assertForbidden();
    }

    public function test_unlinked_pengelola_index_is_forbidden(): void
    {
        $pengelola = User::factory()->pengelola(null)->create();

        $this->actingAs($pengelola)->get(route('pengelola.aset.index'))->assertForbidden();
    }
}
