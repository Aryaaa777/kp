<?php

namespace Tests\Feature\Seeder;

use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Document;
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
    }

    public function test_demo_seeder_is_idempotent(): void
    {
        User::factory()->admin()->create();

        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(1, Cooperative::where('nomor_identitas', 'DEMO-KOP-0001')->count());
        $this->assertSame(2, CooperativeLicense::count());
        $this->assertSame(1, Document::count());
    }

    public function test_demo_seeder_skips_document_when_no_user_exists(): void
    {
        // Tidak ada akun → tidak ada dokumen contoh (uploaded_by wajib), lisensi tetap dibuat.
        $this->seed(DemoSeeder::class);

        $this->assertSame(2, CooperativeLicense::count());
        $this->assertSame(0, Document::count());
    }
}
