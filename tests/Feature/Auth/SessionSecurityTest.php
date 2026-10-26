<?php

namespace Tests\Feature\Auth;

use App\Models\Cooperative;
use App\Models\User;
use App\Services\ActiveSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Keamanan sesi (FR-003): idle timeout, daftar sesi, dan pengakhiran sesi.
 */
class SessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_account_security(): void
    {
        $this->get(route('account.security'))->assertRedirect(route('login'));
    }

    public function test_pengelola_can_open_account_security(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)
            ->get(route('account.security'))
            ->assertOk()
            ->assertSee('Batas waktu tidak aktif');
    }

    public function test_idle_session_is_logged_out(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->withSession(['auth.last_activity_at' => now()->subMinutes(31)->timestamp])
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_activity_refreshes_the_idle_timestamp(): void
    {
        $user = User::factory()->admin()->create();
        $oldActivity = now()->subMinutes(29)->timestamp;

        $this->actingAs($user)
            ->withSession(['auth.last_activity_at' => $oldActivity])
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertGreaterThan($oldActivity, (int) session('auth.last_activity_at'));
    }

    public function test_security_page_contains_csrf_protected_forms(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('account.security'))
            ->assertOk()
            ->assertSee('_token', false);
    }

    public function test_array_session_driver_reports_listing_limitation(): void
    {
        config(['session.driver' => 'array']);

        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('account.security'))
            ->assertOk()
            ->assertSee('tidak menyimpan metadata lintas perangkat');

        $this->actingAs($user)
            ->delete(route('account.security.sessions.destroy-others'))
            ->assertSessionHas('error');
    }

    public function test_user_can_terminate_other_sessions_only(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get(route('account.security'))->assertOk();
        $currentId = (string) $this->app['session']->getId();

        DB::table('sessions')->updateOrInsert(
            ['id' => $currentId],
            [
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Perangkat Saat Ini',
                'payload' => 'payload',
                'last_activity' => now()->timestamp,
            ],
        );

        DB::table('sessions')->insert([
            'id' => 'sesi-lain',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.9',
            'user_agent' => 'Perangkat Lain',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->get(route('account.security'))
            ->assertOk()
            ->assertSee('10.0.0.9');

        $this->actingAs($user)
            ->delete(route('account.security.sessions.destroy-others'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-lain']);
    }

    public function test_active_session_service_keeps_the_current_session(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->admin()->create();

        foreach (['sesi-ini', 'sesi-lain'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Browser',
                'payload' => 'payload',
                'last_activity' => now()->timestamp,
            ]);
        }

        $deleted = app(ActiveSessionService::class)->terminateOthers($user, 'sesi-ini');

        $this->assertSame(1, $deleted);
        $this->assertDatabaseHas('sessions', ['id' => 'sesi-ini']);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-lain']);
    }

    public function test_user_can_terminate_all_sessions_and_is_logged_out(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->admin()->create();

        DB::table('sessions')->insert([
            'id' => 'sesi-akan-dihapus',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($user)
            ->delete(route('account.security.sessions.destroy-all'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-akan-dihapus']);
    }
}
