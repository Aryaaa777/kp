<?php

namespace App\Http\Controllers\Pengelola;

use App\Http\Controllers\Controller;
use App\Models\CooperativeLicense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Perizinan & dokumen untuk pengelola (FR-071) — **hanya baca**.
 *
 * Pengelola melihat izin dan dokumen koperasinya sendiri; pengajuan izin/perubahan data resmi
 * dilakukan lewat workflow pengajuan (F5), bukan penulisan langsung (BR-014).
 */
class PerizinanController extends Controller
{
    public function index(Request $request): View
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

        $licenses = $cooperative->licenses()
            ->withCount('documents')
            ->orderBy('license_type')
            ->orderByDesc('tanggal_terbit')
            ->get();

        return view('pengelola.izin.index', [
            'cooperative' => $cooperative,
            'licenses' => $licenses,
        ]);
    }

    public function show(CooperativeLicense $license): View
    {
        // Policy memverifikasi kepemilikan: pengelola hanya izin koperasinya (anti-IDOR).
        Gate::authorize('view', $license);

        $license->load(['verifier', 'documents.uploader']);

        return view('pengelola.izin.show', [
            'cooperative' => $license->cooperative,
            'license' => $license,
        ]);
    }
}
