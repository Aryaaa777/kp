<?php

namespace Tests\Feature\Navigation;

use App\Models\Cooperative;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tes kelengkapan dan stabilitas menu navigasi sidebar untuk setiap role (Admin Dinas & Pengelola Koperasi).
 * Memastikan tidak ada menu yang hilang ketika berpindah route/halaman.
 */
class NavigationMenuIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_menu_stays_intact_across_all_admin_pages(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'arya']);
        $cooperative = Cooperative::factory()->create();

        $routesToVisit = [
            route('admin.dashboard'),
            route('admin.koperasi.index'),
            route('admin.koperasi.show', $cooperative),
            route('admin.koperasi.edit', $cooperative),
            route('admin.koperasi.create'),
            route('admin.akun.index'),
            route('admin.pengaturan.publik'),
            route('admin.laporan.index'),
            route('admin.pengajuan.index'),
            route('admin.audit-log.index'),
            route('account.security'),
        ];

        $expectedAdminMenus = [
            'Dashboard',
            'Data Koperasi',
            'Akun Pengelola',
            'Pengaturan Publik',
            'Laporan',
            'Pengajuan',
            'Audit Log',
        ];

        foreach ($routesToVisit as $url) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertOk();

            foreach ($expectedAdminMenus as $menuLabel) {
                $response->assertSee($menuLabel, false);
            }
        }
    }

    public function test_pengelola_sidebar_menu_stays_intact_across_all_pengelola_pages(): void
    {
        $cooperative = Cooperative::factory()->create();
        $pengelola = User::factory()->pengelola()->create([
            'koperasi_id' => $cooperative->id,
        ]);

        $routesToVisit = [
            route('pengelola.dashboard'),
            route('pengelola.profil'),
            route('pengelola.izin.index'),
            route('pengelola.pengurus.index'),
            route('pengelola.kesehatan.index'),
            route('pengelola.tata-kelola.index'),
            route('pengelola.aset.index'),
            route('pengelola.anggota.index'),
            route('pengelola.pengajuan.index'),
            route('pengelola.notifikasi.index'),
            route('account.security'),
        ];

        $expectedPengelolaMenus = [
            'Dashboard',
            'Profil Koperasi',
            'Perizinan &amp; Dokumen',
            'Pengurus',
            'Kesehatan',
            'Tata Kelola',
            'Aset',
            'Anggota',
            'Pengajuan',
            'Pemberitahuan',
        ];

        foreach ($routesToVisit as $url) {
            $response = $this->actingAs($pengelola)->get($url);
            $response->assertOk();

            foreach ($expectedPengelolaMenus as $menuLabel) {
                $response->assertSee($menuLabel, false);
            }
        }
    }
}
