<?php

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

];
