<?php

namespace Tests\Feature\Publik;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman error publik F7 — 403 dan 404 (Tahap 93).
 *
 * Pengujian memverifikasi bahwa halaman error publik muncul dengan pesan yang
 * ramah, tautan yang berguna, dan **tanpa menampilkan** detail teknis atau stack trace.
 *
 * Catatan bukti: suite berjalan pada **SQLite `:memory:`** — bukan bukti kompatibilitas
 * runtime MariaDB.
 */
class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_not_found_shows_friendly_error_page(): void
    {
        $response = $this->get('/koperasi/slug-tidak-ada-xxx');

        $response->assertNotFound();
        $response->assertSee('404', false);
        $response->assertSee('tidak ditemukan', false);
        $response->assertSee(route('publik.direktori'), false); // Tautan ke direktori
        $response->assertSee('Direktori Koperasi', false);
        $response->assertDontSee('Stack Trace');
        $response->assertDontSee('Exception');
        $response->assertDontSee('debug');
    }

    public function test_404_page_includes_back_links(): void
    {
        $response = $this->get('/path-yang-tidak-ada');

        $response->assertNotFound();
        $response->assertSee(route('publik.direktori'), false);
        $response->assertSee('/', false); // Beranda
    }

    public function test_403_forbidden_shows_friendly_error_page_with_wrong_role(): void
    {
        // Middleware `role:admin_dinas` pada rute /admin akan abort(403) jika
        // user bukan admin_dinas. Tes ini memverifikasi bahwa respons 403 nyata
        // menampilkan halaman error 403 yang ramah, bukan stack trace atau debug.

        // Buat user dengan role pengelola_koperasi (bukan admin_dinas).
        $user = User::factory()->withRole('pengelola_koperasi')->create();

        // Akses rute /admin sebagai pengelola (role mismatch → middleware abort(403)).
        $response = $this->actingAs($user)->get(route('admin.dashboard'));

        // Verifikasi HTTP 403 benar-benar terjadi.
        $response->assertForbidden(); // Status 403
        $response->assertSee('403', false);
        $response->assertSee('ditolak', false);
        $response->assertSee(route('home'), false); // Kembali ke beranda
        $response->assertDontSee('Stack Trace');
        $response->assertDontSee('Exception');
        $response->assertDontSee('debug');
    }

    public function test_404_page_does_not_expose_sensitive_information(): void
    {
        $response = $this->get('/koperasi/sangat-rahasia-123');

        $response->assertNotFound();
        $response->assertDontSee('SQL');
        $response->assertDontSee('LIKE');
        $response->assertDontSee('query');
        $response->assertDontSee('EXPLAIN');
        $response->assertDontSee('SELECT');
    }

    public function test_403_page_renders_with_context_aware_guidance(): void
    {
        // Tes rendering view secara langsung untuk memastikan tidak ada syntax error
        // dan konten ramah pengguna tersedia. View harus menyesuaikan pesan berdasarkan
        // @auth (user login vs. guest).

        // Render sebagai view generic (tidak ada user context).
        $content = view('errors.403')->render();
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('403', $content);
        $this->assertStringContainsString('ditolak', strtolower($content));
        $this->assertStringContainsString('Akses', $content);

        // Pastikan tidak ada tech detail.
        $this->assertStringNotContainsString('Stack Trace', $content);
        $this->assertStringNotContainsString('Exception', $content);
    }

    public function test_404_page_renders_without_error(): void
    {
        $this->assertTrue(
            \File::exists(resource_path('views/errors/404.blade.php')),
            '404.blade.php harus ada'
        );

        // Render view secara langsung untuk memastikan tidak ada syntax error.
        $content = view('errors.404')->render();
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('404', $content);
    }

    public function test_404_page_has_valid_navigation_links(): void
    {
        $response = $this->get('/koperasi/tidak-ada');

        $response->assertNotFound();
        // Periksa bahwa navbar tetap ada (logo, tautan direktori, login/dashboard)
        $response->assertSee('KJ', false); // Logo
        $response->assertSee(config('app.name'), false);
    }

    public function test_419_session_expired_page_is_friendly_and_has_no_technical_details(): void
    {
        $this->assertTrue(
            \File::exists(resource_path('views/errors/419.blade.php')),
            '419.blade.php harus ada',
        );

        $content = view('errors.419')->render();

        $this->assertStringContainsString('419', $content);
        $this->assertStringContainsString('Sesi', $content);
        $this->assertStringContainsString('Masuk Kembali', $content);
        $this->assertStringContainsString(route('publik.direktori'), $content);
        $this->assertStringNotContainsString('Stack Trace', $content);
        $this->assertStringNotContainsString('Exception', $content);
        $this->assertStringNotContainsString('SQLSTATE', $content);
    }

    public function test_500_server_error_page_is_friendly_and_has_no_technical_details(): void
    {
        $this->assertTrue(
            \File::exists(resource_path('views/errors/500.blade.php')),
            '500.blade.php harus ada',
        );

        $content = view('errors.500')->render();

        $this->assertStringContainsString('500', $content);
        $this->assertStringContainsString('kesalahan pada server', $content);
        $this->assertStringContainsString('Direktori Koperasi', $content);
        $this->assertStringNotContainsString('Stack Trace', $content);
        $this->assertStringNotContainsString('Exception', $content);
        $this->assertStringNotContainsString('SQLSTATE', $content);
    }
}
