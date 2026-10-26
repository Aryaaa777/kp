<?php

namespace App\Services\Reporting;

use App\Models\Cooperative;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laporan/rekapitulasi koperasi (FR-170) — **read-only**.
 *
 * Aturan yang mengikat (keputusan F6-2, AC-REP-01):
 * **hanya** koperasi `status_verifikasi = 'approved'` yang dihitung. Status lain tidak
 * pernah masuk agregasi; karena itu memilih status selain `approved` pada filter
 * menghasilkan rekap kosong (perilaku yang diuji secara eksplisit).
 *
 * Dimensi rekap: wilayah, jenis koperasi, periode (tahun `created_at` — definisi
 * **[SEMENTARA]** F6-3). `region_id` NULL masuk kategori "Belum ditetapkan" (F6-4).
 * Ekspor CSV (FR-171) memakai `detailQuery()`; pembatasan wilayah admin (FR-012)
 * diterapkan lewat `scopeToRegions()`.
 *
 * @see docs/DECISIONS.md §15 (keputusan F6)
 */
class CooperativeReport
{
    /**
     * Satu-satunya status yang boleh masuk rekap laporan (AC-REP-01).
     */
    public const STATUS_VERIFIED = 'approved';

    /**
     * Nilai filter wilayah untuk koperasi tanpa wilayah (`region_id` NULL).
     *
     * @deprecated Gunakan {@see CooperativeFilters::REGION_NONE} — dipertahankan untuk kompatibilitas.
     */
    public const REGION_NONE = CooperativeFilters::REGION_NONE;

    /**
     * Label kategori wilayah kosong (F6-4).
     *
     * @deprecated Gunakan {@see CooperativeFilters::REGION_UNASSIGNED_LABEL}.
     */
    public const REGION_UNASSIGNED_LABEL = CooperativeFilters::REGION_UNASSIGNED_LABEL;

    /**
     * ID wilayah yang boleh diakses (FR-012). `null` = tanpa pembatasan.
     *
     * @var list<int>|null
     */
    private ?array $regionIds = null;

    /**
     * Terapkan pembatasan wilayah admin (FR-012).
     *
     * @param  list<int>|null  $regionIds
     */
    public function scopeToRegions(?array $regionIds): self
    {
        $this->regionIds = $regionIds;

        return $this;
    }

    /**
     * Normalisasi & validasi parameter filter. Nilai yang tidak valid **diabaikan**
     * (dianggap tidak memfilter) mengikuti konvensi filter aplikasi.
     *
     * @param  array<string, mixed>  $input
     * @return array{region_id: string, jenis_koperasi: string, status_verifikasi: string, tahun: string}
     */
    public function normalizeFilters(array $input): array
    {
        return CooperativeFilters::normalize($input);
    }

    /**
     * Query dasar laporan: **selalu** dibatasi ke koperasi terverifikasi (AC-REP-01).
     * Global scope `SoftDeletes` berlaku (koperasi terarsip tidak dihitung, FR-043).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Cooperative>
     */
    public function baseQuery(array $filters): Builder
    {
        $query = Cooperative::query()->where('status_verifikasi', self::STATUS_VERIFIED);

        return $this->applyRegionScope(CooperativeFilters::apply($query, $filters));
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
     * @param  array<string, mixed>  $filters
     */
    public function total(array $filters): int
    {
        return $this->baseQuery($filters)->count();
    }

    /**
     * Rekap per kabupaten/kota.
     *
     * `cooperatives.region_id` dapat menunjuk desa/kelurahan (Q-012), sehingga
     * pengelompokan menaikkan hierarki ke kabupaten/kota: desa → kecamatan →
     * kabupaten/kota. Data lama yang masih menunjuk kabupaten/kota tetap
     * dikelompokkan dengan benar; wilayah tanpa kabupaten/kota (mis. provinsi)
     * masuk kategori "Belum ditetapkan".
     *
     * Dikelompokkan berdasarkan **ID kabupaten/kota** (kunci stabil), **bukan** nama
     * wilayah — `regions.name` **tidak** unik. Nama wilayah hanya dipakai sebagai label.
     *
     * Catatan portabilitas: label diambil dengan `MAX(k.name)` agar aman terhadap
     * `ONLY_FULL_GROUP_BY` (dan bukan `ANY_VALUE()` yang baru ada di MariaDB 10.5+).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Cooperative>
     */
    public function byRegion(array $filters): Collection
    {
        // `region_id` dapat menunjuk desa/kelurahan. Rekap tetap pada dimensi
        // kabupaten/kota: naikkan wilayah desa → kecamatan → kabupaten/kota.
        return RegencyGrouping::join($this->baseQuery($filters))
            ->selectRaw('COALESCE(MAX(k.name), ?) as wilayah, COUNT(*) as total', [self::REGION_UNASSIGNED_LABEL])
            ->groupBy(DB::raw(RegencyGrouping::groupExpression()))
            ->orderByDesc('total')
            ->orderBy('wilayah')
            ->get();
    }

    /**
     * Rekap per jenis koperasi.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Cooperative>
     */
    public function byJenis(array $filters): Collection
    {
        return $this->baseQuery($filters)
            ->selectRaw('jenis_koperasi as jenis, COUNT(*) as total')
            ->groupBy('jenis_koperasi')
            ->orderByDesc('total')
            ->orderBy('jenis')
            ->get();
    }

    /**
     * Rekap per tahun pendaftaran (periode **SEMENTARA** F6-3).
     *
     * Hanya koperasi terverifikasi yang dihitung (AC-REP-01). Tahun memakai ekspresi
     * portabel {@see PeriodExpression} yang sama dengan filter dan dashboard.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Cooperative>
     */
    public function byPeriod(array $filters): Collection
    {
        $year = PeriodExpression::year('cooperatives.created_at');

        return $this->baseQuery($filters)
            ->selectRaw("{$year} as tahun, COUNT(*) as total")
            ->groupByRaw($year)
            ->orderByRaw($year)
            ->get();
    }

    /**
     * Tahun periode yang tersedia pada data terverifikasi (untuk opsi filter).
     *
     * @return list<int>
     */
    public function availableYears(): array
    {
        $year = PeriodExpression::year('cooperatives.created_at');

        return $this->applyRegionScope(
            Cooperative::query()->where('status_verifikasi', self::STATUS_VERIFIED),
        )
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
     * Jenis koperasi yang muncul pada data terverifikasi (untuk opsi filter).
     *
     * @return list<string>
     */
    public function availableJenis(): array
    {
        return $this->applyRegionScope(
            Cooperative::query()->where('status_verifikasi', self::STATUS_VERIFIED),
        )
            ->select('jenis_koperasi')
            ->distinct()
            ->orderBy('jenis_koperasi')
            ->pluck('jenis_koperasi')
            ->map(fn (mixed $jenis): string => (string) $jenis)
            ->values()
            ->all();
    }

    /**
     * Query detail untuk ekspor CSV (FR-171).
     *
     * Hanya field non-sensitif yang diekspor; filter dan aturan `approved`
     * tetap sama dengan laporan di layar (AC-REP-01).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Cooperative>
     */
    public function detailQuery(array $filters): Builder
    {
        return $this->baseQuery($filters)
            ->with('region.parent.parent.parent')
            ->select([
                'id',
                'nama',
                'nomor_identitas',
                'jenis_koperasi',
                'status_operasional',
                'region_id',
                'created_at',
            ])
            ->orderBy('nama')
            ->orderBy('id');
    }
}
