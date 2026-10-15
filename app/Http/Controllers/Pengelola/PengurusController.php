<?php

namespace App\Http\Controllers\Pengelola;

use App\Http\Controllers\Controller;
use App\Models\Officer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller pengelola untuk melihat data pengurus koperasinya sendiri (read-only).
 *
 * FR-120/121: Pengelola dapat melihat susunan pengurus koperasinya.
 *
 * Di F4, pengelola hanya read-only (tidak dapat create/update/delete).
 * Write access akan dibuka via workflow pengajuan (F5).
 *
 * Otorisasi: pengelola_koperasi (scoped ke koperasinya sendiri).
 *
 * @see docs/ROADMAP_REMAINING.md F4 (pengelola read-only di F4)
 */
class PengurusController extends Controller
{
    use AuthorizesRequests;

    /**
     * Tampilkan daftar pengurus koperasi sendiri.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Officer::class);

        $user = $request->user();

        if ($user->koperasi_id === null) {
            abort(403, 'Akun Anda belum terhubung ke koperasi. Hubungi admin dinas.');
        }

        $officers = Officer::where('cooperative_id', $user->koperasi_id)
            ->orderByDesc('is_active')
            ->orderBy('period_start', 'desc')
            ->paginate(15);

        return view('pengelola.pengurus.index', [
            'officers' => $officers,
        ]);
    }

    /**
     * Tampilkan detail pengurus.
     */
    public function show(Request $request, Officer $officer): View
    {
        $this->authorize('view', $officer);

        // Policy sudah memastikan $officer->cooperative_id === $user->koperasi_id

        return view('pengelola.pengurus.show', [
            'officer' => $officer,
        ]);
    }
}
