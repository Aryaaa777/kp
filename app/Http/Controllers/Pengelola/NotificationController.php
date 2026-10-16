<?php

namespace App\Http\Controllers\Pengelola;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notifikasi in-app status pengajuan (FR-201).
 *
 * Hanya menampilkan notifikasi milik pengguna yang sedang login (tabel `notifications` Laravel).
 * Kanal email/WA ditunda (Q-011).
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('pengelola.notifikasi.index', [
            'notifications' => $request->user()->notifications()->paginate(15),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Semua pemberitahuan ditandai telah dibaca.');
    }
}
