<?php

namespace App\Http\Controllers\Pengelola;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard pengelola koperasi.
     */
    public function index(Request $request): View
    {
        return view('pengelola.dashboard', [
            'user' => $request->user(),
            'cooperative' => $request->user()->koperasi,
        ]);
    }
}
