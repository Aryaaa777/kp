<?php

namespace Tests\Feature\Reporting;

use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Region;
use App\Models\Submission;
use App\Models\User;
use App\Services\Reporting\CooperativeFilters;
use App\Services\Reporting\DashboardStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Dashboard admin dinas (F6): statistik FR-020, antrian FR-021, izin kedaluwarsa FR-050.
 *
 * Semua angka harus berasal dari query nyata dan konsisten dengan daftar yang setara.
 * Widget peringatan izin ≤30 hari (FR-022) berada di luar cakupan F6 dan tidak boleh ada.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Peta bucket wilayah yang tahan urutan: key = `region_id` (string) atau
     * {@see CooperativeFilters::REGION_NONE} untuk koperasi tanpa wilayah.
     *
     * `regionCounts` kini berupa daftar bucket `{region_id, label, total}` (R-4), bukan peta
     * `nama => jumlah`, sehingga tes tidak boleh bergantung pada nama wilayah sebagai kunci.
     *
     * @return array<string, array{label: string, total: int}>
     */
    private function regionBucketMap(TestResponse $response): array
    {
        $map = [];

        foreach ($response->viewData('regionCounts') as $bucket) {
            $key = $bucket['region_id'] === null ? CooperativeFilters::REGION_NONE : (string) $bucket['region_id'];
            $map[$key] = ['label' => $bucket['label'], 'total' => $bucket['total']];
        }

        return $map;
    }

    public function test_card_totals_match_equivalent_listing_query(): void
    {
        Cooperative::factory()->count(3)->create(['status_verifikasi' => 'approved']);
        Cooperative::factory()->count(2)->create(['status_verifikasi' => 'draft']);

        $archived = Cooperative::factory()->create(['status_verifikasi' => 'archived']);
        $archived->delete();

        $admin = $this->admin();
        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        // Angka kartu = hasil query daftar yang setara (AC FR-020).
        $listing = $this->actingAs($admin)->get(route('admin.koperasi.index'))->assertOk();

        $this->assertSame(
            $listing->viewData('cooperatives')->total(),
            $dashboard->viewData('totalCooperatives'),
        );

        $this->assertSame(5, $dashboard->viewData('totalCooperatives'));
    }

    public function test_status_breakdown_matches_database_counts(): void
    {
        Cooperative::factory()->count(2)->create(['status_verifikasi' => 'approved']);
        Cooperative::factory()->create(['status_verifikasi' => 'submitted']);
        Cooperative::factory()->create(['status_verifikasi' => 'needs_revision']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('statusCounts', function (array $counts): bool {
            foreach (Cooperative::STATUSES as $status) {
                $expected = Cooperative::query()->where('status_verifikasi', $status)->count();

                if (($counts[$status] ?? 0) !== $expected) {
                    return false;
                }
            }

            return true;
        });
    }

    public function test_analysis_dimensions_are_exposed(): void
    {
        $bandung = Region::factory()->create(['name' => 'Kota Bandung']);
        Cooperative::factory()->create(['region_id' => $bandung->id, 'jenis_koperasi' => 'Konsumen']);
        Cooperative::factory()->create(['region_id' => null, 'jenis_koperasi' => 'Produsen']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();

        $this->assertEquals([
            (string) $bandung->id => ['label' => 'Kota Bandung', 'total' => 1],
            CooperativeFilters::REGION_NONE => ['label' => 'Belum ditetapkan', 'total' => 1],
        ], $this->regionBucketMap($response));
        $this->assertEquals(['Konsumen' => 1, 'Produsen' => 1], $response->viewData('jenisCounts'));

        $response->assertViewHas('unassignedRegionLabel', 'Belum ditetapkan');
    }

    public function test_village_level_regions_are_grouped_under_regency(): void
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

        Cooperative::factory()->create(['region_id' => $village->id]);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();

        $this->assertEquals(
            [(string) $regency->id => ['label' => 'Kota Bandung', 'total' => 1]],
            $this->regionBucketMap($response),
        );

        // Filter wilayah hanya menampilkan kabupaten/kota, bukan desa/kelurahan.
        $this->assertNotContains(
            $village->id,
            $response->viewData('regionOptions')->pluck('id')->all(),
        );
    }

    public function test_status_filter_limits_statistics_and_matches_equivalent_listing(): void
    {
        Cooperative::factory()->count(2)->create(['status_verifikasi' => 'approved']);
        Cooperative::factory()->count(3)->create(['status_verifikasi' => 'draft']);

        $admin = $this->admin();

        $dashboard = $this->actingAs($admin)
            ->get(route('admin.dashboard', ['status_verifikasi' => 'draft']))
            ->assertOk();

        $this->assertTrue($dashboard->viewData('hasFilters'));
        $this->assertSame(3, $dashboard->viewData('totalCooperatives'));
        $this->assertSame(['draft' => 3], $dashboard->viewData('statusCounts'));

        // FR-020 AC: angka kartu = hasil query daftar yang setara.
        $listing = $this->actingAs($admin)
            ->get(route('admin.koperasi.index', ['status_verifikasi' => 'draft']))
            ->assertOk();

        $this->assertSame($listing->viewData('cooperatives')->total(), $dashboard->viewData('totalCooperatives'));
    }

    public function test_region_filter_including_unassigned_matches_listing(): void
    {
        $bandung = Region::factory()->create(['name' => 'Kota Bandung']);
        $cimahi = Region::factory()->create(['name' => 'Kota Cimahi']);

        Cooperative::factory()->count(2)->create(['region_id' => $bandung->id]);
        Cooperative::factory()->create(['region_id' => $cimahi->id]);
        Cooperative::factory()->count(3)->create(['region_id' => null]);

        $admin = $this->admin();

        $bandungResponse = $this->actingAs($admin)
            ->get(route('admin.dashboard', ['region_id' => $bandung->id]))
            ->assertOk();

        $this->assertSame(2, $bandungResponse->viewData('totalCooperatives'));
        $this->assertEquals(
            [(string) $bandung->id => ['label' => 'Kota Bandung', 'total' => 2]],
            $this->regionBucketMap($bandungResponse),
        );

        $listing = $this->actingAs($admin)
            ->get(route('admin.koperasi.index', ['region_id' => $bandung->id]))
            ->assertOk();

        $this->assertSame($listing->viewData('cooperatives')->total(), $bandungResponse->viewData('totalCooperatives'));

        // Nilai `none` = koperasi tanpa wilayah (F6-4).
        $noneResponse = $this->actingAs($admin)
            ->get(route('admin.dashboard', ['region_id' => CooperativeFilters::REGION_NONE]))
            ->assertOk();

        $this->assertSame(3, $noneResponse->viewData('totalCooperatives'));
        $this->assertEquals(
            [CooperativeFilters::REGION_NONE => ['label' => 'Belum ditetapkan', 'total' => 3]],
            $this->regionBucketMap($noneResponse),
        );
    }

    /**
     * R-4: bucket wilayah dikelompokkan per `region_id` stabil, bukan per nama. Dua wilayah
     * bernama sama harus menjadi dua bucket terpisah, dan angka setiap bucket harus sama dengan
     * hasil daftar koperasi untuk `region_id` itu.
     */
    public function test_duplicate_region_names_stay_separate_and_match_listing(): void
    {
        // `regions.name` TIDAK unique (hanya `regions.code`), jadi nama sama sah secara skema.
        $first = Region::factory()->create(['name' => 'Kota Bandung', 'code' => 'KB-01']);
        $second = Region::factory()->create(['name' => 'Kota Bandung', 'code' => 'KB-02']);

        Cooperative::factory()->count(2)->create(['region_id' => $first->id]);
        Cooperative::factory()->count(3)->create(['region_id' => $second->id]);
        Cooperative::factory()->create(['region_id' => null]);

        $admin = $this->admin();
        $dashboard = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $buckets = $dashboard->viewData('regionCounts');

        // Tiga bucket terpisah: dua wilayah bernama sama + satu "Belum ditetapkan".
        $this->assertCount(3, $buckets);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id, null],
            array_map(fn (array $bucket): ?int => $bucket['region_id'], $buckets),
        );

        // Angka bucket = hasil daftar koperasi untuk `region_id` yang sama (FR-020 AC).
        foreach ($buckets as $bucket) {
            $regionParam = $bucket['region_id'] ?? CooperativeFilters::REGION_NONE;

            $listing = $this->actingAs($admin)
                ->get(route('admin.koperasi.index', ['region_id' => $regionParam]))
                ->assertOk();

            $this->assertSame(
                $bucket['total'],
                $listing->viewData('cooperatives')->total(),
                "Angka bucket '{$bucket['label']}' tidak cocok dengan daftar.",
            );
        }
    }

    /**
     * R-4: tautan bucket memakai `region_id`; bucket tanpa wilayah menautkan ke filter `none`.
     */
    public function test_region_bucket_links_use_stable_region_id(): void
    {
        $bandung = Region::factory()->create(['name' => 'Kota Bandung']);
        Cooperative::factory()->create(['region_id' => $bandung->id]);
        Cooperative::factory()->create(['region_id' => null]);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();

        $response->assertSee(route('admin.koperasi.index', ['region_id' => $bandung->id]), false);
        $response->assertSee(route('admin.koperasi.index', ['region_id' => CooperativeFilters::REGION_NONE]), false);
        $response->assertSee('Belum ditetapkan');
    }

    /**
     * R-4 (regresi): `leftJoin('regions')` tidak boleh menggandakan baris — jumlah seluruh bucket
     * wilayah harus sama dengan total koperasi, baik tanpa filter maupun saat filter aktif.
     */
    public function test_region_bucket_totals_sum_to_total_cooperatives(): void
    {
        $bandung = Region::factory()->create(['name' => 'Kota Bandung', 'code' => 'KB-01']);
        $duplicate = Region::factory()->create(['name' => 'Kota Bandung', 'code' => 'KB-02']);

        Cooperative::factory()->count(2)->create(['region_id' => $bandung->id, 'status_verifikasi' => 'approved']);
        Cooperative::factory()->count(3)->create(['region_id' => $duplicate->id, 'status_verifikasi' => 'approved']);
        Cooperative::factory()->count(3)->create(['region_id' => null, 'status_verifikasi' => 'draft']);

        $admin = $this->admin();

        $all = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(8, $all->viewData('totalCooperatives'));
        $this->assertSame(8, array_sum(array_column($all->viewData('regionCounts'), 'total')));

        $filtered = $this->actingAs($admin)
            ->get(route('admin.dashboard', ['status_verifikasi' => 'approved']))
            ->assertOk();

        $this->assertSame(5, $filtered->viewData('totalCooperatives'));

        $buckets = $filtered->viewData('regionCounts');
        $this->assertCount(2, $buckets); // bucket tanpa wilayah kosong → tidak muncul
        $this->assertSame(5, array_sum(array_column($buckets, 'total')));
    }

    /**
     * Regresi bentuk SQL untuk `ONLY_FULL_GROUP_BY`.
     *
     * SQLite tidak menegakkan `ONLY_FULL_GROUP_BY`, jadi tes ini tidak membuktikan
     * kompatibilitas MySQL/MariaDB. Yang diuji: query nyata memakai `GROUP BY k.id`,
     * kolom yang juga dipilih sebagai `wilayah_id`, bukan ekspresi `COALESCE(k.id, 0)`.
     * Verifikasi MariaDB dilakukan terpisah secara read-only.
     */
    public function test_region_aggregation_groups_by_selected_region_id_for_only_full_group_by(): void
    {
        $bandung = Region::factory()->create(['name' => 'Kota Bandung']);
        Cooperative::factory()->create(['region_id' => $bandung->id]);
        Cooperative::factory()->create(['region_id' => null]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $buckets = app(DashboardStatistics::class)->countByRegion();

            $sql = collect(DB::getQueryLog())
                ->pluck('query')
                ->first(fn (string $query): bool => str_contains(strtolower($query), 'group by'));
        } finally {
            DB::disableQueryLog();
        }

        $this->assertNotNull($sql);
        $this->assertMatchesRegularExpression(
            '/group by\s+["`]?k["`]?\s*\.\s*["`]?id["`]?/i',
            $sql,
            'GROUP BY harus memakai k.id yang juga dipilih sebagai wilayah_id.',
        );
        $this->assertStringNotContainsString('COALESCE(k.id', $sql);
        $this->assertCount(2, $buckets);
    }

    public function test_period_filter_uses_the_documented_provisional_year_expression(): void
    {
        $old = Cooperative::factory()->create();
        $old->forceFill(['created_at' => now()->subYears(2)])->save();

        Cooperative::factory()->count(2)->create();

        $admin = $this->admin();
        $year = (int) $old->created_at->year;

        $response = $this->actingAs($admin)
            ->get(route('admin.dashboard', ['tahun' => (string) $year]))
            ->assertOk();

        $this->assertSame(1, $response->viewData('totalCooperatives'));
        $this->assertEquals([$year => 1], $response->viewData('periodCounts'));

        // Tahun tanpa data → kosong (tidak mengarang bucket).
        $empty = $this->actingAs($admin)
            ->get(route('admin.dashboard', ['tahun' => '1999']))
            ->assertOk();

        $this->assertSame(0, $empty->viewData('totalCooperatives'));
    }

    public function test_combined_filters_are_applied_and_do_not_affect_the_queue(): void
    {
        $bandung = Region::factory()->create(['name' => 'Kota Bandung']);

        Cooperative::factory()->count(2)->create([
            'region_id' => $bandung->id,
            'status_verifikasi' => 'approved',
            'jenis_koperasi' => 'Konsumen',
        ]);
        Cooperative::factory()->create([
            'region_id' => $bandung->id,
            'status_verifikasi' => 'draft',
            'jenis_koperasi' => 'Konsumen',
        ]);

        Submission::factory()->submitted()->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.dashboard', [
                'region_id' => $bandung->id,
                'status_verifikasi' => 'approved',
                'jenis_koperasi' => 'Konsumen',
            ]))
            ->assertOk();

        $this->assertSame(2, $response->viewData('totalCooperatives'));
        $this->assertEquals(['Konsumen' => 2], $response->viewData('jenisCounts'));

        // Antrian (FR-021) & izin kedaluwarsa (FR-050) tidak terkena filter FR-020.
        $this->assertSame(1, $response->viewData('queueCount'));
    }

    public function test_invalid_filter_values_are_ignored(): void
    {
        Cooperative::factory()->count(2)->create();

        $response = $this->actingAs($this->admin())
            ->get(route('admin.dashboard', [
                'region_id' => '999999',
                'status_verifikasi' => 'bukan-status',
                'tahun' => 'abcd',
            ]))
            ->assertOk();

        $response->assertViewHas('hasFilters', false);
        $this->assertSame(2, $response->viewData('totalCooperatives'));
    }

    public function test_filter_controls_links_and_reset_are_rendered(): void
    {
        $bandung = Region::factory()->create(['name' => 'Kota Bandung']);
        $cooperative = Cooperative::factory()->create([
            'region_id' => $bandung->id,
            'jenis_koperasi' => 'Konsumen',
            'status_verifikasi' => 'approved',
        ]);
        $cooperative->forceFill(['created_at' => '2024-06-15 10:00:00'])->save();

        $admin = $this->admin();

        $filtered = $this->actingAs($admin)
            ->get(route('admin.dashboard', ['region_id' => $bandung->id]))
            ->assertOk();

        $filtered->assertSee('Wilayah');
        $filtered->assertSee('Tahun Periode');
        $filtered->assertSee('Reset');
        $filtered->assertSee(route('admin.koperasi.index', ['region_id' => $bandung->id]), false);

        // Tabel agregat menyediakan tautan ke daftar koperasi yang setara, dengan filter
        // wilayah aktif tetap dipertahankan agar angka tabel = hasil daftar.
        $filtered->assertSee(route('admin.koperasi.index', [
            'region_id' => $bandung->id,
            'jenis_koperasi' => 'Konsumen',
        ]));
        $filtered->assertSee(route('admin.koperasi.index', [
            'region_id' => $bandung->id,
            'status_verifikasi' => 'approved',
        ]));
        $filtered->assertSee(route('admin.koperasi.index', [
            'region_id' => $bandung->id,
            'tahun' => '2024',
        ]));

        // Tanpa filter → tidak ada tombol reset.
        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('>Reset<', false);
    }

    public function test_queue_lists_only_actionable_statuses(): void
    {
        $submitted = Submission::factory()->submitted()->create();
        $underReview = Submission::factory()->underReview()->create();
        Submission::factory()->create(['status' => 'draft']);
        Submission::factory()->approved()->create();
        Submission::factory()->create(['status' => 'needs_revision']);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('queueCount', 2);

        $this->assertEquals(
            ['submitted' => 1, 'under_review' => 1],
            $response->viewData('queueCounts'),
        );

        $queue = $response->viewData('queue');

        $this->assertInstanceOf(Collection::class, $queue);
        $this->assertEqualsCanonicalizing(
            [$submitted->id, $underReview->id],
            $queue->pluck('id')->all(),
        );

        // Urutan: yang paling lama menunggu (submitted) lebih dahulu.
        $this->assertSame('submitted', $queue->first()->status);
    }

    public function test_queue_excludes_submissions_belonging_to_archived_cooperatives(): void
    {
        $cooperative = Cooperative::factory()->create();
        $submission = Submission::factory()->submitted()->create([
            'cooperative_id' => $cooperative->id,
        ]);

        // Koperasi diarsipkan (soft delete) → pengajuan tidak lagi dapat ditindak.
        $cooperative->delete();

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('queueCount', 0);
        $this->assertSame([], $response->viewData('queueCounts'));
        $this->assertCount(0, $response->viewData('queue'));
        $response->assertDontSee(route('admin.pengajuan.show', $submission), false);
    }

    public function test_queue_actions_follow_the_transition_matrix(): void
    {
        $submitted = Submission::factory()->submitted()->create();
        $underReview = Submission::factory()->underReview()->create();

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();

        // `submitted` → `under_review` sah, jadi form "Mulai tinjau" boleh tampil.
        $response->assertSee(route('admin.pengajuan.start-review', $submitted), false);
        $response->assertSee('Mulai tinjau');

        // `under_review` TIDAK boleh punya aksi mulai-tinjau (transisi tidak sah).
        $response->assertDontSee(route('admin.pengajuan.start-review', $underReview), false);
        $response->assertSee(route('admin.pengajuan.show', $underReview), false);
        $response->assertSee('Tinjau');
    }

    public function test_expired_licenses_are_listed_and_not_yet_expired_are_not(): void
    {
        $cooperative = Cooperative::factory()->create();

        $expired = CooperativeLicense::factory()->past()->create(['cooperative_id' => $cooperative->id]);
        CooperativeLicense::factory()->create([
            'cooperative_id' => $cooperative->id,
            'tanggal_berlaku_sampai' => today()->addDays(10)->toDateString(),
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('expiredLicenseCount', 1);
        $response->assertViewHas('expiredLicenses', fn (Collection $licenses): bool => $licenses->pluck('id')->all() === [$expired->id]);

        // FR-022: izin yang berakhir dalam 30 hari ditampilkan terpisah dari yang sudah lewat.
        $response->assertViewHas('expiringLicenseCount', 1);
        $response->assertViewHas('expiringLicenses', fn (Collection $licenses): bool => $licenses->count() === 1
            && $licenses->first()->id !== $expired->id);
        $response->assertSee('Akan Kedaluwarsa');
        $response->assertSee('Dalam 30 hari');
    }

    public function test_licenses_of_archived_cooperatives_are_not_counted(): void
    {
        $cooperative = Cooperative::factory()->create();
        CooperativeLicense::factory()->past()->create(['cooperative_id' => $cooperative->id]);

        $cooperative->update(['status_verifikasi' => 'archived']);
        $cooperative->delete();

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('expiredLicenseCount', 0);
    }

    public function test_empty_dashboard_renders_informative_empty_state(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('totalCooperatives', 0)
            ->assertViewHas('queueCount', 0)
            ->assertViewHas('expiredLicenseCount', 0)
            ->assertSee('Belum ada data koperasi')
            ->assertSee('Tidak ada pengajuan menunggu')
            ->assertSee('Tidak ada izin kedaluwarsa');
    }

    public function test_guest_is_redirected_and_pengelola_is_forbidden(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)->get(route('admin.dashboard'))->assertForbidden();
    }
}
