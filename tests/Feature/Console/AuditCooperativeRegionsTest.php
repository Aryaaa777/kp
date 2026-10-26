<?php

namespace Tests\Feature\Console;

use App\Models\Cooperative;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditCooperativeRegionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_reports_null_and_non_village_regions_without_personal_data(): void
    {
        $province = Region::factory()->create(['type' => 'province', 'parent_id' => null]);
        $regency = Region::factory()->create(['type' => 'regency', 'parent_id' => $province->id]);
        $district = Region::factory()->create(['type' => 'district', 'parent_id' => $regency->id]);
        $village = Region::factory()->create(['type' => 'village', 'parent_id' => $district->id]);

        $withVillage = Cooperative::factory()->create(['region_id' => $village->id]);
        $withRegency = Cooperative::factory()->create(['region_id' => $regency->id]);
        $withoutRegion = Cooperative::factory()->create(['region_id' => null]);

        $path = tempnam(sys_get_temp_dir(), 'audit_').'.json';

        $this->artisan('regions:audit-cooperatives', ['--json' => $path])
            ->assertExitCode(0);

        $this->assertFileExists($path);

        $report = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(3, $report['totals']['cooperatives']);
        $this->assertSame(2, $report['totals']['with_region']);
        $this->assertSame(1, $report['totals']['without_region']);
        $this->assertSame(1, $report['totals']['village_level']);
        $this->assertSame(1, $report['totals']['non_village_level']);

        $exceptionIds = array_column($report['exceptions'], 'cooperative_id');
        $this->assertEqualsCanonicalizing(
            [$withRegency->id, $withoutRegion->id],
            $exceptionIds,
        );

        $this->assertArrayNotHasKey('nama', $report['exceptions'][0]);
        $this->assertArrayNotHasKey('telephone', $report['exceptions'][0]);
        $this->assertArrayNotHasKey('email', $report['exceptions'][0]);

        $this->assertNotContains($withVillage->id, $exceptionIds);

        @unlink($path);
    }
}
