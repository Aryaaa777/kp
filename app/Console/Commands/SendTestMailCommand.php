<?php

namespace App\Console\Commands;

use App\Mail\TestMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Perintah eksplisit untuk menguji pengiriman email nyata di lingkungan local/development.
 *
 * PERHATIAN KEAMANAN:
 * - Tidak boleh dijalankan di lingkungan production.
 * - Password / kredensial tidak pernah ditampilkan di output konsol.
 */
class SendTestMailCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'mail:test
                            {email : Alamat email penerima pengujian}
                            {--force : Lewati konfirmasi interaktif}';

    /**
     * @var string
     */
    protected $description = 'Kirim email uji coba nyata secara eksplisit (hanya di lingkungan local/development)';

    public function handle(): int
    {
        if (App::isProduction()) {
            $this->error('Perintah ini TIDAK boleh dijalankan di lingkungan produksi.');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Format email '{$email}' tidak valid.");

            return self::INVALID;
        }

        $mailer = config('mail.default');
        $host = config('mail.mailers.smtp.host');
        $port = config('mail.mailers.smtp.port');
        $encryption = config('mail.mailers.smtp.encryption') ?: (config('mail.mailers.smtp.scheme') ?: 'none/starttls');
        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        $this->info('Pengujian Pengiriman Email (Hanya untuk Development/Testing)');
        $this->line('-----------------------------------------------------------');
        $this->line("Mailer:         {$mailer}");
        $this->line("Host:           {$host}");
        $this->line("Port:           {$port}");
        $this->line("Encryption:     {$encryption}");
        $this->line("From Address:   {$fromAddress}");
        $this->line("From Name:      {$fromName}");
        $this->line("Penerima:       {$email}");
        $this->line('-----------------------------------------------------------');

        if (! $this->option('force') && ! $this->confirm('Kirim email pengujian sekarang?', false)) {
            $this->warn('Pengiriman email dibatalkan oleh pengguna.');

            return self::SUCCESS;
        }

        $this->line('Mengirim email uji coba...');

        try {
            Mail::to($email)->send(new TestMail(
                recipientEmail: $email,
                timestamp: now()->toDateTimeString(),
            ));

            $this->info("[BERHASIL] Email uji coba berhasil dikirim ke {$email}.");
            $this->line('Periksa kotak masuk (inbox) atau folder spam pada akun email tujuan.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            // Catat kegagalan ke log sistem
            report($e);

            $this->error('[GAGAL] Terjadi kesalahan saat mengirim email:');
            // Tampilkan pesan error ringkas tanpa membocorkan kredensial
            $safeMessage = preg_replace('/password=[^&\s]+/i', 'password=***', $e->getMessage());
            $this->line($safeMessage);

            $this->line('');
            $this->warn('Tips Troubleshooting:');
            $this->line('- Pastikan internet terhubung dan port SMTP tidak diblokir.');
            $this->line('- Jika menggunakan Gmail, pastikan 2FA aktif dan gunakan App Password 16 karakter.');
            $this->line('- Jalankan `php artisan config:clear` setelah mengubah file .env.');

            return self::FAILURE;
        }
    }
}
