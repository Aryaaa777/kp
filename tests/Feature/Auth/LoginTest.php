<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible_for_guests(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Masuk ke akun');
    }

    public function test_admin_is_redirected_to_admin_dashboard_after_login(): void
    {
        $user = User::factory()->admin()->create(['password' => 'rahasia-panjang']);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia-panjang'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
    }

    public function test_pengelola_is_redirected_to_pengelola_dashboard_after_login(): void
    {
        $user = User::factory()->pengelola(7)->create(['password' => 'rahasia-panjang']);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia-panjang'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->get(route('dashboard'))->assertRedirect(route('pengelola.dashboard'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $user = User::factory()->admin()->create(['password' => 'rahasia-panjang']);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->admin()->inactive()->create(['password' => 'rahasia-panjang']);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'rahasia-panjang'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_last_login_at_is_recorded_on_successful_login(): void
    {
        $user = User::factory()->admin()->create(['password' => 'rahasia-panjang']);

        $this->assertNull($user->last_login_at);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia-panjang']);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->admin()->create(['password' => 'rahasia-panjang']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia-panjang'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_away_from_login_page(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect(route('dashboard'));
    }
}
