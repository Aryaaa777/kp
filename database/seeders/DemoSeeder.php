<?php

namespace Database\Seeders;

use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Data DEMO untuk keperluan demonstrasi/pengujian manual (opt-in).
 *
 * PENTING:
 * - Seeder ini **TIDAK** dipanggil oleh `DatabaseSeeder`, sehingga tidak pernah berjalan otomatis
 *   (`php artisan db:seed` aman — hanya meng-seed data referensi).
 * - Jalankan secara eksplisit bila memang butuh data contoh:
 *     php artisan db:seed --class=DemoSeeder
 * - Tidak diizinkan berjalan pada lingkungan produksi.
 * - Tidak membuat akun apa pun (tidak ada password di source code). Buat akun dengan
 *   `php artisan user:create`.
 * - Bersifat idempoten (updateOrCreate) sehingga aman dijalankan berulang.
 *
 * Data yang dibuat diberi penanda jelas sebagai DEMO (nomor identitas berawalan `DEMO-`).
 */
class DemoSeeder extends Seeder
{
    private const DEMO_NOMOR_IDENTITAS = 'DEMO-KOP-0001';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoSeeder tidak boleh dijalankan pada lingkungan produksi.');

            return;
        }

        $cooperative = Cooperative::withTrashed()->firstOrNew([
            'nomor_identitas' => self::DEMO_NOMOR_IDENTITAS,
        ]);

        $cooperative->fill([
            'nama' => 'Koperasi Demo Sejahtera',
            'jenis_koperasi' => 'Koperasi Demo (data contoh)',
            'bentuk_koperasi' => null,
            'tanggal_berdiri' => '2015-01-01',
            'region_id' => null,
            'alamat' => 'Alamat contoh untuk demonstrasi (bukan data resmi).',
            'telephone' => '0800000000',
            'email' => 'demo@example.test',
            'website' => null,
            'status_verifikasi' => 'approved',
            'is_published' => false,
        ]);

        // Pulihkan bila sebelumnya diarsipkan. `deleted_at` tidak mass-assignable, jadi di-set langsung.
        $cooperative->deleted_at = null;
        $cooperative->save();

        $business = CooperativeLicense::updateOrCreate(
            [
                'cooperative_id' => $cooperative->id,
                'license_type' => CooperativeLicense::TYPE_BUSINESS,
                'nomor' => 'DEMO-IU-0001',
            ],
            [
                'tanggal_terbit' => '2023-01-15',
                'tanggal_berlaku_sampai' => '2028-01-14',
                'penerbit' => 'Dinas (contoh)',
                'status' => 'active',
                'verified_at' => now(),
                'catatan' => 'Data contoh untuk demonstrasi.',
            ],
        );

        CooperativeLicense::updateOrCreate(
            [
                'cooperative_id' => $cooperative->id,
                'license_type' => CooperativeLicense::TYPE_SAVINGS_LOANS,
                'nomor' => 'DEMO-ISP-0001',
            ],
            [
                'tanggal_terbit' => '2023-02-01',
                'tanggal_berlaku_sampai' => null,
                'penerbit' => 'Dinas (contoh)',
                'status' => 'pending',
                'catatan' => 'Data contoh untuk demonstrasi (belum diverifikasi).',
            ],
        );

        $this->seedDemoDocument($cooperative, $business);

        $this->command?->info('Data demo dibuat/diperbarui: koperasi "'.$cooperative->nama.'" ('.$cooperative->nomor_identitas.').');
        $this->command?->warn('Ini data contoh. Hapus/arsipkan bila tidak diperlukan sebelum penggunaan nyata.');
    }

    /**
     * Menulis satu berkas contoh di storage privat + metadata dokumen (idempoten).
     *
     * `documents.uploaded_by` wajib menunjuk pengguna; seeder ini TIDAK membuat akun, jadi
     * dokumen contoh hanya dibuat bila sudah ada akun (mis. admin hasil `user:create`).
     */
    private function seedDemoDocument(Cooperative $cooperative, CooperativeLicense $license): void
    {
        $uploaderId = User::query()->orderBy('id')->value('id');

        if ($uploaderId === null) {
            $this->command?->warn('Dokumen contoh dilewati: belum ada akun pengguna. Buat akun dengan `php artisan user:create`.');

            return;
        }

        $disk = (string) config('koperasi.document_disk', 'local');
        $path = "documents/{$cooperative->id}/demo-contoh-izin.pdf";

        // Berkas PDF minimal (hanya penanda; bukan dokumen resmi).
        $content = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF\n";

        Storage::disk($disk)->put($path, $content);

        Document::updateOrCreate(
            [
                'cooperative_id' => $cooperative->id,
                'documentable_type' => $license->getMorphClass(),
                'documentable_id' => $license->getKey(),
                'original_name' => 'demo-contoh-izin.pdf',
            ],
            [
                'category' => 'izin_usaha',
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($content),
                'checksum' => hash('sha256', $content),
                'version' => 1,
                'uploaded_by' => $uploaderId,
            ],
        );
    }
}
