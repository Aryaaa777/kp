<?php

namespace App\Http\Controllers\Pengelola;

use App\Http\Controllers\Controller;
use App\Models\CooperativeAsset;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengelola melihat aset koperasinya sendiri (read-only, FR-101).
 *
 * Write access (via pengajuan) menyusul pada F5.
 */
class AsetController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CooperativeAsset::class);

        $user = $request->user();

        if ($user->koperasi_id === null) {
            abort(403, 'Akun Anda belum terhubung ke koperasi. Hubungi admin dinas.');
        }

        $assets = CooperativeAsset::where('cooperative_id', $user->koperasi_id)
            ->orderByDesc('period_year')
            ->orderBy('asset_type')
            ->paginate(15);

        return view('pengelola.aset.index', [
            'assets' => $assets,
        ]);
    }

    public function show(Request $request, CooperativeAsset $asset): View
    {
        $this->authorize('view', $asset);

        return view('pengelola.aset.show', [
            'asset' => $asset,
        ]);
    }
}
