<?php

use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\Admin\ActivityLogController as AdminActivityLogController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\CooperativeAssetController as AdminCooperativeAssetController;
use App\Http\Controllers\Admin\CooperativeController as AdminCooperativeController;
use App\Http\Controllers\Admin\CooperativeLicenseController as AdminCooperativeLicenseController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DocumentController as AdminDocumentController;
use App\Http\Controllers\Admin\GovernanceRecordController as AdminGovernanceRecordController;
use App\Http\Controllers\Admin\HealthAssessmentController as AdminHealthAssessmentController;
use App\Http\Controllers\Admin\MemberHistoryController as AdminMemberHistoryController;
use App\Http\Controllers\Admin\OfficerController as AdminOfficerController;
use App\Http\Controllers\Admin\PublicSettingsController as AdminPublicSettingsController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SubmissionController as AdminSubmissionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
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
use App\Http\Controllers\Publik\KoperasiPublikController;
use App\Models\Cooperative;
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

    // Reset password (FR-004). Respons permintaan selalu generik.
    Route::get('/lupa-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/lupa-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Area terautentikasi
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active', 'idle', 'admin-region'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Pengalihan ke dashboard sesuai peran.
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Keamanan akun & sesi aktif (FR-003).
    Route::get('/akun/keamanan', [AccountSecurityController::class, 'show'])->name('account.security');
    Route::delete('/akun/keamanan/sesi-lain', [AccountSecurityController::class, 'destroyOthers'])
        ->name('account.security.sessions.destroy-others');
    Route::delete('/akun/keamanan/semua-sesi', [AccountSecurityController::class, 'destroyAll'])
        ->name('account.security.sessions.destroy-all');

    // Unduh dokumen berotorisasi (FR-071). Otorisasi detail diperiksa Policy (admin/pemilik).
    Route::get('/dokumen/{document}/unduh', DocumentDownloadController::class)->name('dokumen.unduh');

    /*
    |----------------------------------------------------------------------
    | Area admin dinas
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin_dinas,admin_wilayah')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Manajemen akun & pengaturan publik tetap khusus admin dinas (super admin).
        Route::middleware('role:admin_dinas')->group(function () {
            // Urutan penting: /create sebelum /{user}.
            Route::get('/akun', [AdminUserController::class, 'index'])->name('akun.index');
            Route::get('/akun/create', [AdminUserController::class, 'create'])->name('akun.create');
            Route::post('/akun', [AdminUserController::class, 'store'])->name('akun.store');
            Route::get('/akun/{user}/edit', [AdminUserController::class, 'edit'])->name('akun.edit');
            Route::put('/akun/{user}', [AdminUserController::class, 'update'])->name('akun.update');
            Route::post('/akun/{user}/status', [AdminUserController::class, 'updateStatus'])->name('akun.status');
            Route::post('/akun/{user}/kirim-reset', [AdminUserController::class, 'sendReset'])->name('akun.reset');

            // Pengaturan field publik (FR-191).
            Route::get('/pengaturan/publik', [AdminPublicSettingsController::class, 'edit'])->name('pengaturan.publik');
            Route::put('/pengaturan/publik', [AdminPublicSettingsController::class, 'update'])->name('pengaturan.publik.update');
        });

        // Data induk koperasi (F2). Urutan penting: /create sebelum /{cooperative}.
        Route::get('/koperasi', [AdminCooperativeController::class, 'index'])->name('koperasi.index');
        Route::get('/koperasi/create', [AdminCooperativeController::class, 'create'])->name('koperasi.create');
        Route::post('/koperasi', [AdminCooperativeController::class, 'store'])->name('koperasi.store');
        Route::get('/koperasi/{cooperative}', [AdminCooperativeController::class, 'show'])->name('koperasi.show');
        Route::get('/koperasi/{cooperative}/edit', [AdminCooperativeController::class, 'edit'])->name('koperasi.edit');
        Route::put('/koperasi/{cooperative}', [AdminCooperativeController::class, 'update'])->name('koperasi.update');
        Route::delete('/koperasi/{cooperative}', [AdminCooperativeController::class, 'destroy'])->name('koperasi.destroy');

        // Kontrol publikasi direktori publik (F7, FR-190). Perubahan status memakai POST
        // (bukan GET) + CSRF; kelayakan & pengisian `published_at`/`published_by` di server.
        Route::post('/koperasi/{cooperative}/publikasikan', [AdminCooperativeController::class, 'publish'])->name('koperasi.publish');
        Route::post('/koperasi/{cooperative}/tarik-publikasi', [AdminCooperativeController::class, 'unpublish'])->name('koperasi.unpublish');

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
            Route::post('/izin/{license}/dokumen/{document}/versi', [AdminDocumentController::class, 'storeVersion'])->name('dokumen.versi');
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

        // Audit log aktivitas (F5, FR-151) — hanya baca; khusus admin dinas.
        Route::get('/audit-log', [AdminActivityLogController::class, 'index'])
            ->middleware('role:admin_dinas')
            ->name('audit-log.index');

        // Laporan & rekapitulasi koperasi (F6, FR-170) — read-only, hanya data terverifikasi.
        Route::get('/laporan', [AdminReportController::class, 'index'])->name('laporan.index');
        Route::get('/laporan/ekspor', [AdminReportController::class, 'export'])->name('laporan.export');
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

/*
|--------------------------------------------------------------------------
| Direktori & profil publik (F7 — FR-180/181/182) — tanpa autentikasi
|--------------------------------------------------------------------------
|
| Path mengikuti PRD §9 (L894): publik = `/`, `/koperasi`, `/koperasi/{slug}`.
| Konflik penamaan `/public/direktori` vs `/koperasi` pada PROMPT_PACK/ROADMAP
| dicatat sebagai isu dokumentasi terpisah (docs/DECISIONS.md §23) — tidak ada
| route duplikat yang dibuat.
|
| PENTING (urutan registrasi): blok ini diletakkan **setelah** grup internal
| `/koperasi` (pengelola) di atas. `GET /koperasi/{slug}` dapat menangkap path
| apa pun di bawah `/koperasi`, sehingga bila didaftarkan lebih dulu ia akan
| menutupi `/koperasi/dashboard`, `/koperasi/profil`, dst. Dengan urutan ini,
| route internal tetap menang dan route publik hanya melayani sisanya.
|
| Penjagaan `is_published` + status resmi ada di controller (`visibleQuery()`),
| bukan di sini — lihat `App\Http\Controllers\Publik\KoperasiPublikController`.
|
| Constraint slug (Tahap 88 — Opsi 1b): route publik **menolak** slug yang sama
| dengan segmen internal (`Cooperative::RESERVED_SLUGS`) sehingga ia tidak pernah
| melayani slug yang bertabrakan, dan pembentukan slug baru tidak lagi memakai
| segmen tersebut (`Cooperative::generateUniqueSlug()`). Slug lama yang tetap
| bertabrakan **tidak** diubah datanya dan tetap ditangani route internal
| (keputusan lanjutan ada di docs/DECISIONS.md §26).
|
*/
Route::get('/koperasi', [KoperasiPublikController::class, 'index'])->name('publik.direktori');
Route::get('/statistik', [KoperasiPublikController::class, 'statistics'])->name('publik.statistik');
Route::get('/koperasi/{slug}', [KoperasiPublikController::class, 'show'])
    ->where('slug', Cooperative::publicSlugPattern())
    ->name('publik.koperasi');
