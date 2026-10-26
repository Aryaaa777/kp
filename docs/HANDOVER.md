# Serah Terima (Handover) MVP — Sistem Informasi Pendataan dan Pemantauan Koperasi Provinsi Jawa Barat

> **Status dokumen:** serah terima teknis untuk finalisasi MVP, diperbarui **27 Oktober 2026**
> setelah penyelesaian F8, impor wilayah, pengerasan produksi, dan CI MariaDB.
> **Status go-live:** **BELUM siap go-live.** Pengerasan kode (header keamanan, preflight), suite
> MariaDB 10.11.19 lokal, dan prosedur backup/restore lokal sudah diverifikasi; staging/HTTPS/SMTP
> dan uji restore ke staging belum tersedia. Lihat §8. Jangan menafsirkan dokumen ini sebagai
> persetujuan produksi.
>
> Dokumen ini merangkum apa yang sudah diimplementasikan, bukti verifikasi lokal, gap sementara,
> kebutuhan produksi, langkah instalasi/deployment yang aman, penanganan data lama, dan blocker
> eksternal. Sumber kebenaran tetap `docs/PRD.md`; dokumen ini tidak mengubah keputusan PRD.

## 1. Ringkasan eksekutif

- Implementasi MVP **F0-F8** untuk cakupan yang disepakati sudah tersedia. Suite penuh lulus pada
  SQLite `:memory:` dan pada MariaDB **10.11.19 portabel lokal** (database uji terpisah). F8
  diselesaikan secara lokal pada 26 Oktober 2026.
- **F8 selesai secara implementasi lokal:** FR-012, FR-022, FR-072, FR-082, FR-091, FR-171, FR-183.
  Formula/ambang/pembatasan wilayah tetap **PROVISIONAL** (Q-005/Q-007/Q-010).
- Empat migrasi yang sebelumnya `Pending` dan empat migrasi F8 **sudah dijalankan pada database
  lokal** MariaDB 10.4.32 (total **30 Ran, 0 Pending**) dan diuji pada MariaDB 10.11.19 portabel.
  Database staging/produksi tidak tersedia.
- Database lokal sekarang berisi `regions` = **6.612**, `cooperatives` = 1, dan tabel `settings`
  sudah ada. Tidak ada backfill `region_id`; koperasi `id = 1` tetap `NULL`.
- Tidak ada `migrate:fresh`, rollback massal, atau penghapusan data. Backup lokal dibuat sebelum
  migrasi di luar repositori.
- Satu bug integrasi nyata ditemukan dan diperbaiki: validasi tautan akun pengelola masih menerima
  koperasi yang sudah **soft-deleted** melalui UI admin dan perintah `user:create`. Perbaikan dan tes
  regresinya dijelaskan di §2.

## 2. Bug integrasi yang ditemukan dan diperbaiki

### 2.1 Tautan akun ke koperasi yang sudah dihapus (soft delete)

**Dampak:** admin dapat membuat akun pengelola baru—atau memindahkan akun yang ada—ke koperasi yang
sudah di-soft-delete bila request dibuat langsung (di luar pilihan dropdown). Perintah
`user:create` juga menerima ID tersebut. Ini melanggar integritas tautan akun–koperasi dan membuat
akun menunjuk data yang secara operasional sudah diarsipkan.

**Penyebab:** aturan validasi memakai `exists:cooperatives,id`, yang memeriksa tabel mentah dan
tidak menerapkan global scope `SoftDeletes`.

**Perbaikan:**
- `app/Http/Requests/Admin/StoreUserRequest.php`
- `app/Http/Requests/Admin/UpdateUserRequest.php`
- `app/Console/Commands/CreateUserCommand.php`

Ketiganya kini memakai:

```php
Rule::exists('cooperatives', 'id')->whereNull('deleted_at')
```

**Tes regresi:**
- `tests/Feature/Admin/AdminUserManagementTest.php`
  - `test_create_rejects_a_soft_deleted_cooperative`
  - `test_update_rejects_a_soft_deleted_cooperative`
- `tests/Feature/Console/CreateUserCommandTest.php`
  - `test_it_rejects_a_soft_deleted_koperasi_id`

Tidak ada bug integrasi lain yang terkonfirmasi pada alur yang diperiksa (login/logout, idle timeout,
daftar/akhiri sesi, reset password, manajemen akun, pengaturan publik, publikasi/audit/otorisasi/
anti-IDOR/soft-delete, alur wilayah, validasi, dan navigasi layar kecil).

## 3. Cakupan MVP yang sudah tersedia

| Area | Status | Catatan |
| --- | --- | --- |
| Autentikasi, logout, RBAC, anti-IDOR | Selesai | Session guard bawaan Laravel; middleware `role`, `active`, Policy/Gate. |
| FR-003 manajemen sesi | Selesai untuk driver `database` | Idle timeout default 30 menit; daftar/akhiri sesi lain/semua. Driver non-database menampilkan keterbatasan. |
| FR-004 reset password | Selesai | Token hash, sekali pakai, 60 menit; respons generik; tautan reset dari admin. |
| US-009 manajemen akun | Selesai | Daftar/cari/tambah/ubah/status/kirim reset; password acak tidak pernah ditampilkan; tautan koperasi divalidasi. |
| F2 data induk & wilayah | Selesai | CRUD koperasi, form wilayah berjenjang, importer/audit wilayah; dataset diimpor ke DB lokal (6.612 baris), backfill `region_id` belum (Q-012). |
| F3 perizinan & dokumen | Selesai | Izin usaha/simpan pinjam, verifikasi, dokumen privat, unduh ber-otorisasi. |
| F4 data periodik | Selesai | Aset, anggota, kesehatan, tata kelola, pengurus; definisi periode **PROVISIONAL** (Q-010). |
| F5 workflow & audit | Selesai | Pengajuan, review, revisi/versi, penerapan transaksional, audit log, notifikasi in-app. |
| F6 dashboard/pencarian/laporan | Selesai untuk cakupan disepakati | Filter wilayah/jenis/status/periode; laporan agregat; periode memakai `YEAR(cooperatives.created_at)` **PROVISIONAL**. |
| F7 direktori/publikasi/FR-191 | Selesai | Direktori/profil publik, publikasi/penarikan, pengaturan field publik dengan batas Q-008; tabel `settings` sudah dimigrasikan di DB lokal. |
| FR-171 ekspor | Selesai | Ekspor CSV laporan (hanya `approved`) + audit ekspor. Excel/PDF tidak ditambahkan. |
| FR-183 statistik publik | Selesai | `/statistik` agregat; hanya data published + approved. |
| FR-012 sub-peran admin/pembatasan wilayah | Selesai (PROVISIONAL) | Peran `admin_wilayah`, `users.region_id`, Policy/middleware; default **nonaktif** sampai Q-005. |
| FR-022 izin mendekati kedaluwarsa | Selesai (PROVISIONAL) | Ambang hari default 30, konfigurabel. |
| FR-072 versi dokumen | Selesai | `previous_version_id` + `superseded_at`; berkas lama dipertahankan. |
| FR-082/FR-091 kesehatan & kepatuhan | Selesai (PROVISIONAL) | Formula/checklist sementara di `config/koperasi.php`, bukan aturan resmi (Q-007). |

## 4. Verifikasi final yang dijalankan

Perintah dan hasil pada lingkungan lokal:

| Pemeriksaan | Hasil |
| --- | --- |
| `php artisan test --no-coverage` | **610 tes, 609 lulus, 1 skipped, 2.728 asersi** (SQLite `:memory:`). |
| `php vendor/bin/phpunit -c phpunit.mariadb.xml --no-coverage` (DB_PORT=3307) | **610 tes, 609 lulus, 1 skipped, 2.733 asersi** (MariaDB 10.11.19 portabel, DB uji terpisah). |
| `php vendor/bin/phpunit -c phpunit.mariadb.xml --no-coverage` (DB_PORT=3306) | **610 tes, 609 lulus, 1 skipped, 2.733 asersi** (MariaDB 10.4.32 lokal). |
| `php artisan app:production-check` pada MariaDB 10.11.19 | **32 PASS, 2 WARN, 0 FAIL** dengan konfigurasi produksi, wilayah, dan admin uji. Bukan staging. |
| Verifikasi backup/restore lokal | **26 tabel** berhasil direstore ke DB scratch; jumlah baris sama; dump 660.646 byte; SHA-256 `a1e5e3fa...`. Bukan staging/produksi. |
| Workflow CI `.github/workflows/ci.yml` | YAML valid; suite MariaDB lokal lulus; **run GitHub belum diamati**. CI bukan staging. |
| `vendor/bin/pint --test` | PASS. |
| `npm.cmd run build` | PASS; aset produksi terbentuk. |
| `git diff --check` | Bersih (tanpa output). |
| PHP syntax check (`php -l`) pada file PHP yang berubah | 0 error. |
| `php artisan migrate:status` | **30 migrasi `Ran`, 0 `Pending`** pada DB lokal MariaDB 10.4.32. |
| Query baca-saja database lokal | `regions=6612`, `cooperatives=1`, `settings_table=yes`. |
| Audit `region_id` | 1 koperasi, 0 dengan wilayah, 1 `NULL`, 0 non-desa. |
| Smoke HTTP lokal | `/statistik`, `/koperasi`, `/login` → **200**. |
| `composer audit` | Tidak ada advisory keamanan. |

> **Penting:** suite MariaDB 10.11.19 di atas berjalan pada server **portabel lokal** dengan database
> uji terpisah, bukan staging/produksi. `sql_mode` produksi, web server, HTTPS, SMTP, dan
> backup/restore **ke staging** belum diuji. Verifikasi staging tetap wajib.

## 5. Review migrasi yang sebelumnya pending

Empat migrasi berikut sudah dijalankan pada database lokal MariaDB 10.4.32 dan diuji pada MariaDB
10.11.19 portabel. Review ini merangkum objek dan rollback; rollback destruktif tetap memerlukan
persetujuan eksplisit.

### 5.1 Urutan

1. `2026_10_19_000001_add_parent_submission_id_to_submissions_table`
2. `2026_10_24_000001_add_unpublished_tracking_to_cooperatives_table`
3. `2026_10_24_000002_add_status_operasional_to_cooperatives_table`
4. `2026_10_24_000003_create_settings_table`

Semua berada setelah tabel yang direferensikan (`submissions`, `cooperatives`, `users`). Tidak ada
dependensi maju di antara keempatnya. Migrasi harus dijalankan **terserialisasi**; Laravel tidak
menyediakan migration lock bawaan.

### 5.2 Ringkasan objek dan rollback

| Migrasi | Objek | Tipe / constraint | Rollback |
| --- | --- | --- | --- |
| `2026_10_19_000001_*` | `submissions.parent_submission_id` | `BIGINT UNSIGNED NULL` setelah `id`; self-FK ke `submissions.id` `ON DELETE SET NULL`; indeks `submissions_parent_submission_id_index` (non-unik, kolom tunggal). | Drop FK yang definisinya terverifikasi → drop indeks milik migrasi → drop kolom. **Destruktif** terhadap rantai versi; butuh persetujuan eksplisit. |
| `2026_10_24_000001_*` | `cooperatives.unpublished_at`, `cooperatives.unpublished_by` | `TIMESTAMP NULL`; `BIGINT UNSIGNED NULL`; FK `unpublished_by` → `users.id` `ON DELETE SET NULL`. Tidak ada indeks eksplisit; MySQL/MariaDB membuat indeks pendukung FK secara otomatis (verifikasi nama/definisi di staging). | Drop FK → drop kedua kolom. Menghilangkan jejak penarikan terakhir; tidak menghapus kolom publikasi lama. |
| `2026_10_24_000002_*` | `cooperatives.status_operasional` | `ENUM('aktif','tidak_aktif','bubar') NULL`, tanpa default; tidak ada backfill. | Drop kolom; **menghilangkan** status operasional yang sudah diisi. |
| `2026_10_24_000003_*` | Tabel `settings` | `id` PK auto-increment; `key VARCHAR(100) UNIQUE`; `value TEXT NULL`; `timestamps` nullable. Tidak ada FK. | `DROP TABLE settings`; **menghilangkan** seluruh pengaturan (termasuk pilihan field publik). |

### 5.3 Catatan dan batas review

- Migrasi `2026_10_19_000001_*` paling ketat: memvalidasi kolom, indeks, dan self-FK secara terpisah,
  mendeteksi konflik, dan memverifikasi objek setelah DDL. Pada SQLite self-FK sengaja dilewati;
  validasi FK memerlukan MariaDB.
- Migrasi `2026_10_24_000001_*` memeriksa keberadaan kolom dan **keberadaan FK pada kolom**
  `unpublished_by`, tetapi tidak membandingkan definisi FK secara penuh. Pada instalasi bersih ini
  aman. Bila skema lama sudah memiliki FK menyimpang pada kolom tersebut, migrasi tidak akan
  memperbaikinya dan `down()` dapat melepas FK yang ada. **Jangan** menjalankan migrasi ini pada
  skema yang tidak bersih tanpa inspeksi manual.
- `2026_10_24_000002_*` dan `2026_10_24_000003_*` bersifat aditif dan memeriksa keberadaan objek,
  tetapi tidak memvalidasi tipe/definisi objek lama bila nama sudah ada.
- Perilaku DDL nyata (termasuk `ENUM`, FK, dan `AFTER id`) telah diuji pada MariaDB 10.11.19
  portabel lokal. Staging dengan `sql_mode` produksi tetap wajib diverifikasi sebelum go-live.

## 6. Gap sementara / keputusan yang belum final

| Item | Status | Dampak |
| --- | --- | --- |
| Q-010 definisi resmi periode | Terbuka | Filter/laporan memakai `YEAR(cooperatives.created_at)` **[PROVISIONAL]**. Jangan mengklaim sebagai definisi resmi. |
| Q-006 whitelist field pengajuan | Terbuka | Workflow saat ini hanya mengaktifkan modul data induk. |
| Q-022 format laporan pimpinan | Terbuka | Laporan masih agregat dasar, belum format eksekutif final. |
| Q-001/Q-003 identitas & jenis/bentuk koperasi | Terbuka | Format nomor identitas dan taksonomi jenis/bentuk menunggu dinas. |
| Q-012 data wilayah | Sebagian | Dataset 6.612 baris **sudah diimpor ke DB lokal**; backfill `region_id` dan `NOT NULL` belum karena audit belum bersih. |
| Q-014 infrastruktur produksi | Sebagian | MariaDB 10.11.19 dan backup/restore sudah diuji **lokal**; staging resmi, HTTPS/domain `.go.id`, SMTP, dan restore ke staging belum diverifikasi. |
| Q-005 pembatasan wilayah | Terbuka | Peran `admin_wilayah` tersedia, tetapi scope **nonaktif** secara default sampai aturan resmi. |
| Q-007 formula kesehatan/kepatuhan | Terbuka | Formula 5 komponen dan checklist kepatuhan bersifat **PROVISIONAL**, bukan aturan resmi. |
| FR-003 driver sesi | Terbatas | Daftar/akhiri sesi lintas perangkat hanya dengan `SESSION_DRIVER=database`; driver lain menampilkan pemberitahuan. |
| FR-004 email produksi | Belum diverifikasi | Lokal memakai `MAIL_MAILER=log`; pengiriman SMTP nyata belum diuji. |
| FR-171 / FR-183 | Selesai lokal | Ekspor CSV + audit; statistik publik agregat. Verifikasi produksi tetap Q-014. |
| F8 | Selesai lokal | Sub-peran admin, pembatasan wilayah, ekspor, statistik publik, versi dokumen, formula kesehatan. |
| Slug publik lama | Status quo | Slug lama yang bertabrakan dengan segmen internal tidak diubah; keputusan sementara tercatat di `docs/DECISIONS.md`. |

## 7. Kebutuhan produksi sebelum go-live

Semua item berikut **wajib** diselesaikan dan dicatat buktinya:

1. **Staging representatif** dengan MariaDB **10.11 LTS**, PHP 8.3-8.5, dan konfigurasi `sql_mode`
   produksi. Jalankan migrasi di staging dan suite pada **database uji terpisah**; `phpunit.xml`
   memakai SQLite `:memory:`, jadi suite default bukan bukti MariaDB. Jangan arahkan suite ke DB
   staging berisi data. Suite MariaDB 10.11.19 portabel lokal sudah lulus, tetapi bukan staging.
2. **Verifikasi DDL/FK/indeks** pada seluruh migrasi (termasuk empat migrasi F8) di staging
   MariaDB 10.11, termasuk enum, self-FK, FK `unpublished_by`, FK `previous_version_id`, dan indeks
   pendukung. DDL/FK sudah diuji pada MariaDB 10.11.19 lokal; staging tetap wajib.
3. **Backup & restore**: backup database harian + filesystem mingguan ke lokasi remote; uji restore
   ke staging sebelum go-live. Gunakan `scripts/db-backup-verify.sh` (dump `.sql.gz`, checksum,
   restore ke scratch, bandingkan tabel/baris). Verifikasi lokal 26 tabel sudah lulus, tetapi
   backup yang belum pernah di-restore di staging belum layak dianggap.
4. **HTTPS/domain resmi** `.go.id`, redirect HTTP → HTTPS, dan monitoring sertifikat.
5. **Email produksi**: SMTP/TLS resmi, alamat pengirim sah, uji tautan reset ke kotak masuk nyata.
6. **Sesi produksi**: `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, idle timeout sesuai
   kebijakan, dan tabel `sessions` tersedia.
7. **Operasional Laravel**: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `config:cache`,
   `route:cache`, `view:cache`, queue worker, scheduler, izin `storage/`/`bootstrap/cache/`, dan
   `storage:link` bila diperlukan.
8. **Akun awal** hanya lewat `php artisan user:create`; tidak ada akun default berpassword.
9. **Data wilayah**: impor dataset resmi secara idempoten setelah checksum diverifikasi; audit
   `region_id`; jangan backfill otomatis atau `NOT NULL` sebelum audit tuntas.
10. **Smoke test produksi**: login admin, direktori/profil publik, publikasi/penarikan, reset
    password, idle timeout/daftar sesi, pengaturan field publik, laporan/filter, dan halaman error
    ramah tanpa stack trace.

## 8. Status go-live

**BELUM siap go-live.** Finalisasi ini menyatakan bahwa implementasi MVP untuk cakupan yang
disepakati sudah diuji secara lokal, termasuk suite MariaDB 10.11.19 portabel dan
`app:production-check`. Go-live baru boleh dipertimbangkan setelah:

- staging MariaDB 10.11 terverifikasi (suite lokal 10.11.19 sudah lulus);
- backup **dan restore** teruji di staging/produksi (verifikasi lokal 26 tabel sudah lulus);
- email produksi teruji;
- HTTPS/domain resmi aktif dan header keamanan terverifikasi;
- migrasi dijalankan dan diverifikasi di staging;
- data wilayah serta status operasional ditangani sesuai §9;
- pemilik proyek menyetujui secara eksplisit.

## 9. Instalasi bersih (clean install)

Contoh untuk lingkungan pengembangan/staging yang memang diizinkan:

```powershell
composer install
npm.cmd ci
Copy-Item .env.example .env
php artisan key:generate
# isi kredensial DB dan konfigurasi lain di .env; jangan commit .env
php artisan migrate
php artisan storage:link
npm.cmd run build
php artisan user:create
```

Konfigurasi minimum yang perlu diperiksa:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_TIMEZONE=Asia/Jakarta
DB_CONNECTION=mysql
DB_DATABASE=koperasi_jabar
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_IDLE_TIMEOUT=30
MAIL_MAILER=log
```

Untuk produksi, ganti `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`,
`MAIL_MAILER=smtp`, `SESSION_SECURE_COOKIE=true`, `SECURITY_HSTS_ENABLED=true`, dan
`SECURITY_CSP_ENABLED=true`. Isi `TRUSTED_PROXIES` bila berada di belakang reverse proxy. Jalankan
`php artisan app:production-check` dan pastikan **0 FAIL**. **Jangan** memakai `migrate:fresh` pada
database berisi data.

### 9.1 Impor wilayah (opsional, setelah tabel `regions` ada)

```powershell
php artisan regions:import --dry-run --expected=province:1,regency:27,district:627,village:5957
php artisan regions:import --expected=province:1,regency:27,district:627,village:5957
php artisan regions:audit-cooperatives
```

Jangan mengimpor data karangan. Jangan mengubah `region_id` menjadi `NOT NULL` sebelum audit dan
backfill deterministik selesai.

## 10. Deployment aman (staging/produksi)

1. **Backup** database dan `storage/app` sebelum deployment; catat checksum. Untuk database,
   gunakan `scripts/db-backup-verify.sh` dengan `DB_CNF` dan `BACKUP_DIR` (lihat
   `docs/DEPLOYMENT_CHECKLIST.md` §5).
2. Deploy kode, lalu `composer install --no-dev --optimize-autoloader`.
3. Build aset dengan Node 22 (`npm ci && npm run build`); Node tidak perlu berjalan di produksi.
4. Set `.env` produksi dan izin file; jangan menaruh secret di repositori.
5. Jalankan migrasi **terserialisasi** dan hanya setelah backup:
   `php artisan migrate --force`, lalu `php artisan migrate:status`.
6. Jalankan `php artisan config:cache`, `route:cache`, `view:cache`, lalu
   `php artisan app:production-check` (wajib **0 FAIL**) dan simpan keluaran `--json`.
7. Smoke test alur kritis, periksa header keamanan, dan periksa log.
8. Jika migrasi gagal, **jangan** menjalankan rollback massal atau `migrate:fresh`. Hentikan
   deployment, periksa skema, dan pulihkan dari backup bila perlu. Rollback migrasi destruktif hanya
   dengan persetujuan eksplisit.

## 11. Penanganan data lama (legacy)

- **`regions` pada database lokal sudah berisi 6.612 baris.** Pada target produksi, impor harus
  dijalankan dengan urutan aman di `docs/REGION_DATA_SOURCES.md`; sebelum impor, alamat publik
  menampilkan “Belum ditetapkan”.
- **`cooperatives.region_id` tetap nullable.** Data lama boleh menunjuk kecamatan/kabupaten/kota/
  provinsi; audit read-only akan menandai temuan. Jangan menebak atau membackfill otomatis.
  Database lokal: 1 koperasi, `region_id = NULL`, tanpa backfill.
- **`cooperatives.status_operasional` sudah ada di database lokal** (migrasi
  `2026_10_24_000002_*`). Baris lama bernilai `NULL` = belum ditetapkan; **jangan** menganggap semua
  aktif.
- **`submissions.parent_submission_id` sudah ada di database lokal** (migrasi
  `2026_10_19_000001_*`). `NULL` berarti versi awal; tidak ada backfill.
- **`cooperatives.unpublished_at`/`unpublished_by` sudah ada di database lokal** (migrasi
  `2026_10_24_000001_*`). `NULL` berarti belum pernah ditarik; riwayat siklus ada di `activity_logs`.
- **Tabel `settings` sudah ada di database lokal** (migrasi `2026_10_24_000003_*`). Bila tabel belum
  ada pada target, aplikasi memakai default config yang aman.
- **Kolom F8** (`users.region_id`, komponen kesehatan, kepatuhan tata kelola, versi dokumen) sudah
  ada di database lokal; pada target produksi jalankan migrasi sebelum mengaktifkan fitur.

## 12. Blocker eksternal

| ID | Blocker | Dampak |
| --- | --- | --- |
| Q-014 | Tidak ada akses staging/domain/kredensial; MariaDB 10.11.19 dan backup/restore sudah diuji lokal, tetapi HTTPS, SMTP, dan restore ke staging belum diverifikasi | Go-live tidak dapat dinyatakan. |
| Q-012 | Impor wilayah **lokal** selesai, tetapi backfill/`region_id NOT NULL` belum; target produksi belum diimpor | Alamat/wilayah publik pada target bergantung pada impor; data lama tanpa wilayah tetap ditandai audit. |
| Q-010 | Definisi resmi periode belum ada | Filter/laporan memakai definisi sementara. |
| Q-006 / Q-022 | Whitelist field dan format laporan pimpinan belum final | Cakupan workflow/laporan terbatas. |
| Q-001 / Q-003 | Identitas dan jenis/bentuk koperasi belum dikonfirmasi | Validasi/taksonomi dapat berubah. |
| Email produksi | SMTP/TLS resmi belum diuji | Reset password produksi belum dapat diandalkan. |

## 13. Status Git dan batas pekerjaan

- Branch: `main`; HEAD sebelum batch backup/restore: `244a532` (sudah dipush).
- Batch pengerasan produksi (header keamanan, `app:production-check`, verifikasi MariaDB 10.11.19,
  dokumentasi) dikomit setelah suite, Pint, build, dan pemeriksaan secret lulus.
- Batch backup/restore (script `scripts/db-backup-verify.sh`, verifikasi lokal 26 tabel, dokumentasi)
  dikomit setelah pemeriksaan sintaks, `git diff --check`, dan pemeriksaan secret lulus. Belum
  dijalankan sebagai backup/restore staging/produksi.
- Batch CI MariaDB 10.11 (`.github/workflows/ci.yml`) ditambahkan; YAML divalidasi dan suite
  MariaDB lokal lulus. Run GitHub belum diamati; CI bukan pengganti staging.
- Batch F8 sebelumnya dikomit dan di-push setelah suite, Pint, build, dan pemeriksaan secret lulus.
- Tidak ada `git reset`, `git clean`, atau penghapusan perubahan pengguna.
- `.env` tidak dikomit; `.env.example` hanya berisi placeholder. Tidak ada secret di repositori.
- Database **lokal** dimigrasikan (30 `Ran`) dan diimpor wilayah (6.612 baris) dengan persetujuan
  pemilik; database produksi/staging tidak tersedia dan tidak disentuh. Backup lokal dibuat sebelum
  migrasi di luar repositori.

## 14. Rujukan

- `docs/PRD.md` — kontrak kebutuhan dan kriteria penerimaan.
- `docs/IMPLEMENTATION_PLAN.md` — fase, backlog, Definition of Done.
- `docs/DATA_DICTIONARY.md` — skema tabel/kolom.
- `docs/USER_FLOWS.md` — alur pengguna.
- `docs/DECISIONS.md` — keputusan teknis dan batas provisional.
- `docs/SETUP.md` — setup lokal.
- `docs/DEPLOYMENT_CHECKLIST.md` — checklist operasional produksi.
- `docs/REGION_DATA_SOURCES.md` — sumber/checksum data wilayah.
- `docs/VERSIONS.md` — versi terpasang dan known issues.
