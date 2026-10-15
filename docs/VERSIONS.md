# Catatan Versi Aktual

> Dokumen ini mencatat versi yang **benar-benar terpasang** pada lingkungan pengembangan lokal saat F0 diselesaikan (15 Oktober 2026). Ini bukan target/kebijakan; ini hasil verifikasi.

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
| symfony/http-foundation | v8.0.15 |

## Kesesuaian dengan PRD

- PRD §13.1 mengusulkan **Laravel 13.x** dan **PHP 8.3–8.5**. Lingkungan ini memakai **Laravel 13.36.0 + PHP 8.4.0** → sesuai.
- PRD mengusulkan **MySQL 8.x** dan menandainya `[NEEDS CONFIRMATION]`. Server yang benar-benar tersedia adalah **MariaDB 10.4.32 (XAMPP)**, yang tetap didukung Laravel 13 (driver `mysql`). **Keputusan sementara:** gunakan MariaDB lokal; versi server produksi masih perlu dikonfirmasi (Q-014 / §21 PRD).
- PRD mengasumsikan zona waktu `Asia/Jakarta` dan locale Indonesia. Diterapkan via `APP_TIMEZONE=Asia/Jakarta` dan `APP_LOCALE=id` (fallback `en`).

## Known Issues / Advisory

| Item | Status | Tindakan |
| --- | --- | --- |
| `symfony/http-foundation` v8.0.15 terpengaruh **CVE-2026-108082** (ReDoS `HeaderUtils::split()` via header `Cache-Control`) | BELUM DIPERBAIKI | Patch tersedia di v8.1.7+, **tetapi v8.1.9 mensyaratkan PHP >= 8.4.1** sedangkan lingkungan ini PHP 8.4.0. Perbaikan: naikkan PHP ke >= 8.4.1, lalu `composer update symfony/http-foundation`. |
| `php artisan db:show` gagal di MariaDB 10.4 (XAMPP) karena tabel `performance_schema.session_status` tidak tersedia | Diketahui | Bukan kegagalan koneksi. Gunakan `migrate:status` / query langsung sebagai gantinya. |
| Laravel Boost | Tidak digunakan | Keputusan proyek: Boost tidak dipakai. Instruksi Boost bawaan scaffold telah dihapus dari `AGENTS.md` & `CLAUDE.md`; jangan dijalankan kecuali diputuskan lain oleh pemilik proyek. |
| Paket autentikasi | Tidak digunakan | F1 memakai session guard bawaan Laravel. Tidak ada paket autentikasi eksternal yang ditambahkan. |
