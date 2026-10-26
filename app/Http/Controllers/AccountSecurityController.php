<?php

namespace App\Http\Controllers;

use App\Services\ActiveSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Halaman keamanan akun: batas idle & daftar sesi aktif (FR-003).
 */
class AccountSecurityController extends Controller
{
    public function show(Request $request, ActiveSessionService $sessions): View
    {
        $currentSessionId = (string) $request->session()->getId();

        return view('account.security', [
            'idleTimeoutMinutes' => max(1, (int) config('session.idle_timeout', 30)),
            'supportsSessionListing' => $sessions->supportsListing(),
            'sessions' => $sessions->forUser($request->user(), $currentSessionId),
            'currentSessionId' => $currentSessionId,
            'currentIp' => $request->ip(),
            'currentUserAgent' => $request->userAgent(),
        ]);
    }

    public function destroyOthers(Request $request, ActiveSessionService $sessions): RedirectResponse
    {
        if (! $sessions->supportsListing()) {
            return back()->with('error', 'Driver sesi saat ini tidak mendukung daftar sesi lintas perangkat.');
        }

        $currentSessionId = (string) $request->session()->getId();

        if ($currentSessionId === '') {
            return back()->with('error', 'Sesi saat ini tidak dapat diidentifikasi. Silakan masuk kembali.');
        }

        $deleted = $sessions->terminateOthers($request->user(), $currentSessionId);

        return back()->with(
            'status',
            $deleted > 0
                ? "{$deleted} sesi lain telah diakhiri. Perangkat tersebut akan diminta masuk kembali."
                : 'Tidak ada sesi lain yang perlu diakhiri.',
        );
    }

    public function destroyAll(Request $request, ActiveSessionService $sessions): RedirectResponse
    {
        if (! $sessions->supportsListing()) {
            return back()->with('error', 'Driver sesi saat ini tidak mendukung pengakhiran seluruh sesi lintas perangkat.');
        }

        $sessions->terminateAll($request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('status', 'Seluruh sesi telah diakhiri. Silakan masuk kembali.');
    }
}
