<?php

namespace App\Services\Reporting;

use App\Models\Cooperative;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;

/**
 * Normalisasi & penerapan **filter koperasi** yang dipakai bersama (FR-020 dashboard,
 * FR-160 daftar koperasi, FR-170 laporan).
 *
 * Tujuan: satu sumber kebenaran untuk (a) memvalidasi parameter filter dari query string dan
 * (b) menerapkan filter ke query koperasi, sehingga angka pada dashboard/laporan dan isi
 * daftar koperasi selalu konsisten (FR-020 AC: "angka kartu sama dengan hasil query daftar").
 *
 * Konvensi filter aplikasi: nilai yang **tidak valid diabaikan** (dianggap tidak memfilter),
 * bukan menimbulkan error. Nilai `region_id = none` berarti "koperasi tanpa wilayah" (F6-4).
 * Periode = tahun `cooperatives.created_at` — definisi **[SEMENTARA]** F6-3 sampai dinas
 * menetapkan definisi resmi (Q-010).
 *
 * @see docs/DECISIONS.md §17.3 (D-3)
 */
final class CooperativeFilters
{
    /**
     * Nilai filter wilayah untuk koperasi tanpa wilayah (`region_id` NULL).
     */
    public const REGION_NONE = 'none';

    /**
     * Label kategori wilayah kosong (F6-4).
     */
    public const REGION_UNASSIGNED_LABEL = 'Belum ditetapkan';

    /**
     * Normalisasi & validasi parameter filter. Nilai tidak valid diabaikan (konvensi aplikasi).
     *
     * @param  array<string, mixed>  $input
     * @return array{region_id: string, jenis_koperasi: string, status_verifikasi: string, tahun: string}
     */
    public static function normalize(array $input): array
    {
        $region = trim((string) ($input['region_id'] ?? ''));

        if ($region !== '' && $region !== self::REGION_NONE
            && (! ctype_digit($region) || ! Region::query()->whereKey((int) $region)->exists())) {
            $region = '';
        }

        $status = self::normalizeStatus($input['status_verifikasi'] ?? '');

        $tahun = trim((string) ($input['tahun'] ?? ''));

        if ($tahun !== '' && (! ctype_digit($tahun) || strlen($tahun) !== 4)) {
            $tahun = '';
        }

        return [
            'region_id' => $region,
            'jenis_koperasi' => trim((string) ($input['jenis_koperasi'] ?? '')),
            'status_verifikasi' => $status,
            'tahun' => $tahun,
        ];
    }

    /**
     * Normalisasi satu nilai `status_verifikasi` (FR-160). Nilai di luar daftar status aplikasi
     * **diabaikan** (`''`) — konvensi yang sama dengan `normalize()`. Dipakai bersama oleh
     * dashboard/laporan dan daftar koperasi agar perilaku filter konsisten (S-1/S-2 audit).
     */
    public static function normalizeStatus(mixed $value): string
    {
        $status = trim((string) $value);

        return in_array($status, Cooperative::STATUSES, true) ? $status : '';
    }

    /**
     * Terapkan filter yang sudah dinormalisasi ke query koperasi.
     *
     * Kolom diberi kualifikasi `cooperatives.` agar tetap aman bila query memakai
     * `leftJoin('regions', ...)` (mis. rekap per wilayah).
     *
     * @param  Builder<Cooperative>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Cooperative>
     */
    public static function apply(Builder $query, array $filters): Builder
    {
        $region = (string) ($filters['region_id'] ?? '');

        if ($region === self::REGION_NONE) {
            $query->whereNull('cooperatives.region_id');
        } elseif ($region !== '') {
            // Memilih kabupaten/kota juga mencakup kecamatan dan desa/kelurahan
            // di bawahnya (region_id kini dapat menunjuk desa/kelurahan).
            $query->whereIn('cooperatives.region_id', Region::descendantIdsOf((int) $region));
        }

        $jenis = (string) ($filters['jenis_koperasi'] ?? '');

        if ($jenis !== '') {
            $query->where('cooperatives.jenis_koperasi', $jenis);
        }

        $status = (string) ($filters['status_verifikasi'] ?? '');

        if ($status !== '') {
            $query->where('cooperatives.status_verifikasi', $status);
        }

        $tahun = (string) ($filters['tahun'] ?? '');

        if ($tahun !== '') {
            $query->whereRaw(PeriodExpression::year('cooperatives.created_at').' = ?', [(int) $tahun]);
        }

        return $query;
    }

    /**
     * Apakah ada filter yang aktif (dipakai untuk menampilkan ringkasan/tombol reset).
     *
     * @param  array<string, mixed>  $filters
     */
    public static function hasAny(array $filters): bool
    {
        foreach ($filters as $value) {
            if ((string) $value !== '') {
                return true;
            }
        }

        return false;
    }
}
