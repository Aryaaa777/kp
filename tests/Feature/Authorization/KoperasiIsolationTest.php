<?php

namespace Tests\Feature\Authorization;

use App\Models\Cooperative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Isolasi data antar koperasi (anti-IDOR) dan otorisasi modul data induk (F2).
 */
class KoperasiIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $cooperative = Cooperative::factory()->create();

        $this->get(route('admin.koperasi.index'))->assertRedirect(route('login'));
        $this->get(route('admin.koperasi.show', $cooperative))->assertRedirect(route('login'));
        $this->get(route('pengelola.profil'))->assertRedirect(route('login'));
    }

    public function test_pengelola_sees_only_own_cooperative_profile(): void
    {
        $own = Cooperative::factory()->create(['nama' => 'Koperasi Milik Saya']);
        Cooperative::factory()->create(['nama' => 'Koperasi Milik Orang Lain']);

        $user = User::factory()->pengelola($own->id)->create();

        $this->actingAs($user)
            ->get(route('pengelola.profil'))
            ->assertOk()
            ->assertSee('Koperasi Milik Saya')
            ->assertDontSee('Koperasi Milik Orang Lain');
    }

    public function test_pengelola_without_linked_cooperative_sees_unlinked_page(): void
    {
        $user = User::factory()->pengelola(null)->create();

        $this->actingAs($user)
            ->get(route('pengelola.profil'))
            ->assertOk()
            ->assertSee('belum terhubung');
    }

    public function test_pengelola_whose_cooperative_is_archived_sees_unlinked_page(): void
    {
        $cooperative = Cooperative::factory()->create();
        $user = User::factory()->pengelola($cooperative->id)->create();

        $cooperative->delete();

        $this->actingAs($user)
            ->get(route('pengelola.profil'))
            ->assertOk()
            ->assertSee('tidak ditemukan atau telah diarsipkan');
    }

    public function test_policy_denies_cross_cooperative_access(): void
    {
        $own = Cooperative::factory()->create();
        $other = Cooperative::factory()->create();
        $user = User::factory()->pengelola($own->id)->create();

        $this->assertTrue($user->can('view', $own));
        $this->assertFalse($user->can('view', $other));

        // Pengelola tidak boleh mengubah/menghapus data resmi (perubahan via pengajuan, F5).
        $this->assertFalse($user->can('update', $own));
        $this->assertFalse($user->can('delete', $own));
        $this->assertFalse($user->can('create', Cooperative::class));
        $this->assertFalse($user->can('viewAny', Cooperative::class));
    }

    public function test_admin_policy_allows_full_management(): void
    {
        $cooperative = Cooperative::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($admin->can('viewAny', Cooperative::class));
        $this->assertTrue($admin->can('view', $cooperative));
        $this->assertTrue($admin->can('create', Cooperative::class));
        $this->assertTrue($admin->can('update', $cooperative));
        $this->assertTrue($admin->can('delete', $cooperative));
    }

    public function test_pengelola_cannot_access_admin_koperasi_routes(): void
    {
        $cooperative = Cooperative::factory()->create();
        $user = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($user)->get(route('admin.koperasi.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.koperasi.create'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.koperasi.show', $cooperative))->assertForbidden();
        $this->actingAs($user)->post(route('admin.koperasi.store'), [])->assertForbidden();
        $this->actingAs($user)->put(route('admin.koperasi.update', $cooperative), [])->assertForbidden();
        $this->actingAs($user)->delete(route('admin.koperasi.destroy', $cooperative), [])->assertForbidden();
    }

    public function test_pengelola_cannot_change_status_via_admin_endpoint(): void
    {
        $cooperative = Cooperative::factory()->create(['status_verifikasi' => 'draft']);
        $user = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($user)
            ->put(route('admin.koperasi.update', $cooperative), ['status_verifikasi' => 'approved'])
            ->assertForbidden();

        $this->assertSame('draft', $cooperative->fresh()->status_verifikasi);
    }

    public function test_admin_can_access_any_cooperative_detail(): void
    {
        $cooperative = Cooperative::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.koperasi.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.koperasi.show', $cooperative))->assertOk();
    }
}
