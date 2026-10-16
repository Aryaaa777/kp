<?php

use App\Services\Submissions\Handlers\KoperasiIndukHandler;

return [

    /*
    |--------------------------------------------------------------------------
    | Saran jenis koperasi
    |--------------------------------------------------------------------------
    |
    | Daftar jenis/bentuk koperasi resmi BELUM dikonfirmasi dinas (PRD Q-003 /
    | BR-002). Karena itu daftar ini sengaja dikosongkan dan TIDAK dipakai untuk
    | memvalidasi nilai. Bila dinas menetapkan daftar resmi, cukup isi array di
    | bawah — form akan otomatis berubah menjadi dropdown.
    |
    | Saat kosong, form memakai input teks bebas (tetap tervalidasi string).
    |
    */

    'jenis_koperasi_options' => [],

    /*
    |--------------------------------------------------------------------------
    | Dokumen pendukung (F3)
    |--------------------------------------------------------------------------
    |
    | Whitelist ekstensi/MIME dan batas ukuran unggahan dokumen (FR-070, NFR-SEC-008).
    | Batas ukuran 5 MB bersifat [PROPOSED] sesuai PRD §7.3 FR-070; sesuaikan bila
    | dinas menetapkan kebijakan lain.
    |
    | Disk penyimpanan dokumen HARUS privat (bukan `public`). Lihat config/filesystems.php.
    |
    */

    'document_disk' => 'local',

    'document_allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],

    'document_allowed_mimetypes' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ],

    'document_max_size_kb' => 5120,

    /*
    |--------------------------------------------------------------------------
    | Data periodik F4 (PROVISIONAL — MENUNGGU KONFIRMASI DINAS / Q-010)
    |--------------------------------------------------------------------------
    |
    | Aturan periodisitas & klasifikasi BELUM ditetapkan dinas. Nilai di bawah hanya
    | untuk simulasi/pengembangan dan dapat berubah setelah konfirmasi dinas.
    | Semua opsi bersifat konfigurasi, bukan aturan resmi.
    |
    */

    // Periode pencatatan kesehatan & tata kelola (provisional: tahunan saja).
    'period_options' => [
        'annual' => 'Tahunan',
    ],

    // Saran jenis/kategori aset (provisional). Kosong = input teks bebas.
    'asset_type_options' => [],

    // Kondisi aset (provisional, belum resmi).
    'asset_condition_options' => [
        'baik' => 'Baik',
        'rusak_ringan' => 'Rusak ringan',
        'rusak_berat' => 'Rusak berat',
    ],

    // Jenis perubahan keanggotaan (provisional, belum resmi).
    'member_change_type_options' => [
        'penambahan' => 'Penambahan anggota',
        'pengurangan' => 'Pengurangan anggota',
        'lainnya' => 'Perubahan lainnya',
    ],

    /*
    |--------------------------------------------------------------------------
    | Workflow pengajuan F5 (PROVISIONAL — MENUNGGU KONFIRMASI DINAS / Q-006)
    |--------------------------------------------------------------------------
    |
    | Daftar modul yang boleh diajukan pengelola (FR-140) beserta handler penerapannya
    | (FR-143). Field yang boleh diajukan (whitelist Q-006) didefinisikan oleh handler.
    |
    | Saat ini HANYA `koperasi_induk` (FR-042) yang diaktifkan. Modul lain (izin, data
    | periodik) ditambahkan setelah dinas mengonfirmasi whitelist field (Q-006).
    | Menambahkan handler baru = cukup menambah entri di sini (tanpa mengubah workflow).
    |
    */

    'submission_modules' => [
        'koperasi_induk' => [
            'label' => 'Data Induk Koperasi',
            'description' => 'Usul perubahan identitas & kontak koperasi (FR-042).',
            'handler' => KoperasiIndukHandler::class,
        ],
    ],

];
