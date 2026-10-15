<?php

namespace App\Http\Controllers\Pengelola;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Placeholder area koperasi (fondasi F1).
 *
 * Halaman ini TIDAK menampilkan data koperasi. Fungsinya membuktikan
 * mekanisme anti-IDOR: pengelola hanya boleh mengakses koperasi yang
 * tertaut pada akunnya, sedangkan admin dinas boleh mengakses semua.
 * Modul data koperasi sesungguhnya dibangun pada F2.
 */
class KoperasiController extends Controller
{
    public function show(Request $request, string $koperasi): View
    {
        $koperasiId = (int) $koperasi;

        Gate::authorize('access-koperasi', $koperasiId);

        return view('pengelola.koperasi', [
            'koperasiId' => $koperasiId,
        ]);
    }
}
