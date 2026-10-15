<?php

namespace App\Http\Controllers\Pengelola;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Profil koperasi untuk pengelola (FR-041) — read-only.
 *
 * Pengelola tidak mengubah data resmi secara langsung; perubahan diajukan lewat
 * workflow pengajuan (FR-042) yang dibangun pada F5.
 */
class ProfilKoperasiController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        if (! $user->hasKoperasi()) {
            return view('pengelola.belum-terhubung');
        }

        $cooperative = $user->koperasi;

        if ($cooperative === null) {
            return view('pengelola.belum-terhubung', [
                'reason' => 'Koperasi yang tertaut pada akun Anda tidak ditemukan atau telah diarsipkan.',
            ]);
        }

        Gate::authorize('view', $cooperative);

        $cooperative->loadMissing('region');

        return view('pengelola.profil', [
            'cooperative' => $cooperative,
        ]);
    }
}
