<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Pemeriksaan kesiapan produksi (read-only).
 */
class ProductionCheckTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function productionConfig(): array
    {
        return [
            'app.env' => 'production',
            'app.debug' => false,
            'app.key' => 'base64:test-key',
            'app.url' => 'https://koperasi.example.go.id',
            'app.timezone' => 'Asia/Jakarta',
            'app.locale' => 'id',
            'session.driver' => 'database',
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
            'session.idle_timeout' => 30,
            'session.encrypt' => true,
            'mail.default' => 'smtp',
            'mail.from.address' => 'noreply@example.go.id',
            'cache.default' => 'database',
            'queue.default' => 'database',
            'koperasi.document_disk' => 'local',
            'security.hsts.enabled' => true,
            'security.csp.enabled' => true,
            'security.headers.x_frame_options' => 'DENY',
        ];
    }

    private function applyProductionConfig(): void
    {
        foreach ($this->productionConfig() as $key => $value) {
            config()->set($key, $value);
        }
    }

    public function test_it_fails_when_application_is_not_configured_for_production(): void
    {
        config()->set('app.env', 'local');
        config()->set('app.debug', true);

        $this->artisan('app:production-check', ['--skip-database' => true])
            ->expectsOutputToContain('BELUM siap produksi')
            ->assertExitCode(1);
    }

    public function test_it_passes_required_configuration_checks_when_database_is_skipped(): void
    {
        $this->applyProductionConfig();

        $this->artisan('app:production-check', ['--skip-database' => true])
            ->expectsOutputToContain('0 FAIL')
            ->assertExitCode(0);
    }

    public function test_json_output_is_valid_and_contains_summary(): void
    {
        $this->applyProductionConfig();

        $exitCode = Artisan::call('app:production-check', [
            '--skip-database' => true,
            '--json' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $decoded = json_decode(trim($output), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('production', $decoded['environment']);
        $this->assertArrayHasKey('summary', $decoded);
        $this->assertArrayHasKey('checks', $decoded);
        $this->assertSame(0, $decoded['summary']['fail']);
        $this->assertNotEmpty($decoded['checks']);
    }
}
