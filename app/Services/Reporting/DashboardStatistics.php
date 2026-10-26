<?php

namespace App\Services\Reporting;

use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregasi **read-only** untuk dashboard admin dinas (FR-020, FR-021) dan ringkasan
 * izin yang sudah kedaluwarsa (FR-050).
 *
 * Prinsip yang dipegang:
 * - Semua perhitungan memakai agregasi database (`COUNT`/`GROUP BY`), bukan mengambil
 *   seluruh baris lalu menghitung di PHP.
 * - Global scope `SoftDeletes` berlaku: koperasi terarsip tidak ikut dihitung/ditampilkan.
 * - **Statistik koperasi** mencakup seluruh status verifikasi (gambaran kondisi data).
 * - **Antrian pengajuan** (FR-021) memakai status operasional `submitted`/`under_review`
 *   dan **tidak** difilter `approved` — filter `approved` hanya berlaku untuk laporan (F6-2).
 * - Izin kedaluwarsa mengikuti FR-050: teridentifikasi dari **tanggal** (`tanggal_berlaku_sampai`),
 *   sejalan dengan `CooperativeLicense::isExpired()`.
 * - Izin mendekati kedaluwarsa (FR-022) memakai ambang `koperasi.license_expiring_days`
 *   (default 30 hari) dan tidak memasukkan izin yang sudah lewat.
 * - Periode = tahun `cooperatives.created_at` — definisi **[SEMENTARA]** F6-3.
 *
 * @see docs/DECISIONS.md §15 (keputusan F6)
 */
class DashboardStatistics
{
    /**
     * Label untuk koperasi tanpa wilayah (`region_id` NULL) — keputusan F6-4.
     */
    public const REGION_UNASSIGNED_LABEL = 'Belum ditetapkan';

    /**
     * ID wilayah yang boleh diakses (FR-012). `null` = tanpa pembatasan.
     *
     * @var list<int>|null
     */
    private ?array $regionIds = null;

    /**
     * Terapkan pembatasan wilayah pada seluruh agregasi (FR-012).
     *
     * @param  list<int>|null  $regionIds
     */
    public function scopeToRegions(?array $regionIds): self
    {
        $this->regionIds = $regionIds;

        return $this;
    }

    /**
     * Status pengajuan yang perlu ditindak admin dinas (FR-021, PRD §12.1).
     *
     * @var list<string>
     */
    public const QUEUE_STATUSES = ['submitted', 'under_review'];

    /**
     * Query dasar statistik koperasi: seluruh status verifikasi, dengan filter FR-020 opsional
     * (wilayah/periode/status/jenis) yang dinormalisasi lewat {@see CooperativeFilters}.
     * Global scope `SoftDeletes` berlaku (koperasi terarsip tidak dihitung).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Cooperative>
     */
    private function cooperativeQuery(array $filters = []): Builder
    {
        return $this->applyRegionScope(
            CooperativeFilters::apply(Cooperative::query(), $filters),
            'cooperatives.region_id',
        );
    }

    /**
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    private function applyRegionScope(Builder $query, string $column = 'region_id'): Builder
    {
        if ($this->regionIds === null) {
            return $query;
        }

        if ($this->regionIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $this->regionIds);
    }

    /**
     * @return Builder<Submission>
     */
    private function submissionQuery(): Builder
    {
        $query = Submission::query()->whereHas('cooperative');

        if ($this->regionIds === null) {
            return $query;
        }

        if ($this->regionIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'cooperative',
            fn (Builder $cooperative) => $cooperative->whereIn('region_id', $this->regionIds),
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function totalCooperatives(array $filters = []): int
    {
        return $this->cooperativeQuery($filters)->count();
    }

    /**
     * Jumlah koperasi per status verifikasi (semua status).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function countByStatus(array $filters = []): array
    {
        return $this->cooperativeQuery($filters)
            ->selectRaw('status_verifikasi, COUNT(*) as total')
            ->groupBy('status_verifikasi')
            ->get()
            ->mapWithKeys(fn (Cooperative $row): array => [(string) $row->status_verifikasi => (int) $row->total])
            ->all();
    }

    /**
     * Jumlah koperasi per jenis koperasi.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function countByJenis(array $filters = []): array
    {
        return $this->cooperativeQuery($filters)
            ->selectRaw('jenis_koperasi, COUNT(*) as total')
            ->groupBy('jenis_koperasi')
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(fn (Cooperative $row): array => [(string) $row->jenis_koperasi => (int) $row->total])
            ->all();
    }

    /**
     * Jumlah koperasi per wilayah sebagai **daftar bucket** (bukan peta `nama => jumlah`).
     *
     * Dikelompokkan berdasarkan **ID kabupaten/kota** (kunci stabil), **bukan** nama
     * wilayah: `regions.name` **tidak** unik (hanya `regions.code` yang unique, lihat migrasi
     * `2026_10_16_100001_create_regions_table.php`). `region_id` dapat menunjuk desa/
     * kelurahan, sehingga {@see RegencyGrouping} menaikkan hierarki ke kabupaten/kota;
     * tautan "Daftar" tetap konsisten dengan angkanya. Nama wilayah hanya dipakai sebagai
     * **label**.
     *
     * `region_id` NULL atau wilayah tanpa kabupaten/kota → bucket tersendiri berlabel
     * "Belum ditetapkan" (F6-4); pemanggil memetakan `region_id = null` ke filter
     * {@see CooperativeFilters::REGION_NONE}.
     *
     * Catatan portabilitas: label diambil dengan `MAX(k.name)` — agregat yang aman untuk
     * `ONLY_FULL_GROUP_BY` (MySQL/MariaDB) dan tersedia di MariaDB 10.4 (berbeda dari
     * `ANY_VALUE()` yang baru ada di 10.5+). `GROUP BY` memakai `k.id`, kolom yang sama
     * dengan `wilayah_id`; memakai ekspresi lain (mis. `COALESCE(k.id, 0)`) akan ditolak
     * MySQL/MariaDB saat `ONLY_FULL_GROUP_BY` aktif.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{region_id: int|null, label: string, total: int}>
     */
    public function countByRegion(array $filters = []): array
    {
        return RegencyGrouping::join($this->cooperativeQuery($filters))
            ->selectRaw(
                'k.id as wilayah_id, COALESCE(MAX(k.name), ?) as wilayah, COUNT(*) as total',
                [self::REGION_UNASSIGNED_LABEL],
            )
            ->groupBy(DB::raw(RegencyGrouping::groupExpression()))
            ->orderByDesc('total')
            ->orderBy('wilayah')
            ->get()
            ->map(fn (Cooperative $row): array => [
                'region_id' => $row->wilayah_id === null ? null : (int) $row->wilayah_id,
                'label' => (string) $row->wilayah,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    /**
     * Jumlah koperasi per tahun pendaftaran (periode **SEMENTARA** F6-3).
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, int> tahun => jumlah
     */
    public function countByPeriodYear(array $filters = []): array
    {
        $year = PeriodExpression::year('cooperatives.created_at');

        return $this->cooperativeQuery($filters)
            ->selectRaw("{$year} as tahun, COUNT(*) as total")
            ->groupByRaw($year)
            ->orderByRaw($year)
            ->get()
            ->mapWithKeys(fn (Cooperative $row): array => [(int) $row->tahun => (int) $row->total])
            ->all();
    }

    /**
     * Tahun pendaftaran yang tersedia (opsi filter periode). Dihitung dari **semua** status
     * agar pilihan filter tidak mengecil saat filter lain diterapkan.
     *
     * @return list<int>
     */
    public function availableYears(): array
    {
        $year = PeriodExpression::year('cooperatives.created_at');

        return $this->applyRegionScope(Cooperative::query())
            ->selectRaw("{$year} as tahun")
            ->groupByRaw($year)
            ->orderByRaw($year)
            ->get()
            ->map(fn (Cooperative $row): int => (int) $row->tahun)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Jenis koperasi yang tersedia (opsi filter jenis).
     *
     * @return list<string>
     */
    public function availableJenis(): array
    {
        return $this->applyRegionScope(Cooperative::query())
            ->select('jenis_koperasi')
            ->distinct()
            ->orderBy('jenis_koperasi')
            ->pluck('jenis_koperasi')
            ->map(fn (mixed $jenis): string => (string) $jenis)
            ->values()
            ->all();
    }

    /**
     * Jumlah pengajuan yang perlu ditindak (semua koperasi — kewenangan dinas).
     */
    public function queueCount(): int
    {
        return $this->submissionQuery()
            ->whereIn('status', self::QUEUE_STATUSES)
            ->count();
    }

    /**
     * @return array<string, int>
     */
    public function queueCounts(): array
    {
        return $this->submissionQuery()
            ->whereIn('status', self::QUEUE_STATUSES)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (Submission $row): array => [(string) $row->status => (int) $row->total])
            ->all();
    }

    /**
     * Antrian ringkas (terlama menunggu lebih dahulu), maksimal `$limit` baris.
     *
     * @return Collection<int, Submission>
     */
    public function queue(int $limit = 5): Collection
    {
        return $this->submissionQuery()
            ->with('cooperative')
            ->whereIn('status', self::QUEUE_STATUSES)
            ->orderByRaw("CASE status WHEN 'submitted' THEN 0 ELSE 1 END")
            ->orderBy('updated_at')
            ->limit($limit)
            ->get();
    }

    public function expiredLicenseCount(): int
    {
        return $this->expiredLicenseQuery()->count();
    }

    /**
     * Jumlah izin yang akan kedaluwarsa dalam ambang hari (FR-022).
     */
    public function expiringLicenseCount(): int
    {
        return $this->expiringLicenseQuery()->count();
    }

    /**
     * Daftar izin yang akan kedaluwarsa (FR-022).
     *
     * @return Collection<int, CooperativeLicense>
     */
    public function expiringLicenses(int $limit = 10): Collection
    {
        return $this->expiringLicenseQuery()
            ->with('cooperative')
            ->orderBy('tanggal_berlaku_sampai')
            ->limit($limit)
            ->get();
    }

    /**
     * Daftar izin yang sudah kedaluwarsa (FR-050).
     *
     * @return Collection<int, CooperativeLicense>
     */
    public function expiredLicenses(int $limit = 10): Collection
    {
        return $this->expiredLicenseQuery()
            ->with('cooperative')
            ->orderBy('tanggal_berlaku_sampai')
            ->limit($limit)
            ->get();
    }

    /**
     * Query dasar izin kedaluwarsa: tanggal berlaku sudah lewat + koperasi masih aktif
     * (koperasi terarsip tidak ditampilkan, FR-043).
     *
     * @return Builder<CooperativeLicense>
     */
    private function expiredLicenseQuery(): Builder
    {
        return $this->licenseQuery()->expiredByDate();
    }

    /**
     * Query dasar izin dengan pembatasan wilayah admin (FR-012).
     *
     * @return Builder<CooperativeLicense>
     */
    private function licenseQuery(): Builder
    {
        $query = CooperativeLicense::query()->whereHas('cooperative');

        if ($this->regionIds === null) {
            return $query;
        }

        if ($this->regionIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'cooperative',
            fn (Builder $cooperative) => $cooperative->whereIn('region_id', $this->regionIds),
        );
    }

    /**
     * @return Builder<CooperativeLicense>
     */
    private function expiringLicenseQuery(): Builder
    {
        $days = (int) config('koperasi.license_expiring_days', 30);

        return $this->licenseQuery()->expiringByDate($days);
    }
}
