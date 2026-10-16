<?php

namespace Tests\Feature\Seeder;

use App\Models\Cooperative;
use App\Models\CooperativeAsset;
use App\Models\CooperativeLicense;
use App\Models\Document;
use App\Models\GovernanceRecord;
use App\Models\HealthAssessment;
use App\Models\MemberHistory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Seeder demo bersifat opt-in: tidak dijalankan otomatis dan tidak membuat akun.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('koperasi.document_disk', 'local'));
    }

    public function test_database_seeder_does_not_create_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Cooperative::count());
        $this->assertSame(0, CooperativeLicense::count());
        $this->assertSame(0, Document::count());
        $this->assertSame(0, HealthAssessment::count());
        $this->assertSame(0, GovernanceRecord::count());
        $this->assertSame(0, CooperativeAsset::count());
        $this->assertSame(0, MemberHistory::count());
    }

    public function test_demo_seeder_creates_demo_data(): void
    {
        User::factory()->admin()->create();

        $this->seed(DemoSeeder::class);

        $cooperative = Cooperative::where('nomor_identitas', 'DEMO-KOP-0001')->firstOrFail();

        $this->assertSame(2, $cooperative->licenses()->count());

        $document = Document::firstOrFail();

        $this->assertSame($cooperative->id, $document->cooperative_id);
        Storage::disk('local')->assertExists($document->file_path);

        // Modul periodik F4 (ada akun → semua modul terisi).
        $this->assertSame(2, HealthAssessment::where('cooperative_id', $cooperative->id)->count());
        $this->assertSame(2, GovernanceRecord::where('cooperative_id', $cooperative->id)->count());
        $this->assertSame(3, CooperativeAsset::where('cooperative_id', $cooperative->id)->count());
        $this->assertSame(2, MemberHistory::where('cooperative_id', $cooperative->id)->count());
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        User::factory()->admin()->create();

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(1, Cooperative::where('nomor_identitas', 'DEMO-KOP-0001')->count());
        $this->assertSame(2, CooperativeLicense::count());
        $this->assertSame(1, Document::count());
        $this->assertSame(2, HealthAssessment::count());
        $this->assertSame(2, GovernanceRecord::count());
        $this->assertSame(3, CooperativeAsset::count());
        $this->assertSame(2, MemberHistory::count());
    }

    public function test_demo_seeder_skips_document_when_no_user_exists(): void
    {
        // Tidak ada akun → tidak ada dokumen contoh (uploaded_by wajib), lisensi tetap dibuat.
        // Kesehatan tetap terisi (assessor_id nullable); tata kelola/aset/anggota dilewati
        // karena `recorded_by` wajib.
        $this->seed(DemoSeeder::class);

        $this->assertSame(2, CooperativeLicense::count());
        $this->assertSame(0, Document::count());
        $this->assertSame(2, HealthAssessment::count());
        $this->assertSame(0, GovernanceRecord::count());
        $this->assertSame(0, CooperativeAsset::count());
        $this->assertSame(0, MemberHistory::count());
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        User::factory()->admin()->create();

        $originalEnv = $this->app['env'];

        try {
            $this->app['env'] = 'production';

            // Dijalankan langsung (bukan lewat Artisan) agar tidak ada prompt konfirmasi.
            (new DemoSeeder)->run();
        } finally {
            $this->app['env'] = $originalEnv;
        }

        $this->assertSame(0, Cooperative::count());
        $this->assertSame(0, CooperativeLicense::count());
        $this->assertSame(0, HealthAssessment::count());
        $this->assertSame(0, GovernanceRecord::count());
        $this->assertSame(0, CooperativeAsset::count());
        $this->assertSame(0, MemberHistory::count());
    }

    public function test_demo_seeder_fills_foreign_keys_without_unguarded_context(): void
    {
        // Dijalankan langsung (tanpa `Model::unguarded()` milik perintah `db:seed`) untuk
        // membuktikan FK non-fillable diisi eksplisit oleh seeder.
        User::factory()->admin()->create();

        (new DemoSeeder)->run();

        $cooperative = Cooperative::where('nomor_identitas', 'DEMO-KOP-0001')->firstOrFail();

        $this->assertSame(2, $cooperative->licenses()->count());
        $this->assertSame(1, Document::count());
        $this->assertSame(2, HealthAssessment::where('cooperative_id', $cooperative->id)->count());
        $this->assertSame(2, GovernanceRecord::where('cooperative_id', $cooperative->id)->count());
        $this->assertSame(3, CooperativeAsset::where('cooperative_id', $cooperative->id)->count());
        $this->assertSame(2, MemberHistory::where('cooperative_id', $cooperative->id)->count());

        // Seeder tidak membuat akun/kredensial.
        $this->assertSame(1, User::count());
    }

    public function test_demo_seeder_restores_archived_demo_records(): void
    {
        User::factory()->admin()->create();

        $this->seed(DemoSeeder::class);

        $cooperative = Cooperative::where('nomor_identitas', 'DEMO-KOP-0001')->firstOrFail();

        $health = HealthAssessment::where('cooperative_id', $cooperative->id)->orderBy('period_year')->firstOrFail();
        $governance = GovernanceRecord::where('cooperative_id', $cooperative->id)->firstOrFail();
        $asset = CooperativeAsset::where('cooperative_id', $cooperative->id)->firstOrFail();
        $member = MemberHistory::where('cooperative_id', $cooperative->id)->firstOrFail();

        $health->delete();
        $governance->delete();
        $asset->delete();
        $member->delete();

        $this->assertSoftDeleted('health_assessments', ['id' => $health->id]);
        $this->assertSoftDeleted('governance_records', ['id' => $governance->id]);
        $this->assertSoftDeleted('cooperative_assets', ['id' => $asset->id]);
        $this->assertSoftDeleted('member_histories', ['id' => $member->id]);

        // Jalankan ulang: baris demo terarsip harus DIPULIHKAN, bukan diduplikasi.
        $this->seed(DemoSeeder::class);

        $this->assertSame(2, HealthAssessment::count());
        $this->assertSame(2, GovernanceRecord::count());
        $this->assertSame(3, CooperativeAsset::count());
        $this->assertSame(2, MemberHistory::count());

        $this->assertNotSoftDeleted('health_assessments', ['id' => $health->id]);
        $this->assertNotSoftDeleted('governance_records', ['id' => $governance->id]);
        $this->assertNotSoftDeleted('cooperative_assets', ['id' => $asset->id]);
        $this->assertNotSoftDeleted('member_histories', ['id' => $member->id]);
    }
}
