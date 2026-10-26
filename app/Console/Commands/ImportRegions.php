<?php

namespace App\Console\Commands;

use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Impor referensi wilayah dari CSV resmi (Kepmendagri 2025).
 *
 * Perintah ini idempoten: kode wilayah adalah kunci alami, sehingga menjalankan
 * ulang file yang sama tidak membuat duplikat. Seluruh penulisan berada dalam
 * satu transaksi; bila ada baris tidak valid, tidak ada perubahan yang tersimpan.
 *
 * Tidak ada penghapusan data. Wilayah yang tidak ada di CSV dibiarkan apa adanya.
 */
class ImportRegions extends Command
{
    protected $signature = 'regions:import
        {file? : Path CSV (default: database/data/regions/jawa_barat_2025.csv)}
        {--dry-run : Validasi dan tampilkan ringkasan tanpa menulis ke database}
        {--expected= : Jumlah baris per level, mis. province:1,regency:27,district:627,village:5957}';

    protected $description = 'Mengimpor referensi wilayah (provinsi s.d. desa/kelurahan) dari CSV resmi';

    /**
     * Urutan level dari induk ke anak.
     *
     * @var list<string>
     */
    private const LEVELS = ['province', 'regency', 'district', 'village'];

    /**
     * @var array<string, string>
     */
    private const TYPE_MAP = [
        'province' => 'province',
        'kabupaten' => 'regency',
        'kota' => 'city',
        'regency' => 'regency',
        'city' => 'city',
        'district' => 'district',
        'village' => 'village',
    ];

    public function handle(): int
    {
        $path = $this->argument('file')
            ?: database_path('data/regions/jawa_barat_2025.csv');

        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        try {
            $rows = $this->readAndValidate($path);
            $this->validateExpectedCounts($rows);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $counts = array_fill_keys(self::LEVELS, 0);
        foreach ($rows as $row) {
            $counts[$row['level']]++;
        }

        $this->info('Sumber: '.$path);
        $this->table(
            ['Level', 'Baris'],
            collect($counts)->map(fn (int $count, string $level): array => [$level, $count])->values()->all(),
        );

        if ($this->option('dry-run')) {
            $this->info('Dry-run: validasi berhasil, tidak ada perubahan database.');

            return self::SUCCESS;
        }

        $created = array_fill_keys(self::LEVELS, 0);
        $updated = array_fill_keys(self::LEVELS, 0);

        try {
            DB::transaction(function () use ($rows, &$created, &$updated): void {
                /** @var array<string, int> $ids */
                $ids = [];

                foreach (self::LEVELS as $level) {
                    foreach ($rows as $row) {
                        if ($row['level'] !== $level) {
                            continue;
                        }

                        $parentId = $row['parent_code'] === '' ? null : ($ids[$row['parent_code']] ?? null);

                        if ($row['parent_code'] !== '' && $parentId === null) {
                            throw new RuntimeException(
                                "Induk {$row['parent_code']} belum tersedia untuk {$row['code']}."
                            );
                        }

                        $region = Region::query()->updateOrCreate(
                            ['code' => $row['code']],
                            [
                                'name' => $row['name'],
                                'type' => self::TYPE_MAP[$row['type']],
                                'parent_id' => $parentId,
                            ],
                        );

                        if ($region->wasRecentlyCreated) {
                            $created[$level]++;
                        } else {
                            $updated[$level]++;
                        }

                        $ids[$row['code']] = $region->id;
                    }
                }
            });
        } catch (Throwable $exception) {
            $this->error('Impor dibatalkan (transaksi di-rollback): '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Level', 'Dibuat', 'Diperbarui'],
            collect(self::LEVELS)->map(fn (string $level): array => [
                $level,
                $created[$level],
                $updated[$level],
            ])->all(),
        );

        $this->info('Impor selesai. Tidak ada data yang dihapus.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{level: string, code: string, name: string, parent_code: string, type: string}>
     */
    private function readAndValidate(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Berkas tidak dapat dibaca: {$path}");
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw new RuntimeException('Berkas CSV kosong.');
            }

            $header = array_map(static fn ($value): string => trim((string) $value), $header);
            $expectedHeader = ['level', 'code', 'name', 'parent_code', 'type'];

            if ($header !== $expectedHeader) {
                throw new RuntimeException(
                    'Header CSV harus: '.implode(',', $expectedHeader)
                    .'. Ditemukan: '.implode(',', $header)
                );
            }

            /** @var array<string, array{level: string, code: string, name: string, parent_code: string, type: string}> $rows */
            $rows = [];
            $line = 1;

            while (($values = fgetcsv($handle)) !== false) {
                $line++;

                if ($values === [null] || $values === []) {
                    continue;
                }

                if (count($values) !== 5) {
                    throw new RuntimeException("Baris {$line}: jumlah kolom harus 5.");
                }

                $row = [
                    'level' => trim((string) $values[0]),
                    'code' => trim((string) $values[1]),
                    'name' => trim((string) $values[2]),
                    'parent_code' => trim((string) $values[3]),
                    'type' => trim((string) $values[4]),
                ];

                $this->validateRow($row, $line);

                if (isset($rows[$row['code']])) {
                    throw new RuntimeException("Baris {$line}: kode duplikat {$row['code']}.");
                }

                $rows[$row['code']] = $row;
            }

            if ($rows === []) {
                throw new RuntimeException('CSV tidak memuat baris data.');
            }

            $this->validateHierarchy($rows);

            return array_values($rows);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array{level: string, code: string, name: string, parent_code: string, type: string}  $row
     */
    private function validateRow(array $row, int $line): void
    {
        if (! in_array($row['level'], self::LEVELS, true)) {
            throw new RuntimeException("Baris {$line}: level tidak dikenal '{$row['level']}'.");
        }

        if ($row['name'] === '' || mb_strlen($row['name']) > 150) {
            throw new RuntimeException("Baris {$line}: nama wajib diisi (maks. 150 karakter).");
        }

        $patterns = [
            'province' => '/^\d{2}$/',
            'regency' => '/^\d{2}\.\d{2}$/',
            'district' => '/^\d{2}\.\d{2}\.\d{2}$/',
            'village' => '/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/',
        ];

        if (! preg_match($patterns[$row['level']], $row['code'])) {
            throw new RuntimeException(
                "Baris {$line}: format kode '{$row['code']}' tidak sesuai level {$row['level']}."
            );
        }

        if (! isset(self::TYPE_MAP[$row['type']])) {
            throw new RuntimeException("Baris {$line}: tipe '{$row['type']}' tidak dikenal.");
        }

        $type = self::TYPE_MAP[$row['type']];

        $allowedTypes = match ($row['level']) {
            'province' => ['province'],
            'regency' => ['regency', 'city'],
            'district' => ['district'],
            'village' => ['village'],
        };

        if (! in_array($type, $allowedTypes, true)) {
            throw new RuntimeException(
                "Baris {$line}: tipe '{$row['type']}' tidak sesuai level {$row['level']}."
            );
        }

        $expectedParent = match ($row['level']) {
            'province' => '',
            'regency' => substr($row['code'], 0, 2),
            'district' => substr($row['code'], 0, 5),
            'village' => substr($row['code'], 0, 8),
        };

        if ($row['parent_code'] !== $expectedParent) {
            throw new RuntimeException(
                "Baris {$line}: parent_code '{$row['parent_code']}' tidak sesuai kode "
                ."{$row['code']} (seharusnya '{$expectedParent}')."
            );
        }
    }

    /**
     * @param  array<string, array{level: string, code: string, name: string, parent_code: string, type: string}>  $rows
     */
    private function validateHierarchy(array $rows): void
    {
        $parentLevel = [
            'regency' => 'province',
            'district' => 'regency',
            'village' => 'district',
        ];

        foreach ($rows as $code => $row) {
            if ($row['level'] === 'province') {
                continue;
            }

            $parent = $rows[$row['parent_code']] ?? null;

            if ($parent === null) {
                throw new RuntimeException("Kode {$code}: induk {$row['parent_code']} tidak ada di CSV.");
            }

            if ($parent['level'] !== $parentLevel[$row['level']]) {
                throw new RuntimeException(
                    "Kode {$code}: induk {$row['parent_code']} berlevel {$parent['level']}, "
                    ."seharusnya {$parentLevel[$row['level']]}."
                );
            }
        }

        $provinces = array_filter($rows, static fn (array $row): bool => $row['level'] === 'province');

        if (count($provinces) !== 1) {
            throw new RuntimeException('CSV harus memuat tepat satu provinsi.');
        }
    }

    /**
     * @param  list<array{level: string, code: string, name: string, parent_code: string, type: string}>  $rows
     */
    private function validateExpectedCounts(array $rows): void
    {
        $expected = $this->option('expected');

        if (! is_string($expected) || trim($expected) === '') {
            return;
        }

        $actual = array_fill_keys(self::LEVELS, 0);
        foreach ($rows as $row) {
            $actual[$row['level']]++;
        }

        foreach (explode(',', $expected) as $pair) {
            [$level, $count] = array_pad(explode(':', trim($pair), 2), 2, null);

            if (! in_array($level, self::LEVELS, true) || $count === null || ! ctype_digit($count)) {
                throw new RuntimeException("Format --expected tidak valid: '{$pair}'.");
            }

            if ($actual[$level] !== (int) $count) {
                throw new RuntimeException(
                    "Jumlah {$level} tidak sesuai: ditemukan {$actual[$level]}, "
                    ."seharusnya {$count}."
                );
            }
        }
    }
}
