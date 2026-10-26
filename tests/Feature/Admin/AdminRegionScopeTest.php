<?php

namespace Tests\Feature\Admin;

use App\Models\Cooperative;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Pembatasan wilayah admin (FR-012, PROVISIONAL).
 *
 * Default nonaktif; saat diaktifkan, admin_wilayah hanya melihat koperasi pada
 * wilayahnya (termasuk turunan) dan tetap tidak dapat mengelola akun/pengaturan.
 */
class AdminRegionScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Region, 1: Region, 2: Region}
     */
    private function regionTree(): array
    {
        $province = Region::factory()->create(['type' => 'province', 'name' => 'Jawa Barat']);
        $regencyA = Region::factory()->create([
            'type' => 'regency',
            'name' => 'Kabupaten A',
            'parent_id' => $province->id,
        ]);
        $regencyB = Region::factory()->create([
            'type' => 'regency',
            'name' => 'Kabupaten B',
            'parent_id' => $province->id,
        ]);

        return [$province, $regencyA, $regencyB];
    }

    private function village(Region $regency, string $name): Region
    {
        $district = Region::factory()->create([
            'type' => 'district',
            'name' => $name.' District',
            'parent_id' => $regency->id,
        ]);

        return Region::factory()->create([
            'type' => 'village',
            'name' => $name,
            'parent_id' => $district->id,
        ]);
    }

    public function test_scope_is_disabled_by_default(): void
    {
        [, $regencyA, $regencyB] = $this->regionTree();
        $admin = User::factory()->adminWilayah($regencyA->id)->create();

        $inside = Cooperative::factory()->create(['nama' => 'Koperasi Dalam', 'region_id' => $this->village($regencyA, 'Desa A')->id]);
        $outside = Cooperative::factory()->create(['nama' => 'Koperasi Luar', 'region_id' => $this->village($regencyB, 'Desa B')->id]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.index'))
            ->assertOk()
            ->assertSee($inside->nama)
            ->assertSee($outside->nama);
    }

    public function test_enabled_scope_limits_list_and_direct_access(): void
    {
        config()->set('koperasi.admin_region_scope.enabled', true);

        [, $regencyA, $regencyB] = $this->regionTree();
        $admin = User::factory()->adminWilayah($regencyA->id)->create();

        $inside = Cooperative::factory()->create(['nama' => 'Koperasi Dalam', 'region_id' => $this->village($regencyA, 'Desa A')->id]);
        $outside = Cooperative::factory()->create(['nama' => 'Koperasi Luar', 'region_id' => $this->village($regencyB, 'Desa B')->id]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.index'))
            ->assertOk()
            ->assertSee($inside->nama)
            ->assertDontSee($outside->nama);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.show', $outside))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.koperasi.show', $inside))
            ->assertOk();
    }

    public function test_admin_wilayah_cannot_create_cooperative_outside_region(): void
    {
        config()->set('koperasi.admin_region_scope.enabled', true);

        [$province, $regencyA, $regencyB] = $this->regionTree();
        $admin = User::factory()->adminWilayah($regencyA->id)->create();

        $districtB = Region::factory()->create([
            'type' => 'district',
            'name' => 'Kecamatan B',
            'parent_id' => $regencyB->id,
        ]);
        $outsideVillage = Region::factory()->create([
            'type' => 'village',
            'name' => 'Desa B',
            'parent_id' => $districtB->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.koperasi.store'), [
                'nama' => 'Koperasi Baru',
                'nomor_identitas' => 'KOP-WILAYAH-001',
                'jenis_koperasi' => 'Konsumen',
                'bentuk_koperasi' => 'Primer',
                'province_id' => $province->id,
                'regency_id' => $regencyB->id,
                'district_id' => $districtB->id,
                'region_id' => $outsideVillage->id,
                'status_verifikasi' => 'draft',
                'status_operasional' => 'aktif',
            ])
            ->assertSessionHasErrors('region_id');

        $this->assertDatabaseMissing('cooperatives', ['nomor_identitas' => 'KOP-WILAYAH-001']);
    }

    public function test_policy_also_denies_admin_wilayah_outside_region(): void
    {
        config()->set('koperasi.admin_region_scope.enabled', true);

        [, $regencyA, $regencyB] = $this->regionTree();
        $admin = User::factory()->adminWilayah($regencyA->id)->create();

        $inside = Cooperative::factory()->create(['region_id' => $this->village($regencyA, 'Desa A')->id]);
        $outside = Cooperative::factory()->create(['region_id' => $this->village($regencyB, 'Desa B')->id]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $inside));
        $this->assertFalse(Gate::forUser($admin)->allows('view', $outside));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $outside));
    }

    public function test_admin_dinas_is_not_restricted_when_scope_enabled(): void
    {
        config()->set('koperasi.admin_region_scope.enabled', true);

        [, $regencyA, $regencyB] = $this->regionTree();
        $admin = User::factory()->admin()->create();
        $outside = Cooperative::factory()->create(['region_id' => $this->village($regencyB, 'Desa B')->id]);

        $this->actingAs($admin)
            ->get(route('admin.koperasi.show', $outside))
            ->assertOk();
    }
}
