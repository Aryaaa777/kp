<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable uji coba untuk verifikasi koneksi transport email di lingkungan local/development.
 */
class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientEmail,
        public readonly string $timestamp,
        public readonly array $serverInfo = []
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Uji Coba Pengiriman Email - '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '
                <div style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; rounded: 8px;">
                    <h2 style="color: #059669; margin-top: 0;">Uji Coba Pengiriman Email Berhasil</h2>
                    <p>Halo,</p>
                    <p>Email ini dikirim secara eksplisit dari perintah pengujian lokal/development Sistem Informasi Koperasi Jawa Barat.</p>
                    <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;">
                    <p style="margin-bottom: 5px;"><strong>Detail Pengujian:</strong></p>
                    <ul style="padding-left: 20px;">
                        <li><strong>Penerima:</strong> '.htmlspecialchars($this->recipientEmail, ENT_QUOTES, 'UTF-8').'</li>
                        <li><strong>Waktu Kirim:</strong> '.htmlspecialchars($this->timestamp, ENT_QUOTES, 'UTF-8').'</li>
                        <li><strong>Driver Mail:</strong> '.htmlspecialchars(config('mail.default'), ENT_QUOTES, 'UTF-8').'</li>
                        <li><strong>Host SMTP:</strong> '.htmlspecialchars((string) config('mail.mailers.smtp.host'), ENT_QUOTES, 'UTF-8').'</li>
                        <li><strong>Port:</strong> '.htmlspecialchars((string) config('mail.mailers.smtp.port'), ENT_QUOTES, 'UTF-8').'</li>
                    </ul>
                    <p style="font-size: 13px; color: #64748b; margin-top: 25px;">
                        <em>Pesan ini dihasilkan secara otomatis untuk keperluan pengujian koneksi SMTP. Jangan membalas email ini.</em>
                    </p>
                </div>
            ',
        );
    }
}
