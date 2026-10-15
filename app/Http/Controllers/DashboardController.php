<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Arahkan pengguna ke dashboard sesuai perannya.
     */
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasRole('admin_dinas')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->hasRole('pengelola_koperasi')) {
            return redirect()->route('pengelola.dashboard');
        }

        abort(403, 'Akun Anda belum memiliki peran yang dikenali.');
    }
}
