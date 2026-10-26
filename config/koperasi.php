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
    | F8 — Pembatasan wilayah admin (FR-012, PROVISIONAL)
    |--------------------------------------------------------------------------
    |
    | Aturan resmi pembatasan wilayah belum dikonfirmasi dinas (Q-005). Default
    | **nonaktif** agar perilaku lama tidak berubah. Bila diaktifkan, akun dengan
    | peran `admin_wilayah` hanya dapat mengakses koperasi di wilayahnya (termasuk
    | seluruh turunan wilayah). `admin_dinas` tetap super admin.
    |
    */

    'admin_region_scope' => [
        'enabled' => (bool) env('ADMIN_REGION_SCOPE_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | F8 — Peringatan izin mendekati kedaluwarsa (FR-022)
    |--------------------------------------------------------------------------
    |
    | Ambang hari bersifat [PROPOSED]; sesuaikan setelah dinas menetapkan aturan.
    |
    */

    'license_expiring_days' => (int) env('LICENSE_EXPIRING_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | F8 — Formula kesehatan & kepatuhan (FR-082/FR-091, PROVISIONAL)
    |--------------------------------------------------------------------------
    |
    | Formula resmi BELUM ditetapkan dinas (Q-007). Nilai berikut hanya aturan
    | sementara yang dapat diubah tanpa mengubah kode. Skor 0–100; bobot
    | dinormalisasi bila totalnya bukan 1.
    |
    */

    'health_assessment' => [
        'components' => [
            'permodalan' => 'Permodalan',
            'likuiditas' => 'Likuiditas',
            'rentabilitas' => 'Rentabilitas',
            'kualitas_aset' => 'Kualitas Aset',
            'efisiensi' => 'Efisiensi',
        ],
        'weights' => [
            'permodalan' => 0.20,
            'likuiditas' => 0.20,
            'rentabilitas' => 0.20,
            'kualitas_aset' => 0.20,
            'efisiensi' => 0.20,
        ],
        // Urut dari ambang tertinggi. `min` inklusif.
        'predicates' => [
            ['min' => 85, 'label' => 'Sehat'],
            ['min' => 70, 'label' => 'Cukup Sehat'],
            ['min' => 55, 'label' => 'Kurang Sehat'],
            ['min' => 0, 'label' => 'Tidak Sehat'],
        ],
    ],

    'governance_compliance' => [
        'min_meetings_per_year' => (int) env('GOVERNANCE_MIN_MEETINGS', 2),
        'statuses' => [
            'patuh' => 'Patuh',
            'deviasi' => 'Deviasi',
        ],
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

    /*
    |--------------------------------------------------------------------------
    | Direktori publik F7 (kebijakan Q-008 — Tahap 105)
    |--------------------------------------------------------------------------
    |
    | Q-008 dijawab pemilik proyek: nama, nomor badan hukum, tanggal berdiri,
    | alamat sampai tingkat desa/kecamatan, jenis koperasi, dan status operasional
    | boleh tampil; NIK/telepon pribadi pengurus, omzet spesifik, dan data personal
    | anggota tidak boleh. Implementasi tetap memakai **allowlist eksplisit** di
    | bawah agar atribut model tidak pernah tampil otomatis.
    |
    | Dipakai oleh `App\Http\Controllers\Publik\KoperasiPublikController`. Field
    | yang tidak ada di allowlist tidak pernah dikirim ke view publik, sehingga
    | penambahan kolom baru pada `cooperatives` tidak otomatis membocorkan data.
    |
    | Catatan implementasi (lihat `docs/DECISIONS.md` §27 dan §32):
    | - alamat publik = hierarki `regions` (bukan teks bebas `alamat`);
    | - `status_verifikasi` tidak dipakai sebagai "status operasional"; kolom
    |   `status_operasional` sudah dimigrasikan pada DB lokal (migrasi
    |   `2026_10_24_000002_*`); target produksi harus menjalankan migrasi yang sama;
    | - pilihan field aktif disimpan di tabel `settings` (FR-191) dan dibatasi
    |   oleh `allowed_fields` di bawah. Bila tabel belum ada atau belum ada baris, nilai default
    |   `fields`/`show_region`/`contact` dipakai.
    |
    */

    'public' => [

        // Status verifikasi yang boleh tampil di direktori & profil publik (FR-180, BR-009).
        'visible_statuses' => ['approved'],

        // Batas maksimum field yang boleh diaktifkan admin (Q-008). UI FR-191 hanya
        // menampilkan subset dari daftar ini. Jangan menambahkan NIK pengurus, telepon
        // pribadi, omzet spesifik, atau data personal anggota ke daftar ini.
        'allowed_fields' => [
            'nama',
            'nomor_identitas',
            'tanggal_berdiri',
            'jenis_koperasi',
            'status_operasional',
            'website',
        ],

        // Kolom skalar default yang ditampilkan — sesuai keputusan Q-008:
        // nama, nomor badan hukum, tanggal berdiri, jenis koperasi, status operasional
        // (+ situs web yang sudah publik sejak F7). Alamat disusun dari hierarki `regions`
        // (sampai desa/kecamatan), bukan dari teks bebas `cooperatives.alamat`.
        // `status_verifikasi` BUKAN status operasional sehingga tidak ditampilkan dengan label itu.
        // Field yang TIDAK boleh tampil: NIK pengurus, telepon pribadi pengurus, omzet
        // spesifik, data personal anggota.
        'fields' => [
            'nama',
            'nomor_identitas',
            'tanggal_berdiri',
            'jenis_koperasi',
            'status_operasional',
            'website',
        ],

        // Jalur wilayah administratif (provinsi → desa/kelurahan) ditampilkan bila
        // `region_id` terisi. Dataset resmi sudah diimpor ke DB lokal (Q-012);
        // target produksi harus mengimpor dataset yang sama. Selama `regions` kosong
        // atau `region_id` NULL, label memakai fallback aman.
        'show_region' => true,

        // Kontak bersifat sensitif (NFR-SEC-015) → hanya ditampilkan dalam bentuk termasking.
        // `false` = tidak ditampilkan sama sekali.
        'contact' => [
            'email' => true,
            'telephone' => true,
        ],

        // Jumlah kartu koperasi per halaman direktori.
        'per_page' => 12,
    ],

];
