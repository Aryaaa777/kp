# Catatan Versi Aktual

> Dokumen ini mencatat versi yang **benar-benar terpasang** pada lingkungan pengembangan lokal saat F0 diselesaikan (15 Oktober 2026). Ini bukan target/kebijakan; ini hasil verifikasi.
>
> Diperbarui **16 Oktober 2026** setelah remediasi **CVE-2026-108082** (commit `6a5fe3a`): `symfony/http-foundation` v8.0.15 → **v7.4.21**. Komponen lain tidak berubah.

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
| Database client | `C:\xampp\mysql\bin\mysql.exe` | — | Tidak ada di `PATH` |

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
| symfony/http-foundation | **v7.4.21** — dipatok langsung via constraint `^7.4.21` (remediasi CVE-2026-108082) |
| symfony/http-kernel | v8.0.15 |

## Sumber Data Wilayah (Tahap 107)

| Sumber | Versi / tanggal | Berkas | SHA-256 | Lisensi / penggunaan |
| --- | --- | --- | --- | --- |
| Kepmendagri 300.2.2-2138/2025 | 25 April 2025, 4.428 halaman | `kepmendagri_2138_2025.pdf` (tidak disimpan di repo) | `4a40e41cc2be515ca5b88df17a91e3f7ac6161ce7326a9fd38691753efa6b3fd` | Domain publik (UU 28/2014 Pasal 42); sumber kode & nama |
| BPS Master File Desa Jawa Barat 2025 Semester 2 | Vol. 22 No. 1 2026, rilis 30 Juni 2026 | `mfd_jabar_2025_s2.pdf` (tidak disimpan di repo) | `d1a2ca37003804b4c1d834bf8b1d3a6c8c6a6b523c3cfe486b31ec22f26d337f` | Publikasi BPS; hanya validasi jumlah/nama, tidak disebarluaskan ulang |
| Dataset turunan | 6.612 baris | `database/data/regions/jawa_barat_2025.csv` | `95e0c10a41cff77a3ab90c6eebdcb2564665c8ed5192a9a3ddee96622ba2edfd` | Turunan Kepmendagri; lihat `docs/REGION_DATA_SOURCES.md` |

Ekstraksi memerlukan `pypdf` (dependensi skrip, **bukan** dependensi aplikasi). Dataset **sudah
diimpor ke database lokal** MariaDB 10.4.32 (6.612 baris, 26 Oktober 2026); database produksi/staging
belum tersedia.

## Status suite & migrasi (26 Oktober 2026, penutupan F8)

- Suite penuh: **599 tes / 2.687 asersi PASS** pada SQLite `:memory:` (bukan bukti MariaDB 10.11).
- `php artisan migrate:status` pada database lokal MariaDB 10.4.32: **30 migrasi `Ran`**, tidak ada
  `Pending`. Ini mencakup empat migrasi yang sebelumnya tertunda (batch 7) dan empat migrasi F8
  (batch 8: `users.region_id`, komponen kesehatan, kepatuhan tata kelola, versi dokumen).
- `regions` pada database lokal berisi **6.612 baris**; audit `region_id` menunjukkan 1 koperasi
  tanpa wilayah sehingga `cooperatives.region_id` **tetap nullable** (tanpa backfill/`NOT NULL`).
- `RoleSeeder` dijalankan ulang (aditif) untuk menambahkan peran referensi `admin_wilayah` pada
  database lokal.
- Smoke HTTP lokal: `/statistik`, `/koperasi`, dan `/login` = **200**; `composer audit` bersih.
- Tidak ada `migrate:fresh`, rollback massal, atau penghapusan data. Backup lokal dibuat sebelum
  migrasi di luar repositori.
- **Bukan bukti produksi:** MariaDB 10.11, HTTPS, domain, SMTP, dan backup produksi belum
  diverifikasi (Q-014). Transport email lokal memakai `MAIL_MAILER=log`.
- F8 selesai secara implementasi lokal: FR-012, FR-022, FR-072, FR-082, FR-091, FR-171, FR-183.
  Formula/ambang/pembatasan wilayah tetap **PROVISIONAL** (Q-005/Q-007/Q-010).

## Kesesuaian dengan PRD

- PRD §13.1 mengusulkan **Laravel 13.x** dan **PHP 8.3–8.5**. Lingkungan ini memakai **Laravel 13.36.0 + PHP 8.4.0** → sesuai.
- PRD mengusulkan **MySQL 8.x** dan menandainya `[NEEDS CONFIRMATION]`. Server yang benar-benar tersedia adalah **MariaDB 10.4.32 (XAMPP)**, yang tetap didukung Laravel 13 (driver `mysql`). **Keputusan sementara:** gunakan MariaDB lokal; versi server produksi masih perlu dikonfirmasi (Q-014 / §21 PRD).
- PRD mengasumsikan zona waktu `Asia/Jakarta` dan locale Indonesia. Diterapkan via `APP_TIMEZONE=Asia/Jakarta` dan `APP_LOCALE=id` (fallback `en`).

## Known Issues / Advisory

| Item | Status | Tindakan |
| --- | --- | --- |
| `symfony/http-foundation` — **CVE-2026-108082** (ReDoS `HeaderUtils::split()` via header `Cache-Control`) | **DIPERBAIKI** (commit `6a5fe3a`) | Ditangani **tanpa menaikkan PHP**: `symfony/http-foundation` dipatok lewat constraint langsung `^7.4.21` → **v7.4.21**. Jalur v8.0.x yang terdampak tidak memiliki perbaikan; patch v8.1.7+ mensyaratkan PHP >= 8.4.1 sedangkan lingkungan ini PHP 8.4.0. `composer audit` bersih; **angka historis saat patch: 131 tes / 461 assertions lulus** (**bukan** status suite saat ini — status terbaru ada di `DECISIONS.md` §21.8). Penyelarasan seluruh komponen Symfony (mis. ke 8.1.9+) dapat dievaluasi kembali setelah upgrade PHP yang sesuai dan pengujian. |
| `php artisan db:show` gagal di MariaDB 10.4 (XAMPP) karena tabel `performance_schema.session_status` tidak tersedia | Diketahui | Bukan kegagalan koneksi. Gunakan `migrate:status` / query langsung sebagai gantinya. |
| Laravel Boost | Tidak digunakan | Keputusan proyek: Boost tidak dipakai. Instruksi Boost bawaan scaffold telah dihapus dari `AGENTS.md` & `CLAUDE.md`; jangan dijalankan kecuali diputuskan lain oleh pemilik proyek. |
| Paket autentikasi | Tidak digunakan | F1 memakai session guard bawaan Laravel. Tidak ada paket autentikasi eksternal yang ditambahkan. |
| Migrasi `2026_10_19_000001_*` — `up()` **tidak idempoten** bila statement FK gagal (kolom+indeks sudah ada → `early return` → FK tidak pernah dibuat, migrasi tetap tercatat `Ran`) | **DIPERBAIKI** (Tahap 68) dan **sudah dijalankan di DB lokal** | `up()` memeriksa **kolom, indeks, dan FK secara terpisah** (`classify()`); migrasi kini `Ran` pada MariaDB 10.4.32 lokal (batch 7). Diuji oleh `tests/Feature/ParentSubmissionForeignKeyTest.php`. DDL FK pada MariaDB **10.11/produksi belum diverifikasi** — `DECISIONS.md` §21 dan §33. |
| Kebijakan `down()` migrasi `2026_10_19_000001_*` — penghapusan indeks berdasarkan definisi kolom & `dropColumn` yang destruktif terhadap tautan versi | **DIPERBAIKI/DIDOKUMENTASIKAN** (Tahap 70) | Indeks dinilai dari **nama `self::INDEX` + definisi** (`classifyIndex()`); indeks pihak lain tidak pernah dihapus. `down()` **destruktif** terhadap relasi versi (`parent_submission_id` hilang permanen meski baris tetap ada) → butuh persetujuan eksplisit. `DROP INDEX` dapat gagal lebih awal di MariaDB (errno 1553). Risiko `->after('id')` **perlu diverifikasi** pada MariaDB target. `DECISIONS.md` §21.2/§21.7. |
| Self-FK `submissions.parent_submission_id` & serialisasi T-1 | **Belum terverifikasi penuh** | SQLite: `lockForUpdate()` no-op **dan** self-FK sengaja dilewati oleh guard driver migrasi → suite SQLite **bukan** bukti. Migrasi sudah `Ran` di MariaDB 10.4.32 lokal, tetapi uji konkurensi dan verifikasi FK pada MariaDB 10.11/produksi belum dilakukan — `DECISIONS.md` §20.7, §21.3, §33. |
