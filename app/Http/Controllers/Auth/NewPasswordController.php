<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\ActiveSessionService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Penyelesaian reset password (FR-004).
 *
 * Token kedaluwarsa, tidak valid, atau sudah dipakai menghasilkan pesan generik.
 * Setelah berhasil, remember token diganti dan (bila session driver database)
 * seluruh sesi pengguna dihapus agar sesi lama tidak tetap aktif.
 */
class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(ResetPasswordRequest $request, ActiveSessionService $sessions): RedirectResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request, $sessions): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Hapus sesi server-side bila driver mendukungnya. Untuk driver lain,
                // remember token yang berubah tetap memutus "remember me" lama.
                $sessions->terminateAll($user);

                event(new PasswordReset($user));

                if ($request->user() !== null && $request->user()->is($user)) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('status', 'Password berhasil direset. Silakan masuk dengan password baru Anda.');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Tautan reset password tidak valid atau sudah kedaluwarsa.']);
    }
}
