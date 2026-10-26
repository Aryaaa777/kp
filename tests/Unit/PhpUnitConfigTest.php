<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Regresi untuk berkas konfigurasi PHPUnit.
 *
 * Komentar XML tidak boleh memuat `--`; contoh konfigurasi MariaDB pernah gagal
 * diparse karena memuat opsi `--no-coverage` di dalam komentar. CI memakai berkas
 * contoh tersebut, jadi validitas XML diperiksa sebelum suite dijalankan.
 */
class PhpUnitConfigTest extends TestCase
{
    public function test_phpunit_configuration_files_are_valid_xml(): void
    {
        $files = [
            'phpunit.xml',
            'phpunit.mariadb.xml.example',
        ];

        foreach ($files as $file) {
            $path = base_path($file);

            $this->assertFileExists($path);

            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $xml = simplexml_load_file($path);
            $errors = libxml_get_errors();

            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            $this->assertNotFalse($xml, "XML tidak valid: {$file}");

            $messages = array_map(
                static fn ($error): string => trim($error->message).' pada baris '.$error->line,
                $errors
            );

            $this->assertSame([], $messages, "Kesalahan XML pada {$file}: ".implode('; ', $messages));
        }
    }
}
