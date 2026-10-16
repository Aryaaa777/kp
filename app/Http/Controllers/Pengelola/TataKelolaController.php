<?php

namespace App\Http\Controllers\Pengelola;

use App\Http\Controllers\Controller;
use App\Models\GovernanceRecord;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengelola melihat data tata kelola koperasinya sendiri (read-only, FR-090).
 *
 * Write access (via pengajuan) menyusul pada F5.
 */
class TataKelolaController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GovernanceRecord::class);

        $user = $request->user();

        if ($user->koperasi_id === null) {
            abort(403, 'Akun Anda belum terhubung ke koperasi. Hubungi admin dinas.');
        }

        $records = GovernanceRecord::where('cooperative_id', $user->koperasi_id)
            ->orderByDesc('period_year')
            ->paginate(15);

        return view('pengelola.tata-kelola.index', [
            'records' => $records,
        ]);
    }

    public function show(Request $request, GovernanceRecord $governanceRecord): View
    {
        $this->authorize('view', $governanceRecord);

        return view('pengelola.tata-kelola.show', [
            'record' => $governanceRecord,
        ]);
    }
}
