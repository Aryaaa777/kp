<?php

namespace Tests\Feature\Admin;

use App\Models\Cooperative;
use App\Models\Region;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Manajemen akun pengelola melalui UI (US-009).
 */
class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_guest_is_redirected_from_account_management(): void
    {
        $this->get(route('admin.akun.index'))->assertRedirect(route('login'));
    }

    public function test_pengelola_cannot_access_account_management(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperative->id)->create();

        $this->actingAs($pengelola)
            ->get(route('admin.akun.index'))
            ->assertForbidden();

        $this->actingAs($pengelola)
            ->post(route('admin.akun.store'), [
                'name' => 'Akun Tidak Sah',
                'email' => 'tidak-sah@example.test',
                'role' => 'admin_dinas',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_search_accounts_by_name_email_or_cooperative(): void
    {
        $admin = $this->admin();
        $cooperative = Cooperative::factory()->create(['nama' => 'Koperasi Pencarian Khusus']);
        $pengelola = User::factory()->pengelola($cooperative->id)->create([
            'name' => 'Pengelola Pencarian',
            'email' => 'pencarian@example.test',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.akun.index', ['q' => 'Pencarian Khusus']))
            ->assertOk()
            ->assertSee('Pengelola Pencarian')
            ->assertSee('Koperasi Pencarian Khusus');

        $this->actingAs($admin)
            ->get(route('admin.akun.index', ['q' => $pengelola->email]))
            ->assertOk()
            ->assertSee('Pengelola Pencarian');
    }

    public function test_admin_can_create_pengelola_with_cooperative_without_exposing_password(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.akun.store'), [
                'name' => 'Pengelola Baru',
                'email' => 'pengelola-baru@example.test',
                'role' => 'pengelola_koperasi',
                'koperasi_id' => $cooperative->id,
            ])
            ->assertRedirect(route('admin.akun.index'))
            ->assertSessionHas('status');

        $user = User::where('email', 'pengelola-baru@example.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('pengelola_koperasi'));
        $this->assertSame($cooperative->id, $user->koperasi_id);
        $this->assertSame('active', $user->status);
        $this->assertNotSame('', $user->password);
        $this->assertNotSame('unknown', password_get_info($user->password)['algoName']);

        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'create_user',
            'subject_id' => $user->id,
        ]);
    }

    public function test_admin_creation_forces_koperasi_to_null(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $cooperative = Cooperative::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.akun.store'), [
                'name' => 'Admin Baru',
                'email' => 'admin-baru@example.test',
                'role' => 'admin_dinas',
                'koperasi_id' => $cooperative->id,
            ])
            ->assertRedirect(route('admin.akun.index'));

        $user = User::where('email', 'admin-baru@example.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('admin_dinas'));
        $this->assertNull($user->koperasi_id);
    }

    public function test_create_validates_role_email_and_cooperative(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.akun.store'), [
                'name' => 'Akun Salah',
                'email' => 'bukan-email',
                'role' => 'masyarakat',
                'koperasi_id' => 999999,
            ])
            ->assertSessionHasErrors(['email', 'role', 'koperasi_id']);

        $this->assertDatabaseMissing('users', ['name' => 'Akun Salah']);
    }

    public function test_create_rejects_a_soft_deleted_cooperative(): void
    {
        $admin = $this->admin();
        $cooperative = Cooperative::factory()->create();
        $cooperative->delete();

        $this->actingAs($admin)
            ->post(route('admin.akun.store'), [
                'name' => 'Pengelola Arsip',
                'email' => 'pengelola-arsip@example.test',
                'role' => 'pengelola_koperasi',
                'koperasi_id' => $cooperative->id,
            ])
            ->assertSessionHasErrors('koperasi_id');

        $this->assertDatabaseMissing('users', ['email' => 'pengelola-arsip@example.test']);
    }

    public function test_update_rejects_a_soft_deleted_cooperative(): void
    {
        $admin = $this->admin();
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperativeA->id)->create();

        $cooperativeB->delete();

        $this->actingAs($admin)
            ->put(route('admin.akun.update', $pengelola), [
                'name' => $pengelola->name,
                'email' => $pengelola->email,
                'role' => 'pengelola_koperasi',
                'koperasi_id' => $cooperativeB->id,
            ])
            ->assertSessionHasErrors('koperasi_id');

        $this->assertSame($cooperativeA->id, $pengelola->fresh()->koperasi_id);
    }

    public function test_admin_can_update_role_and_cooperative(): void
    {
        $admin = $this->admin();
        $cooperativeA = Cooperative::factory()->create();
        $cooperativeB = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola($cooperativeA->id)->create();

        $this->actingAs($admin)
            ->put(route('admin.akun.update', $pengelola), [
                'name' => 'Pengelola Diubah',
                'email' => 'pengelola-diubah@example.test',
                'role' => 'pengelola_koperasi',
                'koperasi_id' => $cooperativeB->id,
            ])
            ->assertRedirect(route('admin.akun.index'));

        $pengelola->refresh();

        $this->assertSame('Pengelola Diubah', $pengelola->name);
        $this->assertSame($cooperativeB->id, $pengelola->koperasi_id);
        $this->assertTrue($pengelola->hasRole('pengelola_koperasi'));

        $this->actingAs($admin)
            ->put(route('admin.akun.update', $pengelola), [
                'name' => 'Pengelola Diubah',
                'email' => 'pengelola-diubah@example.test',
                'role' => 'admin_dinas',
                'koperasi_id' => $cooperativeB->id,
            ])
            ->assertRedirect(route('admin.akun.index'));

        $pengelola->refresh();

        $this->assertTrue($pengelola->hasRole('admin_dinas'));
        $this->assertNull($pengelola->koperasi_id);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->put(route('admin.akun.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'pengelola_koperasi',
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->hasRole('admin_dinas'));
    }

    public function test_admin_can_deactivate_another_account_and_terminate_its_sessions(): void
    {
        config(['session.driver' => 'database']);

        $admin = $this->admin();
        $target = User::factory()->pengelola()->create();

        DB::table('sessions')->insert([
            'id' => 'sesi-target',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test Browser',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.akun.status', $target), ['status' => 'inactive'])
            ->assertSessionHas('status');

        $this->assertSame('inactive', $target->fresh()->status);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-target']);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'deactivate_user',
            'subject_id' => $target->id,
        ]);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.akun.status', $admin), ['status' => 'inactive'])
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_admin_can_send_reset_link_without_exposing_password(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $target = User::factory()->pengelola()->create();

        $this->actingAs($admin)
            ->post(route('admin.akun.reset', $target))
            ->assertSessionHas('status');

        Notification::assertSentTo($target, ResetPassword::class);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'request_user_password_reset',
            'subject_id' => $target->id,
        ]);
    }

    public function test_admin_can_create_admin_wilayah_with_region(): void
    {
        $admin = $this->admin();
        $region = Region::factory()->create(['type' => 'regency']);

        $this->actingAs($admin)
            ->post(route('admin.akun.store'), [
                'name' => 'Admin Wilayah Uji',
                'email' => 'wilayah@example.test',
                'role' => 'admin_wilayah',
                'region_id' => $region->id,
            ])
            ->assertRedirect(route('admin.akun.index'));

        $user = User::query()->where('email', 'wilayah@example.test')->firstOrFail();

        $this->assertSame($region->id, $user->region_id);
        $this->assertNull($user->koperasi_id);
        $this->assertTrue($user->hasRole('admin_wilayah'));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'create_user',
            'subject_id' => $user->id,
        ]);
    }

    public function test_admin_wilayah_requires_region(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.akun.store'), [
                'name' => 'Admin Tanpa Wilayah',
                'email' => 'tanpa-wilayah@example.test',
                'role' => 'admin_wilayah',
            ])
            ->assertSessionHasErrors('region_id');

        $this->assertDatabaseMissing('users', ['email' => 'tanpa-wilayah@example.test']);
    }

    public function test_admin_wilayah_cannot_access_account_management(): void
    {
        $region = Region::factory()->create(['type' => 'regency']);
        $adminWilayah = User::factory()->adminWilayah($region->id)->create();

        $this->actingAs($adminWilayah)
            ->get(route('admin.akun.index'))
            ->assertForbidden();

        $this->actingAs($adminWilayah)
            ->get(route('admin.pengaturan.publik'))
            ->assertForbidden();
    }
}
