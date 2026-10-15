<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan pengguna terautentikasi masih memiliki akun berstatus aktif.
 *
 * Jika akun dinonaktifkan saat sesi masih berjalan, sesi diputus dan
 * pengguna diarahkan kembali ke halaman login.
 */
class EnsureUserIsActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && ! $request->user()->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Akun Anda dinonaktifkan. Silakan hubungi admin dinas.']);
        }

        return $next($request);
    }
}
