<?php

use App\Http\Controllers\Admin\CooperativeController as AdminCooperativeController;
use App\Http\Controllers\Admin\CooperativeLicenseController as AdminCooperativeLicenseController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Admin\OfficerController as AdminOfficerController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\Pengelola\DashboardController as PengelolaDashboardController;
use App\Http\Controllers\Pengelola\PengurusController as PengelolaPengurusController;
use App\Http\Controllers\Pengelola\PerizinanController as PengelolaPerizinanController;
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

    // Unduh dokumen berotorisasi (FR-071). Otorisasi detail diperiksa Policy (admin/pemilik).
    Route::get('/dokumen/{document}/unduh', DocumentDownloadController::class)->name('dokumen.unduh');

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

        // Perizinan & dokumen per koperasi (F3). Route bersarang + scoped binding: izin koperasi
        // lain tidak dapat diakses lewat id yang ditebak (404).
        Route::prefix('koperasi/{cooperative}')->name('koperasi.izin.')->scopeBindings()->group(function () {
            Route::get('/izin', [AdminCooperativeLicenseController::class, 'index'])->name('index');
            Route::get('/izin/create', [AdminCooperativeLicenseController::class, 'create'])->name('create');
            Route::post('/izin', [AdminCooperativeLicenseController::class, 'store'])->name('store');
            Route::get('/izin/{license}', [AdminCooperativeLicenseController::class, 'show'])->name('show');
            Route::get('/izin/{license}/edit', [AdminCooperativeLicenseController::class, 'edit'])->name('edit');
            Route::put('/izin/{license}', [AdminCooperativeLicenseController::class, 'update'])->name('update');
            Route::delete('/izin/{license}', [AdminCooperativeLicenseController::class, 'destroy'])->name('destroy');
            Route::post('/izin/{license}/verifikasi', [AdminCooperativeLicenseController::class, 'verify'])->name('verify');
            Route::post('/izin/{license}/dokumen', [AdminDocumentController::class, 'store'])->name('dokumen.store');
        });

        // Arsip dokumen (soft delete).
        Route::delete('/dokumen/{document}', [AdminDocumentController::class, 'destroy'])->name('dokumen.destroy');

        // Pengurus per koperasi (F4 PARTIAL). Route bersarang + scoped binding.
        Route::prefix('koperasi/{cooperative}')->name('koperasi.pengurus.')->scopeBindings()->group(function () {
            Route::get('/pengurus', [AdminOfficerController::class, 'index'])->name('index');
            Route::get('/pengurus/create', [AdminOfficerController::class, 'create'])->name('create');
            Route::post('/pengurus', [AdminOfficerController::class, 'store'])->name('store');
            Route::get('/pengurus/{officer}', [AdminOfficerController::class, 'show'])->name('show');
            Route::get('/pengurus/{officer}/edit', [AdminOfficerController::class, 'edit'])->name('edit');
            Route::put('/pengurus/{officer}', [AdminOfficerController::class, 'update'])->name('update');
            Route::delete('/pengurus/{officer}', [AdminOfficerController::class, 'destroy'])->name('destroy');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Area pengelola koperasi
    |----------------------------------------------------------------------
    */
    Route::middleware('role:pengelola_koperasi')->prefix('koperasi')->name('pengelola.')->group(function () {
        Route::get('/dashboard', [PengelolaDashboardController::class, 'index'])->name('dashboard');
        Route::get('/profil', [ProfilKoperasiController::class, 'show'])->name('profil');

        // Perizinan & dokumen koperasi sendiri (FR-071) — hanya baca.
        Route::get('/izin', [PengelolaPerizinanController::class, 'index'])->name('izin.index');
        Route::get('/izin/{license}', [PengelolaPerizinanController::class, 'show'])->name('izin.show');

        // Pengurus koperasi sendiri (F4 PARTIAL) — hanya baca di F4.
        Route::get('/pengurus', [PengelolaPengurusController::class, 'index'])->name('pengurus.index');
        Route::get('/pengurus/{officer}', [PengelolaPengurusController::class, 'show'])->name('pengurus.show');
    });
});
