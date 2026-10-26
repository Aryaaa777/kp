<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tes regresi wildcard escaping untuk pencarian ActivityLog (Tahap 103, GAP-02).
 *
 * Memastikan karakter `%`, `_`, dan `!` diperlakukan sebagai literal dalam
 * pencarian deskripsi aktivitas, bukan wildcard SQL LIKE.
 */
class ActivityLogWildcardSearchTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_search_treats_percent_literal_in_activity_description(): void
    {
        ActivityLog::factory()->create([
            'action' => 'test_action_1',
            'description' => 'Aktivitas dengan % literal',
        ]);
        ActivityLog::factory()->create([
            'action' => 'test_action_2',
            'description' => 'Aktivitas biasa tanpa khusus',
        ]);

        // Search "dengan %" → hanya cocok literal "dengan %", bukan wildcard
        $response = $this->actingAs($this->admin())
            ->get(route('admin.audit-log.index', ['q' => 'dengan %']))
            ->assertOk();

        $response->assertSee('Aktivitas dengan % literal');
        $response->assertDontSee('Aktivitas biasa tanpa khusus');
    }

    public function test_search_treats_underscore_literal_in_activity_description(): void
    {
        ActivityLog::factory()->create([
            'action' => 'test_action_a',
            'description' => 'Log_entry dengan underscore literal',
        ]);
        ActivityLog::factory()->create([
            'action' => 'test_action_b',
            'description' => 'Logentry tanpa underscore',
        ]);

        // Search "Log_" → hanya cocok literal "Log_", bukan wildcard (_ = any char)
        $response = $this->actingAs($this->admin())
            ->get(route('admin.audit-log.index', ['q' => 'Log_']))
            ->assertOk();

        $response->assertSee('Log_entry dengan underscore literal');
        $response->assertDontSee('Logentry tanpa underscore');
    }

    public function test_search_treats_exclamation_literal_in_activity_description(): void
    {
        ActivityLog::factory()->create([
            'action' => 'test_action_x',
            'description' => 'Aksi! dengan exclamation mark',
        ]);
        ActivityLog::factory()->create([
            'action' => 'test_action_y',
            'description' => 'Aksi dengan tanda seru biasa',
        ]);

        // Search "Aksi!" → hanya cocok literal "Aksi!"
        $response = $this->actingAs($this->admin())
            ->get(route('admin.audit-log.index', ['q' => 'Aksi!']))
            ->assertOk();

        $response->assertSee('Aksi! dengan exclamation mark');
        $response->assertDontSee('Aksi dengan tanda seru biasa');
    }

    public function test_search_normal_text_in_activity_description_still_works(): void
    {
        ActivityLog::factory()->create([
            'action' => 'update_data',
            'description' => 'Data koperasi diperbarui',
        ]);
        ActivityLog::factory()->create([
            'action' => 'create_data',
            'description' => 'Koperasi baru dibuat',
        ]);

        // Normal search tanpa special char tetap bekerja
        $response = $this->actingAs($this->admin())
            ->get(route('admin.audit-log.index', ['q' => 'diperbarui']))
            ->assertOk();

        $response->assertSee('Data koperasi diperbarui');
        $response->assertDontSee('Koperasi baru dibuat');
    }

    public function test_search_with_action_filter_still_works(): void
    {
        ActivityLog::factory()->create([
            'action' => 'publish',
            'description' => 'Aktivitas publikasi dilakukan',
        ]);
        ActivityLog::factory()->create([
            'action' => 'archive',
            'description' => 'Aktivitas pengarsipan dilakukan',
        ]);

        // Pencarian + filter action tetap bekerja
        $response = $this->actingAs($this->admin())
            ->get(route('admin.audit-log.index', [
                'q' => 'dilakukan',
                'action' => 'publish',
            ]))
            ->assertOk();

        $response->assertSee('Aktivitas publikasi dilakukan');
        $response->assertDontSee('Aktivitas pengarsipan dilakukan');
    }
}
