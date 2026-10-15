# Panduan Setup Lokal

Sistem Informasi Pendataan dan Pemantauan Koperasi Provinsi Jawa Barat — dokumentasi setup lingkungan pengembangan.

> Versi terpasang yang terverifikasi: lihat `docs/VERSIONS.md`.

## 1. Prasyarat

- **PHP >= 8.3** (terverifikasi pada 8.4.0) dengan ekstensi: `pdo_mysql`, `mysqli`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl`.
- **Composer 2.x**
- **Node.js 20+ / npm** (terverifikasi Node 22.15.1, npm 10.9.2)
- **MySQL 8.x atau MariaDB 10.3+** (terverifikasi MariaDB 10.4.32 dari XAMPP)
- **Git**

> **Catatan Windows/PowerShell:** bila `npm` memblokir eksekusi script (`npm.ps1`), jalankan lewat `npm.cmd` sebagai gantinya.

## 2. Memperoleh Kode & Dependensi

```powershell
git clone <url-repo> koperasi-jabar
cd koperasi-jabar

composer install
npm.cmd install
```

## 3. Konfigurasi Environment

1. Salin template environment:

   ```powershell
   Copy-Item .env.example .env
   ```

2. Generate application key:

   ```powershell
   php artisan key:generate
   ```

3. Sesuaikan kredensial database pada `.env` (nilai contoh, **jangan** komit kredensial nyata):

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=koperasi_jabar
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   > `.env` **tidak pernah** di-commit (sudah masuk `.gitignore`). Jangan menulis password/token/secret ke source code maupun dokumentasi.

## 4. Menyiapkan Database

Buat database kosong (utf8mb4) sesuai nama pada `DB_DATABASE`:

```sql
CREATE DATABASE IF NOT EXISTS koperasi_jabar
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Lalu jalankan migrasi bawaan framework:

```powershell
php artisan migrate
```

> F0 hanya menjalankan migrasi bawaan Laravel (`users`, `cache`, `jobs`). Migrasi modul bisnis dibuat pada fase berikutnya.

## 5. Menjalankan Aplikasi (Lokal)

Terminal 1 — aset frontend (Vite dev server, dengan hot reload):

```powershell
npm.cmd run dev
```

Terminal 2 — server aplikasi:

```powershell
php artisan serve
```

Buka `http://localhost:8000`.

### Build aset untuk produksi

```powershell
npm.cmd run build
```

## 6. Pengujian

```powershell
php artisan test
```

## 7. Struktur Direktori (ringkas)

```text
app/                 # kode aplikasi (controllers, models, providers)
config/              # konfigurasi Laravel
database/            # migrations, seeders, factories
docs/                # PRD, data dictionary, user flows, implementation plan, setup, versions
public/              # document root (index.php, build assets)
resources/
  css/app.css        # entry Tailwind CSS v4 (@import 'tailwindcss')
  js/app.js          # entry JavaScript
  views/             # Blade (layouts/, welcome.blade.php)
routes/web.php       # route web
tests/               # Feature & Unit test
vite.config.js       # integrasi Laravel Vite + @tailwindcss/vite
```

## 8. Tailwind CSS v4

Integrasi resmi `@tailwindcss/vite` sudah dikonfigurasi (lihat `vite.config.js` dan `resources/css/app.css`). Tidak perlu `tailwind.config.js` pada v4; token tema didefinisikan via directive `@theme` di `resources/css/app.css`.

Font `Instrument Sans` dimuat melalui plugin fonts `laravel-vite-plugin`. Optimasi font fallback (`fontaine`) **tidak** diaktifkan (`optimizedFallbacks: false`) agar tidak menambah dependency di luar kebutuhan F0.

## 9. Troubleshooting

| Gejala | Penyebab | Solusi |
| --- | --- | --- |
| `npm : ... running scripts is disabled` | Kebijakan eksekusi PowerShell | Gunakan `npm.cmd` |
| Build memperingatkan `Optimized font fallbacks require ... fontaine` | Plugin fonts tanpa `fontaine` | Sudah dinonaktifkan via `optimizedFallbacks: false` |
| `php artisan db:show` error `performance_schema.session_status` | Keterbatasan MariaDB 10.4 XAMPP | Abaikan; gunakan `migrate:status` atau query langsung |
| Pesan `could not find driver` | Ekstensi `pdo_mysql` tidak aktif | Aktifkan `pdo_mysql`/`mysqli` di `php.ini` |
| Aset tidak muncul / `Vite manifest not found` | Belum build / belum menjalankan dev server | Jalankan `npm.cmd run build` atau `npm.cmd run dev` |

## 10. Catatan Fase F0

- Scope F0: inisialisasi proyek, konfigurasi Blade + Vite + Tailwind v4, koneksi MySQL via environment, layout dasar + halaman awal, konfigurasi Git.
- **Belum** ada: autentikasi, migration modul bisnis, CRUD koperasi, workflow verifikasi (masuk fase F1+).
- Laravel Boost (disebut pada `AGENTS.md`) sengaja **tidak** diinstal selama F0.

Lihat `docs/IMPLEMENTATION_PLAN.md` untuk rencana fase berikutnya.
