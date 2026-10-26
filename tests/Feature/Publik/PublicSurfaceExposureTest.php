<?php

namespace Tests\Feature\Publik;

use App\Models\Cooperative;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Permukaan publik F7: apa yang **tidak boleh** dapat dijangkau tanpa autentikasi.
 *
 * Audit Tahap 86 memverifikasi bahwa selain direktori/profil publik (`/koperasi`, `/koperasi/{slug}`)
 * tidak ada jalur publik alternatif yang membocorkan data. Dua penjagaan diuji di sini:
 *
 * 1. Halaman publik tidak memuat tautan/atribut ke area internal (`/admin`, unduhan dokumen, storage).
 * 2. Route bawaan framework `GET /storage/{path}` tidak dapat menyajikan dokumen privat tanpa
 *    *signed URL* — dokumen disimpan pada disk privat (`config('koperasi.document_disk') = 'local'`),
 *    sedangkan disk `public` tidak di-`serve`.
 *
 * Catatan bukti: suite berjalan pada **SQLite `:memory:`** — bukan bukti kompatibilitas MariaDB.
 */
class PublicSurfaceExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_do_not_expose_internal_endpoints_or_storage_links(): void
    {
        $cooperative = Cooperative::factory()->create([
            'nama' => 'Koperasi Uji Permukaan Publik',
            'status_verifikasi' => 'approved',
            'is_published' => true,
        ]);

        $responses = [
            $this->get(route('publik.direktori'))->assertOk(),
            $this->get(route('publik.koperasi', ['slug' => $cooperative->slug]))->assertOk(),
        ];

        foreach ($responses as $response) {
            $response->assertDontSee('/admin');
            $response->assertDontSee('/dokumen/');
            $response->assertDontSee('storage/');
        }
    }

    public function test_unsigned_storage_url_cannot_serve_private_documents(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('documents/1/kontrak-rahasia.pdf', 'ISI-DOKUMEN-RAHASIA');

        $response = $this->get('/storage/documents/1/kontrak-rahasia.pdf');

        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            'Disk privat wajib menolak permintaan tanpa signed URL.',
        );

        $response->assertDontSee('ISI-DOKUMEN-RAHASIA');
    }
}
