<?php

namespace Tests\Feature\Reporting;

use App\Http\Controllers\Admin\CooperativeController;
use App\Models\Cooperative;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Filter wilayah pada daftar koperasi (F6, melengkapi FR-160) — tanpa halaman pencarian global baru.
 *
 * Filter lain (keyword, status, jenis, whitelist sort, pagination) harus tetap berfungsi dan
 * dapat dikombinasikan.
 */
class CooperativeRegionFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_region_filter_narrows_the_listing(): void
    {
        $bandung = Region::factory()->create(['name' => 'Kota Bandung']);
        $bogor = Region::factory()->create(['name' => 'Kota Bogor']);

        Cooperative::factory()->create(['nama' => 'KOP-BANDUNG', 'region_id' => $bandung->id]);
        Cooperative::factory()->create(['nama' => 'KOP-BOGOR', 'region_id' => $bogor->id]);
        Cooperative::factory()->create(['nama' => 'KOP-TANPA-WILAYAH', 'region_id' => null]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['region_id' => $bandung->id]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('cooperatives')->total());
        $response->assertSee('KOP-BANDUNG');
        $response->assertDontSee('KOP-BOGOR');
        $response->assertDontSee('KOP-TANPA-WILAYAH');
    }

    public function test_regency_filter_includes_cooperatives_assigned_to_villages(): void
    {
        $province = Region::factory()->create(['type' => 'province', 'parent_id' => null]);
        $regency = Region::factory()->create([
            'name' => 'Kabupaten Induk',
            'type' => 'regency',
            'parent_id' => $province->id,
        ]);
        $otherRegency = Region::factory()->create([
            'name' => 'Kabupaten Lain',
            'type' => 'regency',
            'parent_id' => $province->id,
        ]);
        $district = Region::factory()->create([
            'type' => 'district',
            'parent_id' => $regency->id,
        ]);
        $village = Region::factory()->create([
            'type' => 'village',
            'parent_id' => $district->id,
        ]);

        Cooperative::factory()->create(['nama' => 'KOP-DESA', 'region_id' => $village->id]);
        Cooperative::factory()->create(['nama' => 'KOP-LAIN', 'region_id' => $otherRegency->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['region_id' => $regency->id]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('cooperatives')->total());
        $response->assertSee('KOP-DESA');
        $response->assertDontSee('KOP-LAIN');
    }

    public function test_unassigned_option_lists_only_cooperatives_without_region(): void
    {
        $region = Region::factory()->create();

        Cooperative::factory()->create(['nama' => 'KOP-TANPA-WILAYAH', 'region_id' => null]);
        Cooperative::factory()->create(['nama' => 'KOP-BERWILAYAH', 'region_id' => $region->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['region_id' => CooperativeController::REGION_NONE]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('cooperatives')->total());
        $response->assertSee('KOP-TANPA-WILAYAH');
        $response->assertDontSee('KOP-BERWILAYAH');
        $response->assertSee('Belum ditetapkan');
        $this->assertSame(CooperativeController::REGION_NONE, $response->viewData('filters')['region_id']);
    }

    public function test_region_filter_combines_with_keyword_status_and_jenis(): void
    {
        $bandung = Region::factory()->create();
        $bogor = Region::factory()->create();

        $match = [
            'nama' => 'Koperasi Melati',
            'jenis_koperasi' => 'Konsumen',
            'status_verifikasi' => 'approved',
        ];

        Cooperative::factory()->create(array_merge($match, ['nomor_identitas' => 'KOP-1111', 'region_id' => $bandung->id]));
        Cooperative::factory()->create(array_merge($match, ['nomor_identitas' => 'KOP-2222', 'region_id' => $bogor->id]));
        Cooperative::factory()->create(array_merge($match, ['nomor_identitas' => 'KOP-3333', 'region_id' => $bandung->id, 'status_verifikasi' => 'draft']));
        Cooperative::factory()->create(array_merge($match, ['nomor_identitas' => 'KOP-4444', 'region_id' => $bandung->id, 'jenis_koperasi' => 'Produsen']));
        Cooperative::factory()->create(['nama' => 'Koperasi Lain', 'nomor_identitas' => 'KOP-5555', 'region_id' => $bandung->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', [
                'q' => 'Melati',
                'status_verifikasi' => 'approved',
                'jenis_koperasi' => 'Konsumen',
                'region_id' => $bandung->id,
            ]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('cooperatives')->total());
        $response->assertSee('Koperasi Melati');
    }

    public function test_invalid_region_filter_values_are_ignored(): void
    {
        Cooperative::factory()->count(2)->create();

        foreach (['bukan-angka', '999999', '1.5'] as $invalid) {
            $response = $this->actingAs($this->admin())
                ->get(route('admin.koperasi.index', ['region_id' => $invalid]))
                ->assertOk();

            $this->assertSame(2, $response->viewData('cooperatives')->total());
            $this->assertSame('', $response->viewData('filters')['region_id']);
        }
    }

    public function test_pagination_and_sort_whitelist_are_preserved_with_region_filter(): void
    {
        $region = Region::factory()->create();
        Cooperative::factory()->count(12)->create(['region_id' => $region->id]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', [
                'region_id' => $region->id,
                'sort' => 'bukan_kolom',
                'direction' => 'desc',
            ]))
            ->assertOk();

        $paginator = $response->viewData('cooperatives');

        $this->assertSame(12, $paginator->total());
        $this->assertSame(10, $paginator->count());

        // Whitelist pengurutan tetap berlaku (nilai tidak sah jatuh kembali ke `nama`).
        $this->assertSame('nama', $response->viewData('filters')['sort']);

        // Pagination mempertahankan filter yang aktif.
        $response->assertSee('region_id='.$region->id, false);
    }

    public function test_region_options_are_available_in_the_listing(): void
    {
        Region::factory()->create(['name' => 'Kota Sukabumi']);

        $response = $this->actingAs($this->admin())->get(route('admin.koperasi.index'))->assertOk();

        $this->assertSame(1, $response->viewData('regions')->count());
        $response->assertSee('Kota Sukabumi');
        $response->assertSee('Semua wilayah');
    }

    /**
     * S-2: filter `status_verifikasi` pada daftar koperasi memakai normalisasi bersama
     * (`CooperativeFilters`): nilai tidak valid **diabaikan**, bukan menghasilkan hasil kosong.
     */
    public function test_invalid_status_filter_values_are_ignored(): void
    {
        Cooperative::factory()->count(3)->create();

        foreach (['bukan-status', 'APPROVED', '999'] as $invalid) {
            $response = $this->actingAs($this->admin())
                ->get(route('admin.koperasi.index', ['status_verifikasi' => $invalid]))
                ->assertOk();

            $this->assertSame(3, $response->viewData('cooperatives')->total());
            $this->assertSame('', $response->viewData('filters')['status_verifikasi']);
        }
    }

    public function test_valid_status_filter_is_applied_and_preserved_in_pagination(): void
    {
        Cooperative::factory()->count(12)->create(['status_verifikasi' => 'approved']);
        Cooperative::factory()->count(2)->create(['status_verifikasi' => 'draft']);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['status_verifikasi' => 'approved']))
            ->assertOk();

        $this->assertSame(12, $response->viewData('cooperatives')->total());
        $this->assertSame(10, $response->viewData('cooperatives')->count());
        $this->assertSame('approved', $response->viewData('filters')['status_verifikasi']);

        // Query string filter status tetap terpelihara pada tautan pagination.
        $response->assertSee('status_verifikasi=approved', false);
    }

    public function test_status_filter_combines_with_region_and_jenis(): void
    {
        $bandung = Region::factory()->create();

        Cooperative::factory()->create([
            'nama' => 'KOP-COCOK',
            'status_verifikasi' => 'approved',
            'jenis_koperasi' => 'Konsumen',
            'region_id' => $bandung->id,
        ]);
        Cooperative::factory()->create([
            'nama' => 'KOP-STATUS-LAIN',
            'status_verifikasi' => 'draft',
            'jenis_koperasi' => 'Konsumen',
            'region_id' => $bandung->id,
        ]);
        Cooperative::factory()->create([
            'nama' => 'KOP-JENIS-LAIN',
            'status_verifikasi' => 'approved',
            'jenis_koperasi' => 'Produsen',
            'region_id' => $bandung->id,
        ]);
        Cooperative::factory()->create([
            'nama' => 'KOP-WILAYAH-LAIN',
            'status_verifikasi' => 'approved',
            'jenis_koperasi' => 'Konsumen',
            'region_id' => null,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', [
                'status_verifikasi' => 'approved',
                'jenis_koperasi' => 'Konsumen',
                'region_id' => $bandung->id,
            ]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('cooperatives')->total());
        $response->assertSee('KOP-COCOK');
        $response->assertDontSee('KOP-STATUS-LAIN');
        $response->assertDontSee('KOP-JENIS-LAIN');
        $response->assertDontSee('KOP-WILAYAH-LAIN');
    }

    private function createWithYear(string $year, array $attributes = []): Cooperative
    {
        $cooperative = Cooperative::factory()->create($attributes);
        $cooperative->forceFill(['created_at' => $year.'-06-15 10:00:00'])->save();

        return $cooperative->refresh();
    }

    public function test_period_filter_uses_created_at_year_and_combines_with_other_filters(): void
    {
        $this->createWithYear('2024', [
            'nama' => 'KOP-2024-APPROVED',
            'status_verifikasi' => 'approved',
            'jenis_koperasi' => 'Konsumen',
        ]);
        $this->createWithYear('2023', [
            'nama' => 'KOP-2023-APPROVED',
            'status_verifikasi' => 'approved',
            'jenis_koperasi' => 'Konsumen',
        ]);
        $this->createWithYear('2024', [
            'nama' => 'KOP-2024-DRAFT',
            'status_verifikasi' => 'draft',
            'jenis_koperasi' => 'Konsumen',
        ]);
        $this->createWithYear('2024', [
            'nama' => 'KOP-2024-PRODUSEN',
            'status_verifikasi' => 'approved',
            'jenis_koperasi' => 'Produsen',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', [
                'tahun' => '2024',
                'status_verifikasi' => 'approved',
                'jenis_koperasi' => 'Konsumen',
            ]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('cooperatives')->total());
        $this->assertSame('2024', $response->viewData('filters')['tahun']);
        $response->assertSee('KOP-2024-APPROVED');
        $response->assertDontSee('KOP-2023-APPROVED');
        $response->assertDontSee('KOP-2024-DRAFT');
        $response->assertDontSee('KOP-2024-PRODUSEN');

        // Opsi tahun dihitung dari semua status agar filter tidak "menghilang".
        $this->assertSame([2023, 2024], $response->viewData('yearOptions'));
    }

    public function test_invalid_period_filter_values_are_ignored(): void
    {
        Cooperative::factory()->count(2)->create();

        foreach (['20x4', '24', '99999', '2024-01'] as $invalid) {
            $response = $this->actingAs($this->admin())
                ->get(route('admin.koperasi.index', ['tahun' => $invalid]))
                ->assertOk();

            $this->assertSame(2, $response->viewData('cooperatives')->total());
            $this->assertSame('', $response->viewData('filters')['tahun']);
        }
    }

    public function test_empty_listing_with_active_filter_shows_filter_specific_empty_state(): void
    {
        Cooperative::factory()->create(['nama' => 'KOP-ADA']);

        $filtered = $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index', ['q' => 'TIDAK-ADA']))
            ->assertOk();

        $filtered->assertSee('Tidak ada data cocok filter');
        $filtered->assertSee('Reset filter');
        $filtered->assertDontSee('Belum ada data koperasi');

        // Tanpa filter, pesan "belum ada data" tetap dipakai.
        Cooperative::all()->each(fn (Cooperative $cooperative) => $cooperative->delete());

        $this->actingAs($this->admin())
            ->get(route('admin.koperasi.index'))
            ->assertOk()
            ->assertSee('Belum ada data koperasi');
    }
}
