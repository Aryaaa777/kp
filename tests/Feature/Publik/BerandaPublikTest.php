<?php

namespace Tests\Feature\Publik;

use Tests\TestCase;

/**
 * Halaman beranda publik (Tahap 90).
 *
 * Beranda harus mengarahkan pengunjung ke **direktori publik** dan tidak lagi memuat
 * klaim usang bahwa seluruh modul belum diimplementasikan. Tidak ada statistik/jumlah
 * koperasi maupun data bisnis tanpa sumber resmi yang ditambahkan di sini.
 */
class BerandaPublikTest extends TestCase
{
    public function test_beranda_mengarahkan_pengunjung_ke_direktori_publik(): void
    {
        $response = $this->get(route('home'))->assertOk();

        // Tautan menuju direktori publik tersedia bagi tamu (tanpa autentikasi).
        $response->assertSee('href="'.route('publik.direktori').'"', false);
        $response->assertSee('Lihat direktori koperasi');

        // Tautan masuk tetap ada untuk pengguna internal.
        $response->assertSee('Masuk ke sistem');
    }

    public function test_beranda_tidak_lagi_memuat_klaim_implementasi_yang_usang(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Seluruh modul bisnis belum diimplementasikan')
            ->assertDontSee('Fondasi Proyek')
            ->assertDontSee('Belum ada data koperasi nyata');
    }

    public function test_beranda_tidak_menautkan_area_internal_atau_penyimpanan_privat(): void
    {
        $response = $this->get(route('home'))->assertOk();

        foreach (['/admin', '/storage/', '/koperasi/profil'] as $internal) {
            $response->assertDontSee($internal);
        }
    }
}
