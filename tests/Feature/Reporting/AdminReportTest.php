<?php

namespace Tests\Feature\Reporting;

use App\Models\Cooperative;
use App\Models\Region;
use App\Models\User;
use App\Services\Reporting\CooperativeReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Laporan & rekapitulasi koperasi (F6, FR-170) dengan AC-REP-01.
 *
 * Aturan inti: **hanya** koperasi `approved` yang boleh masuk rekap; semua status lain
 * (dan record yang di-soft-delete) harus nol. Laporan bersifat read-only.
 */
class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function createWithYear(string $year, array $attributes = []): Cooperative
    {
        $cooperative = Cooperative::factory()->create($attributes);
        $cooperative->forceFill(['created_at' => $year.'-06-15 10:00:00'])->save();

        return $cooperative->refresh();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function report(array $query = []): TestResponse
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.laporan.index', $query))
            ->assertOk();
    }

    public function test_report_counts_only_approved_cooperatives(): void
    {
        Cooperative::factory()->count(3)->create(['status_verifikasi' => 'approved']);
        Cooperative::factory()->count(4)->create(['status_verifikasi' => 'draft']);

        $response = $this->report();

        $response->assertViewHas('total', 3);
        $response->assertViewHas('verifiedStatus', CooperativeReport::STATUS_VERIFIED);

        // Rekap per dimensi konsisten dengan total (AC-REP-01).
        $this->assertSame(3, $response->viewData('byRegion')->sum('total'));
        $this->assertSame(3, $response->viewData('byJenis')->sum('total'));
        $this->assertSame(3, (int) $response->viewData('byPeriod')->sum('total'));
    }

    public function test_every_non_approved_status_is_excluded(): void
    {
        Cooperative::factory()->count(2)->create(['status_verifikasi' => 'approved']);

        $excluded = array_values(array_diff(Cooperative::STATUSES, ['approved']));

        foreach ($excluded as $status) {
            Cooperative::factory()->create(['status_verifikasi' => $status]);
        }

        $this->report()->assertViewHas('total', 2);

        // Filter status selain `approved` tidak boleh memunculkan data apa pun.
        foreach ($excluded as $status) {
            $this->report(['status_verifikasi' => $status])->assertViewHas('total', 0);
        }
    }

    public function test_soft_deleted_cooperative_is_excluded(): void
    {
        Cooperative::factory()->create(['status_verifikasi' => 'approved']);

        $deleted = Cooperative::factory()->create(['status_verifikasi' => 'approved']);
        $deleted->delete();

        $this->report()->assertViewHas('total', 1);
    }

    public function test_region_filter_and_unassigned_bucket(): void
    {
        $bandung = Region::factory()->create(['name' => 'Kota Bandung']);
        $bogor = Region::factory()->create(['name' => 'Kota Bogor']);

        Cooperative::factory()->create(['status_verifikasi' => 'approved', 'region_id' => $bandung->id]);
        Cooperative::factory()->create(['status_verifikasi' => 'approved', 'region_id' => $bogor->id]);
        Cooperative::factory()->count(2)->create(['status_verifikasi' => 'approved', 'region_id' => null]);

        $this->report()->assertViewHas('total', 4);

        $this->report(['region_id' => $bandung->id])->assertViewHas('total', 1);
        $this->report(['region_id' => CooperativeReport::REGION_NONE])->assertViewHas('total', 2);

        $byRegion = $this->report()->viewData('byRegion');

        $this->assertEqualsCanonicalizing(
            ['Kota Bandung', 'Kota Bogor', CooperativeReport::REGION_UNASSIGNED_LABEL],
            $byRegion->pluck('wilayah')->all(),
        );

        $rows = $byRegion->pluck('total', 'wilayah')->all();

        $this->assertSame(2, $rows[CooperativeReport::REGION_UNASSIGNED_LABEL]);
        $this->assertSame(1, $rows['Kota Bandung']);
    }

    /**
     * R-4: rekap per wilayah pada laporan juga dikelompokkan per `region_id` stabil, sehingga dua
     * wilayah bernama sama menjadi dua baris terpisah (bukan digabung oleh nama wilayah).
     */
    public function test_duplicate_region_names_are_not_merged_in_the_report(): void
    {
        $first = Region::factory()->create(['name' => 'Kota Bandung', 'code' => 'KB-01']);
        $second = Region::factory()->create(['name' => 'Kota Bandung', 'code' => 'KB-02']);

        Cooperative::factory()->count(2)->create(['status_verifikasi' => 'approved', 'region_id' => $first->id]);
        Cooperative::factory()->count(3)->create(['status_verifikasi' => 'approved', 'region_id' => $second->id]);

        $byRegion = $this->report()->viewData('byRegion');

        $this->assertCount(2, $byRegion);
        $this->assertSame(['Kota Bandung', 'Kota Bandung'], $byRegion->pluck('wilayah')->all());
        $this->assertEqualsCanonicalizing(
            [2, 3],
            $byRegion->pluck('total')->map(fn ($total): int => (int) $total)->all(),
        );

        // Tidak ada koperasi yang hilang atau tergabung salah ke wilayah lain.
        $this->assertSame(5, (int) $byRegion->sum('total'));
    }

    public function test_jenis_and_year_filters_work_individually_and_combined(): void
    {
        $this->createWithYear('2024', ['status_verifikasi' => 'approved', 'jenis_koperasi' => 'Konsumen']);
        $this->createWithYear('2024', ['status_verifikasi' => 'approved', 'jenis_koperasi' => 'Produsen']);
        $this->createWithYear('2023', ['status_verifikasi' => 'approved', 'jenis_koperasi' => 'Konsumen']);
        // Bukan approved → tidak pernah dihitung, walau tahun/jenis cocok.
        $this->createWithYear('2023', ['status_verifikasi' => 'draft', 'jenis_koperasi' => 'Konsumen']);

        $this->report()->assertViewHas('total', 3);
        $this->report(['jenis_koperasi' => 'Konsumen'])->assertViewHas('total', 2);
        $this->report(['tahun' => '2024'])->assertViewHas('total', 2);
        $this->report(['tahun' => '2023'])->assertViewHas('total', 1);

        $this->report(['jenis_koperasi' => 'Konsumen', 'tahun' => '2024'])->assertViewHas('total', 1);
        $this->report(['jenis_koperasi' => 'Produsen', 'tahun' => '2023'])->assertViewHas('total', 0);

        // Opsi periode hanya berasal dari data terverifikasi.
        $this->assertSame([2023, 2024], $this->report()->viewData('yearOptions'));
        $this->assertSame(['Konsumen', 'Produsen'], $this->report()->viewData('jenisOptions'));

        // Rekap per periode memakai tahun yang sama dan hanya menghitung data approved.
        $periods = $this->report()->viewData('byPeriod');
        $this->assertSame([2023, 2024], $periods->pluck('tahun')->map(fn ($year): int => (int) $year)->all());
        $this->assertSame([1, 2], $periods->pluck('total')->map(fn ($total): int => (int) $total)->all());
    }

    public function test_filters_can_be_combined_with_region_and_status(): void
    {
        $region = Region::factory()->create();
        $other = Region::factory()->create();

        Cooperative::factory()->create([
            'status_verifikasi' => 'approved',
            'region_id' => $region->id,
            'jenis_koperasi' => 'Konsumen',
        ]);
        Cooperative::factory()->create([
            'status_verifikasi' => 'approved',
            'region_id' => $other->id,
            'jenis_koperasi' => 'Konsumen',
        ]);

        $response = $this->report([
            'region_id' => $region->id,
            'jenis_koperasi' => 'Konsumen',
            'status_verifikasi' => 'approved',
        ]);

        $response->assertViewHas('total', 1);
    }

    public function test_invalid_filter_values_are_ignored_safely(): void
    {
        Cooperative::factory()->create(['status_verifikasi' => 'approved']);

        $response = $this->report([
            'region_id' => 'bukan-angka',
            'jenis_koperasi' => '',
            'status_verifikasi' => 'bogus',
            'tahun' => '20x4',
        ]);

        $response->assertViewHas('total', 1);

        $this->assertSame([
            'region_id' => '',
            'jenis_koperasi' => '',
            'status_verifikasi' => '',
            'tahun' => '',
        ], $response->viewData('filters'));
    }

    public function test_empty_report_shows_informative_empty_state(): void
    {
        Cooperative::factory()->create(['status_verifikasi' => 'draft']);

        $response = $this->report();

        $response->assertViewHas('total', 0);

        $this->assertCount(0, $response->viewData('byRegion'));
        $this->assertCount(0, $response->viewData('byJenis'));
        $this->assertCount(0, $response->viewData('byPeriod'));

        $response->assertSee('Tidak ada data');
    }

    public function test_report_groups_village_region_under_its_regency(): void
    {
        $province = Region::factory()->create(['type' => 'province', 'parent_id' => null]);
        $regency = Region::factory()->create([
            'name' => 'Kota Bandung',
            'type' => 'city',
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

        Cooperative::factory()->create([
            'status_verifikasi' => 'approved',
            'region_id' => $village->id,
        ]);

        $response = $this->report()->assertOk();

        $byRegion = $response->viewData('byRegion');

        $this->assertCount(1, $byRegion);
        $this->assertSame('Kota Bandung', $byRegion->first()->wilayah);
        $this->assertSame(1, (int) $byRegion->first()->total);
    }

    public function test_report_offers_csv_export(): void
    {
        Cooperative::factory()->create(['status_verifikasi' => 'approved']);

        $this->report()
            ->assertSee('Ekspor CSV')
            ->assertSee(route('admin.laporan.export'), false);
    }

    public function test_guest_is_redirected_and_pengelola_is_forbidden(): void
    {
        $this->get(route('admin.laporan.index'))->assertRedirect(route('login'));

        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)->get(route('admin.laporan.index'))->assertForbidden();
    }

    public function test_export_csv_contains_only_approved_cooperatives_and_is_audited(): void
    {
        $approved = Cooperative::factory()->create([
            'nama' => 'Koperasi Ekspor Disetujui',
            'status_verifikasi' => 'approved',
        ]);
        Cooperative::factory()->create([
            'nama' => 'Koperasi Ekspor Draft',
            'status_verifikasi' => 'draft',
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('admin.laporan.export'))
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Koperasi Ekspor Disetujui', $csv);
        $this->assertStringNotContainsString('Koperasi Ekspor Draft', $csv);
        $this->assertStringContainsString((string) $approved->nomor_identitas, $csv);

        $this->assertDatabaseHas('activity_logs', ['action' => 'export_report']);
    }
}
