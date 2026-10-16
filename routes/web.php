<?php

use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\CooperativeAssetController as AdminCooperativeAssetController;
use App\Http\Controllers\Admin\CooperativeController as AdminCooperativeController;
use App\Http\Controllers\Admin\CooperativeLicenseController as AdminCooperativeLicenseController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Admin\GovernanceRecordController as AdminGovernanceRecordController;
use App\Http\Controllers\Admin\HealthAssessmentController as AdminHealthAssessmentController;
use App\Http\Controllers\Admin\MemberHistoryController as AdminMemberHistoryController;
use App\Http\Controllers\Admin\OfficerController as AdminOfficerController;
use App\Http\Controllers\Admin\SubmissionController as AdminSubmissionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\Pengelola\AnggotaController as PengelolaAnggotaController;
use App\Http\Controllers\Pengelola\AsetController as PengelolaAsetController;
use App\Http\Controllers\Pengelola\DashboardController as PengelolaDashboardController;
use App\Http\Controllers\Pengelola\KesehatanController as PengelolaKesehatanController;
use App\Http\Controllers\Pengelola\NotificationController as PengelolaNotificationController;
use App\Http\Controllers\Pengelola\PengurusController as PengelolaPengurusController;
use App\Http\Controllers\Pengelola\PerizinanController as PengelolaPerizinanController;
use App\Http\Controllers\Pengelola\ProfilKoperasiController;
use App\Http\Controllers\Pengelola\SubmissionController as PengelolaSubmissionController;
use App\Http\Controllers\Pengelola\TataKelolaController as PengelolaTataKelolaController;
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

        // Data periodik F4: penilaian kesehatan (FR-080/081). Scoped binding anti-IDOR.
        Route::prefix('koperasi/{cooperative}')->name('koperasi.kesehatan.')->scopeBindings()->group(function () {
            Route::get('/kesehatan', [AdminHealthAssessmentController::class, 'index'])->name('index');
            Route::get('/kesehatan/create', [AdminHealthAssessmentController::class, 'create'])->name('create');
            Route::post('/kesehatan', [AdminHealthAssessmentController::class, 'store'])->name('store');
            Route::get('/kesehatan/{healthAssessment}', [AdminHealthAssessmentController::class, 'show'])->name('show');
            Route::get('/kesehatan/{healthAssessment}/edit', [AdminHealthAssessmentController::class, 'edit'])->name('edit');
            Route::put('/kesehatan/{healthAssessment}', [AdminHealthAssessmentController::class, 'update'])->name('update');
            Route::delete('/kesehatan/{healthAssessment}', [AdminHealthAssessmentController::class, 'destroy'])->name('destroy');
            // Pulihkan catatan terarsip. `withTrashed()` + scoped binding: record koperasi lain tetap 404.
            Route::post('/kesehatan/{healthAssessment}/pulihkan', [AdminHealthAssessmentController::class, 'restore'])
                ->name('restore')->withTrashed();
        });

        // Data periodik F4: tata kelola (FR-090).
        Route::prefix('koperasi/{cooperative}')->name('koperasi.tata-kelola.')->scopeBindings()->group(function () {
            Route::get('/tata-kelola', [AdminGovernanceRecordController::class, 'index'])->name('index');
            Route::get('/tata-kelola/create', [AdminGovernanceRecordController::class, 'create'])->name('create');
            Route::post('/tata-kelola', [AdminGovernanceRecordController::class, 'store'])->name('store');
            Route::get('/tata-kelola/{governanceRecord}', [AdminGovernanceRecordController::class, 'show'])->name('show');
            Route::get('/tata-kelola/{governanceRecord}/edit', [AdminGovernanceRecordController::class, 'edit'])->name('edit');
            Route::put('/tata-kelola/{governanceRecord}', [AdminGovernanceRecordController::class, 'update'])->name('update');
            Route::delete('/tata-kelola/{governanceRecord}', [AdminGovernanceRecordController::class, 'destroy'])->name('destroy');
            // Pulihkan catatan terarsip. `withTrashed()` + scoped binding: record koperasi lain tetap 404.
            Route::post('/tata-kelola/{governanceRecord}/pulihkan', [AdminGovernanceRecordController::class, 'restore'])
                ->name('restore')->withTrashed();
        });

        // Data periodik F4: aset koperasi (FR-100/101).
        Route::prefix('koperasi/{cooperative}')->name('koperasi.aset.')->scopeBindings()->group(function () {
            Route::get('/aset', [AdminCooperativeAssetController::class, 'index'])->name('index');
            Route::get('/aset/create', [AdminCooperativeAssetController::class, 'create'])->name('create');
            Route::post('/aset', [AdminCooperativeAssetController::class, 'store'])->name('store');
            Route::get('/aset/{asset}', [AdminCooperativeAssetController::class, 'show'])->name('show');
            Route::get('/aset/{asset}/edit', [AdminCooperativeAssetController::class, 'edit'])->name('edit');
            Route::put('/aset/{asset}', [AdminCooperativeAssetController::class, 'update'])->name('update');
            Route::delete('/aset/{asset}', [AdminCooperativeAssetController::class, 'destroy'])->name('destroy');
        });

        // Data periodik F4: riwayat keanggotaan (FR-110/111).
        Route::prefix('koperasi/{cooperative}')->name('koperasi.anggota.')->scopeBindings()->group(function () {
            Route::get('/anggota', [AdminMemberHistoryController::class, 'index'])->name('index');
            Route::get('/anggota/create', [AdminMemberHistoryController::class, 'create'])->name('create');
            Route::post('/anggota', [AdminMemberHistoryController::class, 'store'])->name('store');
            Route::get('/anggota/{memberHistory}', [AdminMemberHistoryController::class, 'show'])->name('show');
            Route::get('/anggota/{memberHistory}/edit', [AdminMemberHistoryController::class, 'edit'])->name('edit');
            Route::put('/anggota/{memberHistory}', [AdminMemberHistoryController::class, 'update'])->name('update');
            Route::delete('/anggota/{memberHistory}', [AdminMemberHistoryController::class, 'destroy'])->name('destroy');
        });

        // Workflow pengajuan & verifikasi (F5, FR-140…143). Admin melihat semua pengajuan
        // (kewenangan dinas) & memutuskan. Aksi diverifikasi server-side (SubmissionPolicy).
        Route::get('/pengajuan', [AdminSubmissionController::class, 'index'])->name('pengajuan.index');
        Route::get('/pengajuan/{submission}', [AdminSubmissionController::class, 'show'])->name('pengajuan.show');
        Route::post('/pengajuan/{submission}/tinjau', [AdminSubmissionController::class, 'startReview'])->name('pengajuan.start-review');
        Route::post('/pengajuan/{submission}/putuskan', [AdminSubmissionController::class, 'decide'])->name('pengajuan.decide');
        Route::post('/pengajuan/{submission}/arsip', [AdminSubmissionController::class, 'archive'])->name('pengajuan.archive');

        // Audit log aktivitas (F5, FR-151) — hanya baca.
        Route::get('/audit-log', [AdminActivityLogController::class, 'index'])->name('audit-log.index');
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

        // Data periodik F4 koperasi sendiri — hanya baca di F4 (mis. kesehatan read-only, BR-010).
        Route::get('/kesehatan', [PengelolaKesehatanController::class, 'index'])->name('kesehatan.index');
        Route::get('/kesehatan/{healthAssessment}', [PengelolaKesehatanController::class, 'show'])->name('kesehatan.show');
        Route::get('/tata-kelola', [PengelolaTataKelolaController::class, 'index'])->name('tata-kelola.index');
        Route::get('/tata-kelola/{governanceRecord}', [PengelolaTataKelolaController::class, 'show'])->name('tata-kelola.show');
        Route::get('/aset', [PengelolaAsetController::class, 'index'])->name('aset.index');
        Route::get('/aset/{asset}', [PengelolaAsetController::class, 'show'])->name('aset.show');
        Route::get('/anggota', [PengelolaAnggotaController::class, 'index'])->name('anggota.index');
        Route::get('/anggota/{memberHistory}', [PengelolaAnggotaController::class, 'show'])->name('anggota.show');

        // Pengajuan perubahan data resmi (F5, FR-140…143) — koperasi sendiri. Urutan penting:
        // /create sebelum /{submission}.
        Route::get('/pengajuan', [PengelolaSubmissionController::class, 'index'])->name('pengajuan.index');
        Route::get('/pengajuan/create', [PengelolaSubmissionController::class, 'create'])->name('pengajuan.create');
        Route::post('/pengajuan', [PengelolaSubmissionController::class, 'store'])->name('pengajuan.store');
        Route::get('/pengajuan/{submission}', [PengelolaSubmissionController::class, 'show'])->name('pengajuan.show');
        Route::get('/pengajuan/{submission}/edit', [PengelolaSubmissionController::class, 'edit'])->name('pengajuan.edit');
        Route::put('/pengajuan/{submission}', [PengelolaSubmissionController::class, 'update'])->name('pengajuan.update');
        Route::post('/pengajuan/{submission}/kirim', [PengelolaSubmissionController::class, 'submit'])->name('pengajuan.submit');
        Route::post('/pengajuan/{submission}/arsip', [PengelolaSubmissionController::class, 'archive'])->name('pengajuan.archive');

        // Notifikasi in-app status pengajuan (F5, FR-201).
        Route::get('/notifikasi', [PengelolaNotificationController::class, 'index'])->name('notifikasi.index');
        Route::post('/notifikasi/baca-semua', [PengelolaNotificationController::class, 'markAllRead'])->name('notifikasi.baca-semua');
    });
});
