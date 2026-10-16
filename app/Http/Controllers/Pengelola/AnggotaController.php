<?php

namespace App\Http\Controllers\Pengelola;

use App\Http\Controllers\Controller;
use App\Models\MemberHistory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengelola melihat riwayat keanggotaan koperasinya sendiri (read-only, FR-111).
 *
 * PROVISIONAL — pencatatan per kejadian/perubahan. Write access (via pengajuan) menyusul pada F5.
 */
class AnggotaController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', MemberHistory::class);

        $user = $request->user();

        if ($user->koperasi_id === null) {
            abort(403, 'Akun Anda belum terhubung ke koperasi. Hubungi admin dinas.');
        }

        $histories = MemberHistory::where('cooperative_id', $user->koperasi_id)
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('pengelola.anggota.index', [
            'histories' => $histories,
        ]);
    }

    public function show(Request $request, MemberHistory $memberHistory): View
    {
        $this->authorize('view', $memberHistory);

        return view('pengelola.anggota.show', [
            'history' => $memberHistory,
        ]);
    }
}
