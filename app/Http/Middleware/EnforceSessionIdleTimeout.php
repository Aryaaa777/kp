<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengakhiri sesi terautentikasi setelah melewati batas idle (FR-003).
 *
 * Batas diambil dari `session.idle_timeout` (menit). Timestamp aktivitas
 * disimpan di sesi, sehingga tidak bergantung pada cookie/session lifetime
 * bawaan Laravel yang lebih panjang.
 */
class EnforceSessionIdleTimeout
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return $next($request);
        }

        $timeoutMinutes = max(1, (int) config('session.idle_timeout', 30));
        $now = now()->getTimestamp();
        $lastActivity = (int) $request->session()->get('auth.last_activity_at', 0);

        if ($lastActivity > 0 && ($now - $lastActivity) > ($timeoutMinutes * 60)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', 'Sesi Anda berakhir karena tidak ada aktivitas. Silakan masuk kembali.');
        }

        $request->session()->put('auth.last_activity_at', $now);

        return $next($request);
    }
}
