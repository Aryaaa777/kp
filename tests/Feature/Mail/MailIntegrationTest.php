<?php

namespace Tests\Feature\Mail;

use App\Mail\TestMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tes integrasi mekanisme email Laravel dan konfigurasi SMTP (termasuk Gmail testing).
 *
 * Menguji bahwa:
 * 1. Seluruh pengiriman email aplikasi melalui mekanisme native Laravel Mail / Notification.
 * 2. Konfigurasi Gmail SMTP dapat dimuat secara dinamis via environment variable tanpa hardcoding.
 * 3. Driver SMTP membaca variabel lingkungan secara dinamis.
 * 4. Perintah `mail:test` terlindungi dari eksekusi di lingkungan produksi.
 * 5. Pengujian menggunakan Notification::fake() dan Mail::fake() — tidak ada email sungguhan yang dikirim.
 */
class MailIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_smtp_configuration_is_driven_by_environment_without_hardcoding(): void
    {
        // Pastikan konfigurasi config/mail.php membaca nilai environment secara dinamis
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.dinas-jabar.test');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.mailers.smtp.username', 'dinas_user');
        Config::set('mail.mailers.smtp.password', 'secret_credential');
        Config::set('mail.from.address', 'noreply@koperasi.jabarprov.go.id');
        Config::set('mail.from.name', 'Koperasi Jabar Official');

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.dinas-jabar.test', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('dinas_user', config('mail.mailers.smtp.username'));
        $this->assertSame('secret_credential', config('mail.mailers.smtp.password'));
        $this->assertSame('noreply@koperasi.jabarprov.go.id', config('mail.from.address'));
        $this->assertSame('Koperasi Jabar Official', config('mail.from.name'));
    }

    public function test_gmail_smtp_configuration_mapping(): void
    {
        // Uji pemetaan environment variable untuk konfigurasi Gmail testing
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', 'smtp.gmail.com');
        Config::set('mail.mailers.smtp.port', 587);
        Config::set('mail.mailers.smtp.encryption', 'tls');
        Config::set('mail.mailers.smtp.scheme', 'smtp');
        Config::set('mail.mailers.smtp.username', 'tester@gmail.com');
        Config::set('mail.mailers.smtp.password', 'app_password_dummy');
        Config::set('mail.from.address', 'tester@gmail.com');
        Config::set('mail.from.name', 'Koperasi Jabar Dev');

        $this->assertSame('smtp.gmail.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('tls', config('mail.mailers.smtp.encryption'));
        $this->assertSame('tester@gmail.com', config('mail.mailers.smtp.username'));
        $this->assertSame('tester@gmail.com', config('mail.from.address'));
    }

    public function test_password_reset_uses_laravel_notification_channel_without_real_mail_sent(): void
    {
        Notification::fake();
        Mail::fake();

        $user = User::factory()->pengelola()->create([
            'email' => 'pengelola@koperasi-jabar.test',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) {
            $mailData = $notification->toMail(new User);
            $this->assertNotEmpty($mailData->actionUrl);
            $this->assertStringContainsString('reset-password', $mailData->actionUrl);

            return true;
        });

        Mail::assertNothingSent();
    }

    public function test_admin_triggered_password_reset_uses_laravel_notification(): void
    {
        Notification::fake();
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->pengelola()->create([
            'email' => 'target.pengelola@koperasi-jabar.test',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.akun.reset', $targetUser));

        $response->assertSessionHas('status');

        Notification::assertSentTo($targetUser, ResetPassword::class);
        Mail::assertNothingSent();
    }

    public function test_send_test_mail_command_in_local_environment(): void
    {
        Mail::fake();

        $this->artisan('mail:test', [
            'email' => 'test.receiver@example.test',
            '--force' => true,
        ])
            ->expectsOutputToContain('[BERHASIL] Email uji coba berhasil dikirim')
            ->assertSuccessful();

        Mail::assertSent(TestMail::class, function (TestMail $mail) {
            return $mail->recipientEmail === 'test.receiver@example.test';
        });
    }

    public function test_send_test_mail_command_is_blocked_in_production(): void
    {
        Mail::fake();

        App::detectEnvironment(fn () => 'production');

        $this->artisan('mail:test', [
            'email' => 'test.receiver@example.test',
            '--force' => true,
        ])
            ->expectsOutputToContain('Perintah ini TIDAK boleh dijalankan di lingkungan produksi.')
            ->assertFailed();

        Mail::assertNothingSent();
    }
}
