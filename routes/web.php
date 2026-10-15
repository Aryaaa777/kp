<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Pengelola\DashboardController as PengelolaDashboardController;
use App\Http\Controllers\Pengelola\KoperasiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman publik
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

/*
|--------------------------------------------------------------------------
| Tamu (belum terautentikasi)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Area terautentikasi
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Pengalihan ke dashboard sesuai peran.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Area koperasi (fondasi anti-IDOR; data koperasi dibangun pada F2).
    Route::get('/dashboard/koperasi/{koperasi}', [KoperasiController::class, 'show'])
        ->whereNumber('koperasi')
        ->name('koperasi.show');

    // Area admin dinas.
    Route::middleware('role:admin_dinas')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    });

    // Area pengelola koperasi.
    Route::middleware('role:pengelola_koperasi')->prefix('koperasi')->name('pengelola.')->group(function () {
        Route::get('/dashboard', [PengelolaDashboardController::class, 'index'])->name('dashboard');
    });
});
