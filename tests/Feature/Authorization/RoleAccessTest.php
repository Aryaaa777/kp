<?php

namespace Tests\Feature\Authorization;

use App\Models\Cooperative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_admin_dashboard(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_from_pengelola_dashboard(): void
    {
        $this->get('/koperasi/dashboard')->assertRedirect(route('login'));
    }

    public function test_pengelola_cannot_access_admin_area(): void
    {
        $cooperative = Cooperative::factory()->create();
        $user = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_admin_cannot_access_pengelola_area(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get('/koperasi/dashboard')->assertForbidden();
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get('/admin/dashboard')->assertOk();
    }

    public function test_pengelola_can_access_pengelola_dashboard(): void
    {
        $cooperative = Cooperative::factory()->create();
        $user = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($user)->get('/koperasi/dashboard')->assertOk();
    }

    public function test_user_without_any_role_is_forbidden_on_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    public function test_inactive_authenticated_user_is_logged_out_by_active_middleware(): void
    {
        $user = User::factory()->admin()->inactive()->create();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
