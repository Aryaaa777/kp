<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Regresi untuk peringatan kompilasi PHP pada berkas migrasi.
 *
 * Peringatan seperti `The use statement with non-compound name ... has no effect`
 * tidak menggagalkan `php -l` (exit code 0), tetapi Laravel mengubahnya menjadi
 * ErrorException saat migrasi dimuat. CI MariaDB 10.11 pernah gagal karena hal ini,
 * jadi setiap berkas migrasi diperiksa dengan `error_reporting=E_ALL`.
 */
class MigrationLintTest extends TestCase
{
    public function test_migration_files_compile_without_php_warnings(): void
    {
        if (! function_exists('exec')) {
            $this->markTestSkipped('Fungsi exec tidak tersedia untuk memeriksa migrasi.');
        }

        $files = glob(database_path('migrations/*.php')) ?: [];

        $this->assertNotEmpty($files, 'Tidak ada berkas migrasi yang ditemukan.');

        foreach ($files as $file) {
            $output = [];
            $exitCode = 0;

            exec(
                escapeshellarg(PHP_BINARY).' -d error_reporting=E_ALL -l '.escapeshellarg($file).' 2>&1',
                $output,
                $exitCode
            );

            $text = implode("\n", $output);

            $this->assertSame(0, $exitCode, "php -l gagal untuk {$file}:\n{$text}");
            $this->assertStringNotContainsString(
                'Warning:',
                $text,
                "Peringatan kompilasi PHP pada {$file}:\n{$text}"
            );
        }
    }
}
