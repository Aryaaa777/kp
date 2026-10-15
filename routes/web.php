<?php

use App\Http\Controllers\Admin\CooperativeController as AdminCooperativeController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Pengelola\DashboardController as PengelolaDashboardController;
use App\Http\Controllers\Pengelola\ProfilKoperasiController;
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

    /*
    |----------------------------------------------------------------------
    | Area admin dinas
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin_dinas')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Data induk koperasi (F2). Urutan penting: /create sebelum /{cooperative}.
        Route::get('/koperasi', [AdminCooperativeController::class, 'index'])->name('koperasi.index');
        Route::get('/koperasi/create', [AdminCooperativeController::class, 'create'])->name('koperasi.create');
        Route::post('/koperasi', [AdminCooperativeController::class, 'store'])->name('koperasi.store');
        Route::get('/koperasi/{cooperative}', [AdminCooperativeController::class, 'show'])->name('koperasi.show');
        Route::get('/koperasi/{cooperative}/edit', [AdminCooperativeController::class, 'edit'])->name('koperasi.edit');
        Route::put('/koperasi/{cooperative}', [AdminCooperativeController::class, 'update'])->name('koperasi.update');
        Route::delete('/koperasi/{cooperative}', [AdminCooperativeController::class, 'destroy'])->name('koperasi.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Area pengelola koperasi
    |----------------------------------------------------------------------
    */
    Route::middleware('role:pengelola_koperasi')->prefix('koperasi')->name('pengelola.')->group(function () {
        Route::get('/dashboard', [PengelolaDashboardController::class, 'index'])->name('dashboard');
        Route::get('/profil', [ProfilKoperasiController::class, 'show'])->name('profil');
    });
});
