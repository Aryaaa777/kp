<?php

namespace App\Services\Reporting;

use Illuminate\Support\Facades\DB;

/**
 * Ekspresi SQL portabel untuk mengambil komponen **tahun** dari kolom timestamp.
 *
 * Diperlukan karena aplikasi diuji pada SQLite (in-memory) tetapi dijalankan pada
 * MariaDB/MySQL: `YEAR()` tidak tersedia di SQLite dan `strftime()` tidak tersedia di MySQL.
 * Semua pemakaian tetap berupa agregasi di sisi database (bukan perhitungan di PHP).
 *
 * Dipakai untuk definisi periode **sementara** F6-3 (tahun `cooperatives.created_at`).
 *
 * @see docs/DECISIONS.md §15 (F6-3)
 */
final class PeriodExpression
{
    public static function year(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
            default => "YEAR({$column})",
        };
    }
}
