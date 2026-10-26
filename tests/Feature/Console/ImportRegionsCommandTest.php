<?php

namespace Tests\Feature\Console;

use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Impor referensi wilayah (Kepmendagri 2025) — idempoten, transaksional,
 * dan menolak hierarki yang tidak valid.
 */
class ImportRegionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): string
    {
        return base_path('tests/Fixtures/regions/jawa_barat_sample.csv');
    }

    /**
     * @param  list<string>  $lines
     */
    private function temporaryCsv(array $lines): string
    {
        $path = tempnam(sys_get_temp_dir(), 'regions_');
        $this->assertNotFalse($path);
        file_put_contents($path, implode("\n", $lines)."\n");

        return $path;
    }

    public function test_imports_real_fixture_and_preserves_codes_and_hierarchy(): void
    {
        $this->artisan('regions:import', [
            'file' => $this->fixture(),
            '--expected' => 'province:1,regency:1,district:2,village:4',
        ])->assertExitCode(0);

        $this->assertDatabaseCount('regions', 8);

        $village = Region::query()->where('code', '32.01.01.1001')->firstOrFail();

        // Kode disimpan sebagai string; nol di depan segmen tidak hilang.
        $this->assertSame('32.01.01.1001', $village->code);
        $this->assertSame('Pondok Rajeg', $village->name);
        $this->assertSame('village', $village->type);
        $this->assertSame('32.01.01', $village->parent?->code);
        $this->assertSame('32.01', $village->parent?->parent?->code);
        $this->assertSame('32', $village->parent?->parent?->parent?->code);

        $this->assertDatabaseHas('regions', [
            'code' => '32.01.02',
            'name' => 'Gunungputri',
            'type' => 'district',
        ]);
    }

    public function test_import_is_idempotent(): void
    {
        $this->artisan('regions:import', ['file' => $this->fixture()])->assertExitCode(0);
        $this->artisan('regions:import', ['file' => $this->fixture()])->assertExitCode(0);

        $this->assertDatabaseCount('regions', 8);
        $this->assertSame(1, Region::query()->where('code', '32.01')->count());
        $this->assertSame(1, Region::query()->where('code', '32.01.01.1001')->count());
    }

    public function test_dry_run_does_not_write_to_database(): void
    {
        $this->artisan('regions:import', [
            'file' => $this->fixture(),
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('regions', 0);
    }

    public function test_orphan_village_is_rejected_and_transaction_rolls_back(): void
    {
        $path = $this->temporaryCsv([
            'level,code,name,parent_code,type',
            'province,32,Jawa Barat,,province',
            'regency,32.01,Bogor,32,kabupaten',
            'district,32.01.01,Cibinong,32.01,district',
            // Induk 32.01.02 tidak ada.
            'village,32.01.02.2001,Wanaherang,32.01.02,village',
        ]);

        $this->artisan('regions:import', ['file' => $path])->assertExitCode(1);

        $this->assertDatabaseCount('regions', 0);
        @unlink($path);
    }

    public function test_invalid_code_format_is_rejected(): void
    {
        $path = $this->temporaryCsv([
            'level,code,name,parent_code,type',
            'province,32,Jawa Barat,,province',
            'regency,32.01,Bogor,32,kabupaten',
            'district,32.01.1,Cibinong,32.01,district',
        ]);

        $this->artisan('regions:import', ['file' => $path])->assertExitCode(1);

        $this->assertDatabaseCount('regions', 0);
        @unlink($path);
    }

    public function test_expected_count_mismatch_is_rejected(): void
    {
        $this->artisan('regions:import', [
            'file' => $this->fixture(),
            '--expected' => 'province:1,regency:1,district:2,village:99',
        ])->assertExitCode(1);

        $this->assertDatabaseCount('regions', 0);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $path = $this->temporaryCsv([
            'level,code,name,parent_code,type',
            'province,32,Jawa Barat,,province',
            'province,32,Jawa Barat Duplikat,,province',
        ]);

        $this->artisan('regions:import', ['file' => $path])->assertExitCode(1);

        $this->assertDatabaseCount('regions', 0);
        @unlink($path);
    }

    public function test_missing_file_is_rejected(): void
    {
        $this->artisan('regions:import', ['file' => base_path('tests/Fixtures/regions/tidak-ada.csv')])
            ->assertExitCode(1);
    }
}
