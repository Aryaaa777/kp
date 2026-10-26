<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Permintaan tautan reset password (FR-004).
 *
 * Respons selalu generik, baik email terdaftar maupun tidak, agar keberadaan
 * akun tidak dapat ditebak. Token dibuat & disimpan sebagai hash oleh password
 * broker Laravel; token mentah hanya dikirim ke email.
 */
class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = Str::lower((string) $request->input('email'));
        $throttleKey = 'password-reset-link:'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => "Terlalu banyak permintaan reset. Silakan coba lagi dalam {$seconds} detik."]);
        }

        RateLimiter::hit($throttleKey, 600);

        try {
            // Status broker tidak diteruskan ke pengguna: INVALID_USER dan
            // RESET_THROTTLED sengaja menghasilkan pesan yang sama.
            Password::sendResetLink(['email' => $email]);
        } catch (Throwable $exception) {
            // Jangan membocorkan detail transport email ke pengguna. Kegagalan
            // tetap dicatat untuk operator; pesan respons tetap generik.
            report($exception);
        }

        return back()->with(
            'status',
            'Jika alamat email terdaftar, tautan reset password telah dikirim. Periksa kotak masuk dan folder spam.',
        );
    }
}
