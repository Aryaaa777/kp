<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Membuktikan isolasi data antar koperasi (anti-IDOR).
 */
class KoperasiIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengelola_can_access_own_koperasi(): void
    {
        $user = User::factory()->pengelola(10)->create();

        $this->actingAs($user)->get('/dashboard/koperasi/10')->assertOk();
    }

    public function test_pengelola_cannot_access_another_koperasi(): void
    {
        $user = User::factory()->pengelola(10)->create();

        $this->actingAs($user)->get('/dashboard/koperasi/11')->assertForbidden();
    }

    public function test_pengelola_without_linked_koperasi_cannot_access_any(): void
    {
        $user = User::factory()->pengelola(null)->create();

        $this->actingAs($user)->get('/dashboard/koperasi/1')->assertForbidden();
    }

    public function test_admin_can_access_any_koperasi(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get('/dashboard/koperasi/999')->assertOk();
    }

    public function test_guest_cannot_access_koperasi_route(): void
    {
        $this->get('/dashboard/koperasi/1')->assertRedirect(route('login'));
    }

    public function test_non_numeric_koperasi_id_returns_not_found(): void
    {
        $user = User::factory()->pengelola(1)->create();

        $this->actingAs($user)->get('/dashboard/koperasi/abc')->assertNotFound();
    }
}
