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

### 3.1 Sesi & email lokal (FR-003/FR-004)

Nilai default yang aman untuk pengembangan:

```dotenv
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_IDLE_TIMEOUT=30
MAIL_MAILER=log
```

- `SESSION_IDLE_TIMEOUT` adalah batas idle terautentikasi dalam menit (minimal 1). Default 30 menit.
  Daftar/pengakhiran sesi lintas perangkat hanya tersedia bila `SESSION_DRIVER=database`; driver lain
  akan menampilkan keterbatasan di halaman Keamanan.
- `MAIL_MAILER=log` **tidak mengirim email nyata**; tautan reset password ditulis ke
  `storage/logs/laravel.log`. Jangan mengaktifkan SMTP produksi di mesin pengembangan. Untuk produksi,
  isi konfigurasi SMTP/TLS resmi melalui environment dan uji pengiriman ke kotak masuk nyata sebelum
  go-live. Jangan menaruh kredensial email di repositori.
- Test memakai `Notification::fake()` dan `MAIL_MAILER=array`, sehingga tidak ada email keluar.

### 3.2 Konfigurasi F8 (PROVISIONAL)

Semua nilai berikut **bukan** aturan resmi dinas dan dapat diubah tanpa mengubah kode:

```dotenv
# Default nonaktif. Aktifkan hanya setelah dinas menetapkan pembagian wilayah (Q-005).
ADMIN_REGION_SCOPE_ENABLED=false
# Ambang peringatan izin mendekati kedaluwarsa (hari).
LICENSE_EXPIRING_DAYS=30
# Minimal rapat tahunan untuk checklist kepatuhan (PROVISIONAL).
GOVERNANCE_MIN_MEETINGS=2
```

- Bobot/ambang kesehatan dan label kepatuhan berada di `config/koperasi.php`
  (`health_assessment`, `governance_compliance`). Formula resmi belum ada (Q-007).
- Saat `ADMIN_REGION_SCOPE_ENABLED=true`, akun `admin_wilayah` **wajib** memiliki `region_id`;
  `admin_dinas` tetap super admin. Jangan mengaktifkan di produksi tanpa keputusan resmi.
- Ekspor laporan memakai CSV dan hanya data `approved`; tidak ada paket Excel/PDF tambahan.

### 3.3 Header keamanan & reverse proxy (produksi)

```dotenv
SECURITY_HSTS_ENABLED=true
SECURITY_HSTS_MAX_AGE=31536000
SECURITY_HSTS_INCLUDE_SUBDOMAINS=false
SECURITY_HSTS_PRELOAD=false
# Nonaktif secara default agar Vite HMR lokal tidak terganggu.
SECURITY_CSP_ENABLED=false
SECURITY_CSP_REPORT_ONLY=false
SECURITY_X_FRAME_OPTIONS=DENY
# Isi hanya bila di belakang reverse proxy/load balancer.
TRUSTED_PROXIES=
```

- HSTS hanya dikirim pada request HTTPS. Jangan aktifkan `includeSubDomains`/`preload` sebelum
  domain resmi dan seluruh subdomainnya dipastikan HTTPS.
- Di produksi, `SECURITY_CSP_ENABLED=true` **wajib**. CSP default memakai `script-src 'self'` tanpa
  `unsafe-inline`; inline `onsubmit` sudah diganti `data-confirm` + listener di `resources/js/app.js`.
  `style-src` masih mengizinkan atribut style inline untuk progress bar.
- Bila memakai `npm run dev`, biarkan CSP nonaktif. Setelah build produksi, uji ulang halaman dengan
  DevTools Console agar tidak ada pelanggaran CSP.
- `TRUSTED_PROXIES` dibaca dari `config/trustedproxy.php`; ini diperlukan agar Laravel mendeteksi
  HTTPS dari `X-Forwarded-Proto` di belakang proxy. Jangan gunakan `*` pada host yang dapat diakses
  langsung dari internet.
- `php artisan app:production-check` memeriksa HSTS/CSP dan akan gagal bila CSP masih nonaktif.

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

### 4.1 Referensi wilayah (opsional, Q-012)

Setelah tabel `regions` ada, dataset resmi Jawa Barat dapat diimpor (idempoten, transaksional):

```powershell
php artisan regions:import --dry-run --expected=province:1,regency:27,district:627,village:5957
php artisan regions:import --expected=province:1,regency:27,district:627,village:5957
php artisan regions:audit-cooperatives
```

Sumber, checksum, lisensi, dan batas data: `docs/REGION_DATA_SOURCES.md`. Jangan mengimpor data
karangan dan jangan mengubah `cooperatives.region_id` menjadi `NOT NULL` tanpa audit/backfill.

Database lokal pengembangan sudah diimpor pada 26 Oktober 2026 (6.612 baris). Langkah di atas tetap
wajib untuk instalasi baru/staging. Audit pada database lokal menemukan satu koperasi tanpa wilayah,
jadi **tidak ada backfill** dan `region_id` tetap nullable.

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

## 6. Membuat Akun Pengguna Pertama

Tidak ada akun admin dengan password bawaan di dalam source code. Untuk membuat
akun pertama, jalankan perintah interaktif berikut (password ditanyakan dan
disimpan sebagai hash):

```powershell
php artisan user:create
```

Perintah akan menanyakan: nama, email, peran (`admin_dinas` atau
`pengelola_koperasi`), password (minimal 8 karakter), dan untuk peran pengelola
juga menanyakan ID koperasi (boleh dikosongkan).

`php artisan db:seed` hanya menyiapkan data **referensi** (daftar peran) dan tidak
membuat akun apa pun.

Peran yang tersedia: `admin_dinas`, `pengelola_koperasi`, dan `masyarakat`.
`masyarakat` adalah peran konteks publik (akses tanpa login) dan tidak ditautkan
ke akun login, sesuai PRD §8.

### 6.1 Akun berikutnya, reset password, dan pengaturan publik

- Admin dinas yang sudah login dapat membuka **Akun Pengelola** (`/admin/akun`) untuk mencari,
  membuat, mengubah, menautkan koperasi, serta mengaktifkan/menonaktifkan akun. Password akun baru
  dibuat acak dan tidak pernah ditampilkan; tautan reset dikirim ke email akun.
- Halaman **Lupa password** (`/lupa-password`) tersedia untuk pengguna. Di lokal, tautan reset masuk
  ke log (`MAIL_MAILER=log`), bukan email nyata. Token berlaku 60 menit dan hanya sekali pakai.
- Halaman **Keamanan** (`/akun/keamanan`) menampilkan batas idle dan sesi aktif. Daftar/pengakhiran
  sesi lintas perangkat memerlukan `SESSION_DRIVER=database`.
- Admin dinas dapat mengatur field publik di **Pengaturan Publik** (`/admin/pengaturan/publik`).
  Batas maksimum tetap Q-008; NIK, telepon pribadi, data anggota, dan omzet terlarang tidak dapat
  dipilih.
- Tabel `settings` dibuat oleh migrasi aditif; jalankan `php artisan migrate` pada database
  pengembangan/staging yang memang diizinkan. **Jangan** menjalankan `migrate:fresh` pada database
  berisi data.

### Data demo (opsional, opt-in)

Untuk demonstrasi/pengujian manual modul perizinan & dokumen tersedia seeder data contoh yang
**tidak** berjalan otomatis:

```powershell
php artisan db:seed --class=DemoSeeder
```

- `php artisan db:seed` tetap hanya meng-seed data **referensi** (peran); `DemoSeeder` **tidak** terdaftar di `DatabaseSeeder`.
- Seeder menolak berjalan bila `APP_ENV=production`.
- Tidak membuat akun; dokumen contoh memakai akun yang sudah ada (buat dulu dengan `user:create`).
- Idempoten (aman dijalankan berulang); data contoh diberi penanda `DEMO-`.

## 7. Pengujian

Suite default memakai SQLite `:memory:`:

```powershell
php artisan test
```

Untuk memverifikasi driver MySQL/MariaDB, gunakan database uji **terpisah** dan konfigurasi contoh
(lihat `phpunit.mariadb.xml.example`):

```powershell
Copy-Item phpunit.mariadb.xml.example phpunit.mariadb.xml
# edit DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD ke database uji khusus
php vendor\bin\phpunit -c phpunit.mariadb.xml --no-coverage
```

`RefreshDatabase` menjalankan migrasi bersih pada database uji. **Jangan** mengarahkan konfigurasi ini
ke database staging/produksi berisi data. Pada 26 Oktober 2026 suite yang sama lulus pada MariaDB
10.11.19 portabel lokal dan MariaDB 10.4.32 lokal.

Pemeriksaan kesiapan produksi (read-only):

```powershell
php artisan app:production-check
php artisan app:production-check --json
```

Perintah memeriksa konfigurasi wajib, header keamanan, izin storage, aset build, koneksi/versi
database, migrasi tertunda, data wilayah, dan akun admin. Exit code `1` bila ada `FAIL`.

### 7.1 Verifikasi backup/restore

Script `scripts/db-backup-verify.sh` ditujukan untuk host Linux produksi. Script membuat dump
`.sql.gz`, checksum SHA-256, me-restore ke database scratch, membandingkan daftar tabel dan jumlah
baris, lalu menghapus scratch:

```sh
DB_CNF=/etc/koperasi/backup.cnf \
DB_DATABASE=koperasi_jabar \
BACKUP_DIR=/var/backups/koperasi \
bash scripts/db-backup-verify.sh
```

Gunakan `DB_CNF` berisi kredensial `[client]` user backup; jangan menaruh password di argumen proses.
`VERIFY=0` hanya untuk backup cepat, bukan pengganti uji restore. Jangan mengarahkan `VERIFY_DB` ke
database sumber atau database sistem.

Pada Windows, verifikasi lokal dapat dilakukan dengan `mysqldump`/`mysql` native ke database scratch;
client `mysql.exe` melalui pipe Git Bash tidak kompatibel. Hasil verifikasi lokal 26 Oktober 2026:
26 tabel, jumlah baris sama, dump 660.646 byte, SHA-256
`a1e5e3fafe039df98013d428884129abcc32ddf3b0de28f500ef9b5a39bcac22`. Ini **bukan** bukti restore
staging/produksi.

## 8. Struktur Direktori (ringkas)

```text
app/                 # kode aplikasi (controllers, models, providers)
config/              # konfigurasi Laravel
database/            # migrations, seeders, factories
docs/                # PRD, data dictionary, user flows, implementation plan, setup, versions
public/              # document root (index.php, build assets)
resources/
  css/app.css        # entry Tailwind CSS v4 (@import 'tailwindcss')
  js/app.js          # entry JavaScript
  views/             # Blade (layouts/, components/, auth/, admin/, pengelola/)
routes/web.php       # route web (publik + terautentikasi)
tests/               # Feature & Unit test
vite.config.js       # integrasi Laravel Vite + @tailwindcss/vite
```

## 9. Tailwind CSS v4

Integrasi resmi `@tailwindcss/vite` sudah dikonfigurasi (lihat `vite.config.js` dan `resources/css/app.css`). Tidak perlu `tailwind.config.js` pada v4; token tema didefinisikan via directive `@theme` di `resources/css/app.css`.

Font `Instrument Sans` dimuat melalui plugin fonts `laravel-vite-plugin`. Optimasi font fallback (`fontaine`) **tidak** diaktifkan (`optimizedFallbacks: false`) agar tidak menambah dependency di luar kebutuhan F0.

## 10. Troubleshooting

| Gejala | Penyebab | Solusi |
| --- | --- | --- |
| `npm : ... running scripts is disabled` | Kebijakan eksekusi PowerShell | Gunakan `npm.cmd` |
| Build memperingatkan `Optimized font fallbacks require ... fontaine` | Plugin fonts tanpa `fontaine` | Sudah dinonaktifkan via `optimizedFallbacks: false` |
| `php artisan db:show` error `performance_schema.session_status` | Keterbatasan MariaDB 10.4 XAMPP | Abaikan; gunakan `migrate:status` atau query langsung |
| Pesan `could not find driver` | Ekstensi `pdo_mysql` tidak aktif | Aktifkan `pdo_mysql`/`mysqli` di `php.ini` |
| Aset tidak muncul / `Vite manifest not found` | Belum build / belum menjalankan dev server | Jalankan `npm.cmd run build` atau `npm.cmd run dev` |

## 11. Catatan Fase

**F0 — Fondasi:** inisialisasi proyek, konfigurasi Blade + Vite + Tailwind v4, koneksi MariaDB via environment, layout dasar + halaman awal, konfigurasi Git.

**F1 — Autentikasi & Otorisasi (fondasi):**

- Autentikasi memakai **session guard bawaan Laravel** (`Auth::attempt`). **Tidak ada paket autentikasi eksternal** (Breeze/Jetstream/Sanctum) yang ditambahkan.
- Otorisasi berbasis peran: tabel `roles` + pivot `role_user`, middleware `role:...` (`EnsureUserHasRole`) dan `active` (`EnsureUserIsActive`), serta Gate `access-koperasi` untuk mencegah akses lintas koperasi (anti-IDOR).
- Semua pemeriksaan hak akses dilakukan **di server**, bukan sekadar menyembunyikan menu.
- Relasi `users.koperasi_id` → `cooperatives.id` **belum diberi foreign key** karena tabel `cooperatives` baru dibuat pada F2. Foreign key ditambahkan pada migrasi F2.
- **FR-003, FR-004, dan US-009 sudah tersedia:** idle timeout (`SESSION_IDLE_TIMEOUT`, default 30 menit), daftar/pengakhiran sesi pada driver database (`/akun/keamanan`), reset password self-service/admin (`/lupa-password`), dan manajemen akun pengelola (`/admin/akun`). Provisioning akun pertama tetap memakai `php artisan user:create`; akun berikutnya dapat dibuat lewat UI dengan tautan reset, tanpa password teks biasa. CRUD koperasi dan workflow verifikasi sudah tersedia pada fase berikutnya.
- Laravel Boost **tidak** digunakan (keputusan F0 & F1). Instruksi Boost bawaan scaffold telah dihapus dari `AGENTS.md` & `CLAUDE.md`.

**F3 — Perizinan & Dokumen:**

- Tabel `cooperative_licenses` (izin usaha & izin usaha simpan pinjam) dan `documents` (polimorfik, F3 menempel ke izin).
- Admin: CRUD izin + verifikasi status (FR-051/061) + unggah/unduh/arsip dokumen.
- Pengelola: **hanya baca** izin koperasinya dan **mengunduh** dokumen koperasinya (FR-071, anti-IDOR/AC-DOC-03).
- Dokumen disimpan di **storage privat** `storage/app/private/documents/{koperasi_id}/...`; hanya dapat
  diunduh lewat controller ber-Policy. Tidak ada symlink publik (`public/storage` tidak dibuat).
- Seeder data demo opt-in: `php artisan db:seed --class=DemoSeeder`.

**Penting — batas unggah PHP vs batas aplikasi.** Batas dokumen di aplikasi ditetapkan
`config('koperasi.document_max_size_kb')` (5 MB). PHP sendiri punya batas `upload_max_filesize`
(bawaan **2M**) dan `post_max_size` (bawaan **8M**). Pada banyak instalasi PHP bawaan, berkas
> 2 MB akan ditolak PHP **sebelum** validasi Laravel berjalan (muncul pesan generik, bukan pesan
"melebihi batas" milik aplikasi), sedangkan berkas > `post_max_size` membuat isi request kosong.
Samakan/naikkan batas PHP minimal sebesar batas aplikasi, mis.:

```ini
; php.ini
upload_max_filesize = 8M
post_max_size = 12M
```

`post_max_size` harus **lebih besar** dari `upload_max_filesize`. Nilai di atas adalah contoh
minimum agar fitur unggah 5 MB berjalan; sesuaikan dengan kebijakan ukuran dokumen dinas.

Lihat `docs/IMPLEMENTATION_PLAN.md` untuk rencana fase berikutnya.
