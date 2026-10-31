# Catatan Versi Aktual

> Dokumen ini mencatat versi yang **benar-benar terpasang** pada lingkungan pengembangan lokal saat F0 diselesaikan (15 Oktober 2026). Ini bukan target/kebijakan; ini hasil verifikasi.
>
> Diperbarui **31 Oktober 2026** setelah remediasi **CVE-2026-45070**: `symfony/mime` v8.0.15 → **v7.4.22**; komponen Symfony lain diselaraskan ke **7.4.x**. `composer.json` memakai `config.platform.php = 8.3.0` agar lock dapat dipasang pada batas bawah target PHP 8.3.

## Runtime & Tooling

| Komponen | Versi terpasang | Sumber/Perintah verifikasi | Catatan |
| --- | --- | --- | --- |
| Laravel Framework | **13.36.0** | `php artisan --version` | Scaffold `laravel/laravel` |
| PHP (CLI) | **8.4.0** | `php -v` | Herd Lite (`C:\Users\mocha\.config\herd-lite\bin\php.exe`) |
| Composer | **2.8.9** | `composer -V` | |
| Node.js | **22.15.1** | `node -v` | |
| npm | **10.9.2** | `npm.cmd -v` | Pakai `npm.cmd` (kebijakan eksekusi PowerShell memblokir `npm.ps1`) |
| Git | **2.48.1.windows.1** | `git --version` | Branch awal: `main` |
| Database server | **MariaDB 10.4.32** | `mysql --version` (XAMPP) | Tersedia sebagai service Windows `mysql` (Running) |
| Database uji target | **MariaDB 10.11.19** | `mariadb.exe --version` (portabel, port 3307) | Hanya untuk verifikasi suite; bukan service/staging/produksi. Binary tidak di-commit. |
| Database client | `C:\xampp\mysql\bin\mysql.exe` | - | Tidak ada di `PATH` |

## Dependency Frontend (terpasang)

| Paket | Versi |
| --- | --- |
| vite | 8.3.4 |
| tailwindcss | 4.3.3 |
| @tailwindcss/vite | 4.3.3 |
| laravel-vite-plugin | 3.2.0 |
| concurrently | 10.0.6 |

## Beberapa Dependency Backend (terpasang)

| Paket | Versi |
| --- | --- |
| laravel/framework | v13.36.0 |
| symfony/http-foundation | **v7.4.22** — dipatok langsung via constraint `^7.4.21` |
| symfony/http-kernel | **v7.4.22** — diselaraskan untuk kompatibilitas PHP 8.3 |
| symfony/mime | **v7.4.22** — remediasi CVE-2026-45070 |
| symfony/mailer | **v7.4.22** |
| symfony/console | **v7.4.22** |
| Platform Composer | **PHP 8.3.0** — `config.platform.php`; runtime lokal tetap PHP 8.4.0 |

## Sumber Data Wilayah (Tahap 107)

| Sumber | Versi / tanggal | Berkas | SHA-256 | Lisensi / penggunaan |
| --- | --- | --- | --- | --- |
| Kepmendagri 300.2.2-2138/2025 | 25 April 2025, 4.428 halaman | `kepmendagri_2138_2025.pdf` (tidak disimpan di repo) | `4a40e41cc2be515ca5b88df17a91e3f7ac6161ce7326a9fd38691753efa6b3fd` | Domain publik (UU 28/2014 Pasal 42); sumber kode & nama |
| BPS Master File Desa Jawa Barat 2025 Semester 2 | Vol. 22 No. 1 2026, rilis 30 Juni 2026 | `mfd_jabar_2025_s2.pdf` (tidak disimpan di repo) | `d1a2ca37003804b4c1d834bf8b1d3a6c8c6a6b523c3cfe486b31ec22f26d337f` | Publikasi BPS; hanya validasi jumlah/nama, tidak disebarluaskan ulang |
| Dataset turunan | 6.612 baris | `database/data/regions/jawa_barat_2025.csv` | `208d7aca0cc7993721a39f3e3c003e290b9675d37d2f7d8506d5bc7f755a6526` | Turunan Kepmendagri; lihat `docs/REGION_DATA_SOURCES.md` |

Ekstraksi memerlukan `pypdf` (dependensi skrip, **bukan** dependensi aplikasi). Dataset **sudah
diimpor ke database lokal** MariaDB 10.4.32 (6.612 baris, 26 Oktober 2026); database produksi/staging
belum tersedia.

## Status suite, migrasi, dan pengerasan produksi (26-31 Oktober 2026)

- Suite penuh SQLite `:memory:`: **612 tes, 611 lulus, 1 skipped, 2.795 asersi**. Tes yang
  di-skip adalah tes khusus MySQL/MariaDB (self-FK).
- Suite penuh MariaDB **10.4.32** lokal: **612 tes, 611 lulus, 1 skipped, 2.800 asersi**.
  Tes yang di-skip adalah tes khusus SQLite (guard self-FK).
- Suite penuh MariaDB **10.11.19** portabel (port 3307, database uji terpisah, dijalankan
  26 Oktober 2026 sebelum dua tes regresi CI): **610 tes, 609 lulus, 1 skipped, 2.733 asersi**.
  Ini memverifikasi migrasi, DDL/FK, indeks, enum, dan perilaku aplikasi pada versi database
  target secara lokal. **Bukan** staging/produksi.
- `ParentSubmissionForeignKeyTest` kini sadar driver: tes tanpa self-FK hanya SQLite, tes baru
  memverifikasi self-FK + `ON DELETE SET NULL` pada MySQL/MariaDB, dan simulasi skema parsial
  melepas FK sebelum indeks untuk menghindari `errno 1553`.
- `php artisan migrate:status` pada database lokal MariaDB 10.4.32: **30 migrasi `Ran`**, tidak ada
  `Pending`. Ini mencakup empat migrasi yang sebelumnya tertunda (batch 7) dan empat migrasi F8
  (batch 8: `users.region_id`, komponen kesehatan, kepatuhan tata kelola, versi dokumen).
- `app:production-check` pada MariaDB 10.11.19 dengan konfigurasi produksi, dataset wilayah
  (6.612 baris), dan akun admin uji: **32 PASS, 2 WARN, 0 FAIL**. WARN: `SESSION_ENCRYPT=false`
  dan cache konfigurasi/route belum dibuat saat perintah dijalankan. Ini bukan pengganti staging.
- `regions` pada database lokal berisi **6.612 baris**; audit `region_id` menunjukkan 1 koperasi
  tanpa wilayah sehingga `cooperatives.region_id` **tetap nullable** (tanpa backfill/`NOT NULL`).
- `RoleSeeder` dijalankan ulang (aditif) untuk menambahkan peran referensi `admin_wilayah` pada
  database lokal.
- Smoke HTTP lokal: `/statistik`, `/koperasi`, dan `/login` = **200**; `composer audit` bersih.
- Tidak ada `migrate:fresh`, rollback massal, atau penghapusan data pada database berisi data.
  Backup lokal dibuat sebelum migrasi di luar repositori. Database uji 10.11 hanya dipakai untuk
  verifikasi.
- **Bukan bukti produksi:** staging resmi, HTTPS/domain `.go.id`, SMTP nyata, dan backup/restore
  produksi belum diverifikasi (Q-014). Transport email lokal memakai `MAIL_MAILER=log`.
- F8 selesai secara implementasi lokal: FR-012, FR-022, FR-072, FR-082, FR-091, FR-171, FR-183.
  Formula/ambang/pembatasan wilayah tetap **PROVISIONAL** (Q-005/Q-007/Q-010).

## Verifikasi backup/restore lokal (26 Oktober 2026)

- Sumber: database lokal `koperasi_jabar` pada MariaDB 10.4.32.
- Dump: `mysqldump` dengan `--single-transaction --routines --triggers`, ukuran **660.646 byte**,
  SHA-256 `a1e5e3fafe039df98013d428884129abcc32ddf3b0de28f500ef9b5a39bcac22`.
- Restore ke database scratch `koperasi_jabar_restore_check_ps`: **26 tabel** berhasil dibuat,
  jumlah baris setiap tabel sama dengan sumber; scratch dihapus setelah verifikasi.
- Script produksi: `scripts/db-backup-verify.sh` (Linux) membuat dump `.sql.gz`, checksum, restore
  ke scratch, membandingkan tabel/baris, lalu membersihkan scratch.
- **Batas:** belum menguji backup remote, enkripsi at-rest, retensi, RPO/RTO, atau restore ke
  staging/produksi. Uji restore staging tetap wajib sebelum go-live.

## CI MariaDB 10.11 (27-31 Oktober 2026)

- Workflow `.github/workflows/ci.yml`: matriks **PHP 8.3, 8.4, dan 8.5**, Node 22, service
  MariaDB 10.11, suite SQLite + MariaDB, build aset, Pint, dan `composer audit`.
- Run GitHub `c03011b` **sukses** untuk seluruh langkah:
  https://github.com/Aryaaa777/kp/actions/runs/40369698521.
- Run `8294505` gagal pada `composer audit` karena **CVE-2026-45070** (`symfony/mime`).
  Perbaikannya: `symfony/mime` v7.4.22, komponen Symfony diselaraskan ke 7.4.x, dan
  `config.platform.php = 8.3.0`. Run `1b87874` **sukses** pada matriks PHP 8.3/8.4/8.5:
  https://github.com/Aryaaa777/kp/actions/runs/41173784466.
- Dua run sebelumnya menemukan cacat nyata yang tidak terlihat di lokal: peringatan kompilasi
  `use RuntimeException;` pada migrasi lama dan komentar XML `--` pada contoh konfigurasi
  PHPUnit. Keduanya diperbaiki; regresi dijaga oleh `MigrationLintTest` dan
  `PhpUnitConfigTest`.
- Validasi lokal: SQLite **612 tes / 611 lulus / 1 skipped / 2.795 asersi**; MariaDB 10.4.32
  **612 tes / 611 lulus / 1 skipped / 2.800 asersi**; `composer audit` bersih.
- **Batas:** CI bukan pengganti staging/HTTPS/SMTP/backup restore.

## Kesesuaian dengan PRD

- PRD §13.1 mengusulkan **Laravel 13.x** dan **PHP 8.3–8.5**. Lingkungan ini memakai **Laravel
  13.36.0 + PHP 8.4.0**; lock Composer kini dihasilkan untuk platform **PHP 8.3.0** agar dapat
  dipasang pada batas bawah target. CI menguji PHP 8.3, 8.4, dan 8.5.
- PRD mengusulkan **MySQL 8.x** dan menandainya `[NEEDS CONFIRMATION]`. Server lokal adalah **MariaDB 10.4.32 (XAMPP)**; versi target **10.11.19** juga diuji secara portabel pada 26 Oktober 2026. Keduanya memakai driver `mysql` Laravel. **Keputusan sementara:** gunakan MariaDB lokal; versi server produksi masih perlu dikonfirmasi (Q-014 / §21 PRD).
- PRD mengasumsikan zona waktu `Asia/Jakarta` dan locale Indonesia. Diterapkan via `APP_TIMEZONE=Asia/Jakarta` dan `APP_LOCALE=id` (fallback `en`).

## Known Issues / Advisory

| Item | Status | Tindakan |
| --- | --- | --- |
| `symfony/http-foundation` — **CVE-2026-108082** (ReDoS `HeaderUtils::split()` via header `Cache-Control`) | **DIPERBAIKI** (commit `6a5fe3a`) | Ditangani **tanpa menaikkan PHP**: `symfony/http-foundation` dipatok lewat constraint langsung `^7.4.21` → **v7.4.21**. Jalur v8.0.x yang terdampak tidak memiliki perbaikan; patch v8.1.7+ mensyaratkan PHP >= 8.4.1 sedangkan lingkungan ini PHP 8.4.0. `composer audit` bersih; **angka historis saat patch: 131 tes / 461 assertions lulus** (**bukan** status suite saat ini — status terbaru ada di `DECISIONS.md` §21.8). Penyelarasan seluruh komponen Symfony (mis. ke 8.1.9+) dapat dievaluasi kembali setelah upgrade PHP yang sesuai dan pengujian. |
| `symfony/mime` — **CVE-2026-45070** (*Email Header Injection via Non-Token Characters in Mime Parameter Names*) | **DIPERBAIKI** (31 Oktober 2026) | `symfony/mime` v8.0.15 → **v7.4.22**; komponen Symfony lain diselaraskan ke 7.4.x. `composer.json` memakai `config.platform.php = 8.3.0`. `composer audit` bersih; rincian di `DECISIONS.md` §38. |
| `php artisan db:show` gagal di MariaDB 10.4 (XAMPP) karena tabel `performance_schema.session_status` tidak tersedia | Diketahui | Bukan kegagalan koneksi. Gunakan `migrate:status` / query langsung sebagai gantinya. |
| Laravel Boost | Tidak digunakan | Keputusan proyek: Boost tidak dipakai. Instruksi Boost bawaan scaffold telah dihapus dari `AGENTS.md` & `CLAUDE.md`; jangan dijalankan kecuali diputuskan lain oleh pemilik proyek. |
| Paket autentikasi | Tidak digunakan | F1 memakai session guard bawaan Laravel. Tidak ada paket autentikasi eksternal yang ditambahkan. |
| Migrasi `2026_10_19_000001_*` — `up()` **tidak idempoten** bila statement FK gagal (kolom+indeks sudah ada → `early return` → FK tidak pernah dibuat, migrasi tetap tercatat `Ran`) | **DIPERBAIKI** (Tahap 68) dan **sudah dijalankan di DB lokal** | `up()` memeriksa **kolom, indeks, dan FK secara terpisah** (`classify()`); migrasi kini `Ran` pada MariaDB 10.4.32 lokal (batch 7). Diuji oleh `tests/Feature/ParentSubmissionForeignKeyTest.php`. DDL/FK telah diuji pada MariaDB **10.11.19 lokal**; staging/produksi belum diverifikasi — `DECISIONS.md` §21 dan §33. |
| Kebijakan `down()` migrasi `2026_10_19_000001_*` — penghapusan indeks berdasarkan definisi kolom & `dropColumn` yang destruktif terhadap tautan versi | **DIPERBAIKI/DIDOKUMENTASIKAN** (Tahap 70) | Indeks dinilai dari **nama `self::INDEX` + definisi** (`classifyIndex()`); indeks pihak lain tidak pernah dihapus. `down()` **destruktif** terhadap relasi versi (`parent_submission_id` hilang permanen meski baris tetap ada) → butuh persetujuan eksplisit. `DROP INDEX` dapat gagal lebih awal di MariaDB (errno 1553). Risiko `->after('id')` telah dijalankan pada MariaDB 10.11.19 lokal; staging tetap perlu diverifikasi. `DECISIONS.md` §21.2/§21.7. |
| Self-FK `submissions.parent_submission_id` & serialisasi T-1 | **Terverifikasi pada MariaDB 10.11.19 lokal; konkurensi/staging belum** | SQLite: `lockForUpdate()` no-op **dan** self-FK sengaja dilewati oleh guard driver migrasi → suite SQLite **bukan** bukti. Migrasi sudah `Ran` di MariaDB 10.4.32 lokal, verifikasi FK pada MariaDB 10.11.19 lokal lulus; uji konkurensi dan staging/produksi belum dilakukan — `DECISIONS.md` §20.7, §21.3, §33. |
