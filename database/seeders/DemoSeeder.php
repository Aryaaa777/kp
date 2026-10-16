<?php

namespace Database\Seeders;

use App\Models\Cooperative;
use App\Models\CooperativeAsset;
use App\Models\CooperativeLicense;
use App\Models\Document;
use App\Models\GovernanceRecord;
use App\Models\HealthAssessment;
use App\Models\MemberHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
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
 * - Bersifat idempoten sehingga aman dijalankan berulang.
 *
 * Data yang dibuat diberi penanda jelas sebagai DEMO (nomor identitas berawalan `DEMO-`).
 *
 * Sejak F4, seeder ini juga mengisi data contoh untuk modul periodik (kesehatan, tata kelola,
 * aset, riwayat keanggotaan). Aturan periodisitas/klasifikasi masih PROVISIONAL (Q-010).
 * Modul yang memerlukan FK `recorded_by` (tata kelola/aset/anggota) dilewati bila belum ada
 * akun pengguna (seeder tidak membuat akun).
 *
 * Catatan implementasi:
 * - Foreign key non-fillable (`cooperative_id`, `assessor_id`, `recorded_by`, `uploaded_by`) dan
 *   `deleted_at` ditetapkan **eksplisit** (lihat `fillDemoRecord()`), sehingga seeder TIDAK
 *   bergantung pada `Model::unguarded()` milik perintah Artisan dan mass-assignment model tetap
 *   terjaga.
 * - Baris demo yang sebelumnya diarsipkan **dipulihkan** (bukan diduplikasi) karena pencarian
 *   memakai `withTrashed()`.
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

        $this->fillDemoRecord($cooperative, [
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

        $business = CooperativeLicense::withTrashed()->firstOrNew([
            'cooperative_id' => $cooperative->id,
            'license_type' => CooperativeLicense::TYPE_BUSINESS,
            'nomor' => 'DEMO-IU-0001',
        ]);
        $this->fillDemoRecord($business, [
            'tanggal_terbit' => '2023-01-15',
            'tanggal_berlaku_sampai' => '2028-01-14',
            'penerbit' => 'Dinas (contoh)',
            'status' => 'active',
            'verified_at' => now(),
            'catatan' => 'Data contoh untuk demonstrasi.',
        ], [
            'cooperative_id' => $cooperative->id,
        ]);

        $savingsLoans = CooperativeLicense::withTrashed()->firstOrNew([
            'cooperative_id' => $cooperative->id,
            'license_type' => CooperativeLicense::TYPE_SAVINGS_LOANS,
            'nomor' => 'DEMO-ISP-0001',
        ]);
        $this->fillDemoRecord($savingsLoans, [
            'tanggal_terbit' => '2023-02-01',
            'tanggal_berlaku_sampai' => null,
            'penerbit' => 'Dinas (contoh)',
            'status' => 'pending',
            'catatan' => 'Data contoh untuk demonstrasi (belum diverifikasi).',
        ], [
            'cooperative_id' => $cooperative->id,
        ]);

        $this->seedDemoDocument($cooperative, $business);
        $this->seedPeriodikModules($cooperative);

        $this->command?->info('Data demo dibuat/diperbarui: koperasi "'.$cooperative->nama.'" ('.$cooperative->nomor_identitas.').');
        $this->command?->warn('Ini data contoh. Hapus/arsipkan bila tidak diperlukan sebelum penggunaan nyata.');
    }

    /**
     * Isi & simpan model demo tanpa bergantung pada `Model::unguarded()` milik Artisan.
     *
     * `$forced` memuat kolom non-fillable (FK) dan ditetapkan eksplisit di server sehingga
     * mass-assignment model tetap terjaga. `deleted_at` selalu di-set `null` agar baris demo yang
     * sebelumnya diarsipkan otomatis **dipulihkan** (bukan diduplikasi).
     *
     * @param  array<string, mixed>  $values  Kolom fillable.
     * @param  array<string, mixed>  $forced  Kolom non-fillable (FK).
     */
    private function fillDemoRecord(Model $record, array $values, array $forced = []): void
    {
        $record->fill($values);

        foreach ($forced as $column => $value) {
            $record->{$column} = $value;
        }

        $record->deleted_at = null;
        $record->save();
    }

    /**
     * Data contoh modul periodik F4 (idempoten + memulihkan baris terarsip).
     *
     * PROVISIONAL: periodisitas tahunan, skor/predikat manual — bukan penilaian resmi (Q-010).
     */
    private function seedPeriodikModules(Cooperative $cooperative): void
    {
        $actorId = User::query()->orderBy('id')->value('id');
        $year = (int) date('Y');

        // Kesehatan — assessor_id nullable sehingga dapat diisi tanpa akun.
        $health = HealthAssessment::withTrashed()->firstOrNew([
            'cooperative_id' => $cooperative->id,
            'period_year' => $year - 2,
            'period' => 'annual',
        ]);
        $this->fillDemoRecord($health, [
            'assessment_date' => ($year - 2).'-12-20',
            'score' => 82.50,
            'predicate' => 'Sehat (contoh)',
            'notes' => 'Data contoh untuk demonstrasi (bukan penilaian resmi).',
            'status' => 'final',
        ], [
            'cooperative_id' => $cooperative->id,
            'assessor_id' => $actorId,
        ]);

        $health = HealthAssessment::withTrashed()->firstOrNew([
            'cooperative_id' => $cooperative->id,
            'period_year' => $year - 1,
            'period' => 'annual',
        ]);
        $this->fillDemoRecord($health, [
            'assessment_date' => ($year - 1).'-12-18',
            'score' => 78.00,
            'predicate' => 'Cukup Sehat (contoh)',
            'notes' => 'Data contoh untuk demonstrasi (bukan penilaian resmi).',
            'status' => 'draft',
        ], [
            'cooperative_id' => $cooperative->id,
            'assessor_id' => $actorId,
        ]);

        if ($actorId === null) {
            $this->command?->warn('Data tata kelola/aset/anggota contoh dilewati: belum ada akun pengguna (buat dengan `php artisan user:create`).');

            return;
        }

        // Tata kelola (unique per koperasi+tahun+periode).
        foreach ([$year - 2, $year - 1] as $govYear) {
            $record = GovernanceRecord::withTrashed()->firstOrNew([
                'cooperative_id' => $cooperative->id,
                'period_year' => $govYear,
                'period' => 'annual',
            ]);
            $this->fillDemoRecord($record, [
                'meeting_count' => 4,
                'governance_score' => 80.00,
                'summary' => 'Ringkasan tata kelola contoh (data demonstrasi).',
                'status' => 'draft',
            ], [
                'cooperative_id' => $cooperative->id,
                'recorded_by' => $actorId,
            ]);
        }

        // Aset (snapshot tahunan, multiple baris per periode).
        $assets = [
            ['asset_type' => 'Tanah', 'asset_name' => 'Tanah kantor (contoh)', 'quantity' => 1, 'value' => 500000000.00, 'condition' => 'baik'],
            ['asset_type' => 'Bangunan', 'asset_name' => 'Gedung kantor (contoh)', 'quantity' => 1, 'value' => 750000000.00, 'condition' => 'baik'],
            ['asset_type' => 'Peralatan', 'asset_name' => 'Peralatan kantor (contoh)', 'quantity' => 10, 'value' => 25000000.00, 'condition' => 'rusak_ringan'],
        ];

        foreach ($assets as $asset) {
            $record = CooperativeAsset::withTrashed()->firstOrNew([
                'cooperative_id' => $cooperative->id,
                'period_year' => $year - 1,
                'asset_type' => $asset['asset_type'],
                'asset_name' => $asset['asset_name'],
            ]);
            $this->fillDemoRecord($record, [
                'quantity' => $asset['quantity'],
                'value' => $asset['value'],
                'acquisition_date' => ($year - 5).'-06-01',
                'condition' => $asset['condition'],
                'description' => 'Data contoh untuk demonstrasi.',
            ], [
                'cooperative_id' => $cooperative->id,
                'recorded_by' => $actorId,
            ]);
        }

        // Riwayat keanggotaan (berbasis kejadian).
        $events = [
            [
                'event_date' => ($year - 1).'-03-01',
                'change_type' => 'penambahan',
                'members_added' => 25,
                'members_removed' => null,
                'notes' => 'Penambahan anggota contoh (data demonstrasi).',
            ],
            [
                'event_date' => ($year - 1).'-08-15',
                'change_type' => 'pengurangan',
                'members_added' => null,
                'members_removed' => 5,
                'notes' => 'Pengurangan anggota contoh (data demonstrasi).',
            ],
        ];

        foreach ($events as $event) {
            $record = MemberHistory::withTrashed()->firstOrNew([
                'cooperative_id' => $cooperative->id,
                'event_date' => Carbon::parse($event['event_date']),
                'change_type' => $event['change_type'],
            ]);
            $this->fillDemoRecord($record, [
                'members_added' => $event['members_added'],
                'members_removed' => $event['members_removed'],
                'notes' => $event['notes'],
            ], [
                'cooperative_id' => $cooperative->id,
                'recorded_by' => $actorId,
            ]);
        }
    }

    /**
     * Menulis satu berkas contoh di storage privat + metadata dokumen (idempoten + memulihkan
     * baris terarsip).
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

        $document = Document::withTrashed()->firstOrNew([
            'cooperative_id' => $cooperative->id,
            'documentable_type' => $license->getMorphClass(),
            'documentable_id' => $license->getKey(),
            'original_name' => 'demo-contoh-izin.pdf',
        ]);
        $this->fillDemoRecord($document, [
            'category' => 'izin_usaha',
            'file_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'checksum' => hash('sha256', $content),
            'version' => 1,
        ], [
            'cooperative_id' => $cooperative->id,
            'uploaded_by' => $uploaderId,
        ]);
    }
}
