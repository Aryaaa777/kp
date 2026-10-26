<?php

namespace App\Services\Reporting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Menyatukan wilayah koperasi ke dimensi kabupaten/kota.
 *
 * `cooperatives.region_id` dapat menunjuk desa/kelurahan (Q-012), kecamatan, atau
 * langsung kabupaten/kota (data lama). Dashboard dan laporan tetap disajikan per
 * kabupaten/kota agar angka tidak pecah menjadi ribuan desa.
 *
 * Hierarki yang dipakai:
 * - desa/kelurahan → kecamatan (`r.parent_id`) → kabupaten/kota (`d.parent_id`)
 * - kecamatan → kabupaten/kota (`r.parent_id`)
 * - kabupaten/kota → dirinya sendiri
 * - provinsi / tidak ada → NULL (bucket "Belum ditetapkan")
 *
 * SQL `CASE` + `LEFT JOIN` ini portabel untuk SQLite (tes) dan MySQL/MariaDB
 * (target produksi yang belum dikonfirmasi).
 */
final class RegencyGrouping
{
    /**
     * Tambahkan join `r` (wilayah koperasi), `d` (kecamatan), dan `k` (kabupaten/kota).
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public static function join(Builder $query): Builder
    {
        return $query
            ->leftJoin('regions as r', 'r.id', '=', 'cooperatives.region_id')
            ->leftJoin('regions as d', 'd.id', '=', 'r.parent_id')
            ->leftJoin('regions as k', 'k.id', '=', DB::raw(self::caseExpression()));
    }

    public static function caseExpression(): string
    {
        return "CASE
            WHEN r.type = 'village' THEN d.parent_id
            WHEN r.type = 'district' THEN r.parent_id
            WHEN r.type IN ('regency', 'city') THEN r.id
            ELSE NULL
        END";
    }

    /**
     * Ekspresi GROUP BY harus identik dengan kolom non-agregat yang dipilih.
     *
     * `k.id` mengelompokkan seluruh baris tanpa kabupaten/kota (NULL) ke satu bucket
     * "Belum ditetapkan". Jangan memakai `COALESCE(k.id, 0)` saat query juga memilih
     * `k.id`: MySQL/MariaDB dengan `ONLY_FULL_GROUP_BY` menolak ekspresi yang berbeda.
     */
    public static function groupExpression(): string
    {
        return 'k.id';
    }
}
