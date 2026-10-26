<?php

namespace App\Console\Commands;

use App\Models\Cooperative;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit read-only pemetaan `cooperatives.region_id` ke referensi wilayah.
 *
 * Perintah ini TIDAK melakukan backfill dan TIDAK mengubah data. Ia hanya
 * melaporkan koperasi yang belum memiliki wilayah atau masih menunjuk wilayah
 * non-desa/kelurahan, sehingga keputusan pemetaan dapat diambil berdasarkan
 * bukti. Laporan tidak memuat nama, telepon, email, atau data pribadi lain.
 */
class AuditCooperativeRegions extends Command
{
    protected $signature = 'regions:audit-cooperatives
        {--json= : Simpan laporan ke berkas JSON}';

    protected $description = 'Melaporkan pemetaan region_id koperasi tanpa mengubah data';

    public function handle(): int
    {
        $total = Cooperative::query()->withTrashed()->count();
        $withRegion = Cooperative::query()->withTrashed()->whereNotNull('region_id')->count();
        $withoutRegion = $total - $withRegion;

        $byType = Cooperative::query()
            ->withTrashed()
            ->join('regions', 'regions.id', '=', 'cooperatives.region_id')
            ->select('regions.type', DB::raw('count(*) as total'))
            ->groupBy('regions.type')
            ->pluck('total', 'regions.type')
            ->all();

        $villageCount = (int) ($byType['village'] ?? 0);
        $nonVillageCount = $withRegion - $villageCount;

        $exceptions = Cooperative::query()
            ->withTrashed()
            ->leftJoin('regions', 'regions.id', '=', 'cooperatives.region_id')
            ->where(function ($query): void {
                $query->whereNull('cooperatives.region_id')
                    ->orWhereNull('regions.id')
                    ->orWhere('regions.type', '!=', 'village');
            })
            ->orderBy('cooperatives.id')
            ->get([
                'cooperatives.id as cooperative_id',
                'cooperatives.region_id',
                'regions.code as region_code',
                'regions.type as region_type',
            ])
            ->map(function ($row): array {
                $reason = match (true) {
                    $row->region_id === null => 'region_id_null',
                    $row->region_code === null => 'region_tidak_ditemukan',
                    default => 'region_bukan_desa_kelurahan',
                };

                return [
                    'cooperative_id' => (int) $row->cooperative_id,
                    'region_id' => $row->region_id === null ? null : (int) $row->region_id,
                    'region_code' => $row->region_code,
                    'region_type' => $row->region_type,
                    'reason' => $reason,
                ];
            })
            ->all();

        $report = [
            'generated_at' => now()->toIso8601String(),
            'totals' => [
                'cooperatives' => $total,
                'with_region' => $withRegion,
                'without_region' => $withoutRegion,
                'village_level' => $villageCount,
                'non_village_level' => $nonVillageCount,
            ],
            'by_region_type' => $byType,
            'exceptions' => $exceptions,
            'note' => 'Laporan hanya memuat ID koperasi dan kode/tipe wilayah; tanpa data pribadi.',
        ];

        $this->table(
            ['Metrik', 'Jumlah'],
            [
                ['Total koperasi', $total],
                ['Dengan wilayah', $withRegion],
                ['Tanpa wilayah (NULL)', $withoutRegion],
                ['Wilayah desa/kelurahan', $villageCount],
                ['Wilayah non-desa (perlu penanganan)', $nonVillageCount],
            ],
        );

        if ($exceptions !== []) {
            $this->warn('Koperasi yang memerlukan penanganan:');
            $this->table(
                ['ID koperasi', 'region_id', 'Kode wilayah', 'Tipe', 'Alasan'],
                array_map(static fn (array $row): array => [
                    $row['cooperative_id'],
                    $row['region_id'] ?? '—',
                    $row['region_code'] ?? '—',
                    $row['region_type'] ?? '—',
                    $row['reason'],
                ], $exceptions),
            );
        }

        $jsonPath = $this->option('json');

        if (is_string($jsonPath) && $jsonPath !== '') {
            $directory = dirname($jsonPath);

            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                $this->error("Direktori laporan tidak dapat dibuat: {$directory}");

                return self::FAILURE;
            }

            file_put_contents(
                $jsonPath,
                json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL,
            );

            $this->info("Laporan JSON ditulis ke {$jsonPath}");
        }

        return self::SUCCESS;
    }
}
