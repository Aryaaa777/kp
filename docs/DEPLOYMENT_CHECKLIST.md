# Checklist Deployment & Backup — Tim IT

> Dokumen ini adalah **checklist operasional** untuk menindaklanjuti keputusan **Q-014** (Tahap 105).
> Isinya **belum** dijalankan: tidak ada server, domain, kredensial, atau staging yang tersedia saat
> dokumen dibuat. Karena itu **tidak ada** klaim bahwa aplikasi sudah terverifikasi di MariaDB/produksi.
>
> Jangan menaruh domain, IP, atau kredensial nyata di repositori. Nilai `<...>` diisi oleh tim IT.

## 1. Target yang disetujui (Q-014)

| Aspek | Target |
| --- | --- |
| OS | Linux enterprise-grade — Ubuntu Server LTS atau AlmaLinux |
| Database | MariaDB **10.11 LTS** |
| Domain | Subdomain resmi pemerintah `.go.id` |
| Transport | HTTPS wajib; TLS dengan pembaruan otomatis atau sertifikat instansi |
| Backup database | Setiap 24 jam |
| Backup filesystem | Mingguan |
| Lokasi backup | Remote storage / server terpisah (bukan host aplikasi) |

## 2. Server & runtime

- [ ] OS terpasang dan di-*patch*; hanya paket yang diperlukan.
- [ ] PHP 8.3–8.5 + ekstensi: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`,
      `bcmath`, `fileinfo`, `curl`.
- [ ] Composer 2.x (install dependency dengan `--no-dev --optimize-autoloader`).
- [ ] Node 22.x hanya untuk **build aset** (`npm ci && npm run build`); Node tidak perlu di produksi.
- [ ] Web server mengarah ke `public/` (bukan root proyek).
- [ ] `.env` produksi: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<subdomain>.go.id`,
      `APP_TIMEZONE=Asia/Jakarta`, kredensial DB khusus, `SESSION_SECURE_COOKIE=true`.
- [ ] Sesi: `SESSION_DRIVER=database` (agar daftar/pengakhiran sesi FR-003 berfungsi),
      `SESSION_LIFETIME` sesuai kebijakan, dan `SESSION_IDLE_TIMEOUT=30` (atau nilai yang disetujui).
      Pastikan tabel `sessions` ada setelah migrasi.
- [ ] Email: konfigurasi `MAIL_MAILER=smtp`/mailer resmi, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
      `MAIL_PASSWORD`, `MAIL_ENCRYPTION`/scheme, dan `MAIL_FROM_ADDRESS` yang sah. **Jangan** memakai
      `MAIL_MAILER=log` di produksi. Uji pengiriman tautan reset ke kotak masuk nyata sebelum go-live.
- [ ] Izin: `.env` 640 (pemilik service + group), `storage/` & `bootstrap/cache/` dapat ditulis service.
- [ ] `php artisan storage:link` bila diperlukan; dokumen disimpan pada disk **privat** (`local`), bukan `public`.

## 3. Database MariaDB 10.11

- [ ] Database `utf8mb4` / `utf8mb4_unicode_ci` (atau sesuai konvensi instansi).
- [ ] User DB **khusus aplikasi** dengan hak minimum (SELECT/INSERT/UPDATE/DELETE + DDL saat migrasi);
      user ini **tidak** dipakai untuk backup bila memungkinkan.
- [ ] Port DB tidak terbuka ke internet; akses hanya dari host aplikasi.
- [ ] Verifikasi versi & `sql_mode` aktual:
      ```sql
      SELECT VERSION();
      SELECT @@sql_mode;
      ```
      Catat hasilnya. Proyek **belum** diverifikasi pada MariaDB; jangan menganggap SQLite sebagai bukti.
- [ ] Migrasi dijalankan **terserialisasi** (satu proses `migrate` pada satu waktu — tidak ada migration
      lock bawaan):
      ```sh
      php artisan migrate --force
      ```
- [ ] Migrasi yang harus tersedia pada target (semuanya sudah `Ran` di DB lokal MariaDB 10.4.32,
      **belum** diverifikasi pada MariaDB 10.11/produksi):
      `2026_10_19_000001_add_parent_submission_id_to_submissions_table`,
      `2026_10_24_000001_add_unpublished_tracking_to_cooperatives_table`,
      `2026_10_24_000002_add_status_operasional_to_cooperatives_table`,
      `2026_10_24_000003_create_settings_table`,
      `2026_10_26_000001_add_region_id_to_users_table`,
      `2026_10_26_000002_add_component_scores_to_health_assessments_table`,
      `2026_10_26_000003_add_compliance_fields_to_governance_records_table`,
      `2026_10_26_000004_add_versioning_to_documents_table`.
- [ ] Verifikasi pasca-migrasi: `php artisan migrate:status` (semua `Ran`), cek FK & indeks pada
      `submissions.parent_submission_id`, `cooperatives.unpublished_by`, enum
      `cooperatives.status_operasional`, tabel `settings` (`key` unik, `value` teks),
      `users.region_id`, kolom komponen kesehatan, kolom kepatuhan tata kelola, serta
      `documents.previous_version_id`/`superseded_at`.
- [ ] **F8 (PROVISIONAL):** pastikan `ADMIN_REGION_SCOPE_ENABLED=false` kecuali dinas sudah
      menetapkan pembagian wilayah (Q-005); periksa `LICENSE_EXPIRING_DAYS` dan
      `GOVERNANCE_MIN_MEETINGS`; uji ekspor CSV (hanya `approved`), halaman `/statistik`, unggah
      versi dokumen, dan perhitungan kesehatan/kepatuhan di staging.
- [ ] **Data wilayah (Q-012):** verifikasi checksum dataset
      `database/data/regions/jawa_barat_2025.csv` terhadap
      `database/data/regions/jawa_barat_2025.report.json`, lalu:
      ```sh
      php artisan regions:import --dry-run \
        --expected=province:1,regency:27,district:627,village:5957
      php artisan regions:import \
        --expected=province:1,regency:27,district:627,village:5957
      php artisan regions:audit-cooperatives --json=storage/app/region-audit.json
      ```
      Periksa jumlah `regions` = 6.612 (1 provinsi, 27 kabupaten/kota, 627 kecamatan,
      5.957 desa/kelurahan). **Jangan** backfill `region_id` tanpa pemetaan deterministik dan
      **jangan** mengubahnya menjadi `NOT NULL` sebelum audit tuntas. Rincian:
      `docs/REGION_DATA_SOURCES.md`.
- [ ] Jalankan suite pada **database uji terpisah**—bukan DB staging berisi data. `phpunit.xml`
      memaksa SQLite `:memory:`, sehingga `php artisan test --no-coverage` **tidak otomatis** menguji
      MariaDB. Untuk uji MariaDB, gunakan konfigurasi PHPUnit/database uji khusus dan jangan memakai
      `RefreshDatabase` pada data staging.
- [ ] Akun admin pertama: `php artisan user:create` (password diminta interaktif; jangan ditanam di kode).

## 4. TLS & domain

- [ ] DNS subdomain `.go.id` mengarah ke host aplikasi.
- [ ] Sertifikat dari instansi **atau** Let's Encrypt dengan pembaruan otomatis (timer/cron) + monitoring
      masa berlaku.
- [ ] Redirect HTTP → HTTPS; hanya 80/443 terbuka untuk publik.
- [ ] Uji: `curl -I https://<subdomain>.go.id` → 200/302 dan header TLS valid.

## 5. Backup

### 5.1 Database (harian)

- [ ] Dump konsisten:
      ```sh
      mariadb-dump --defaults-extra-file=<kredensial-backup> --single-transaction --routines --triggers <db> | gzip > <tujuan>/db-YYYYMMDD.sql.gz
      ```
- [ ] Jadwal harian (cron/systemd timer) pada jam sepi.
- [ ] Retensi **ditetapkan & disetujui tim IT** (contoh awal: harian 14 hari, mingguan 8 minggu — wajib
      dikonfirmasi, bukan keputusan proyek).
- [ ] Checksum disimpan (mis. SHA-256) untuk verifikasi integritas.

### 5.2 Filesystem (mingguan)

- [ ] Arsipkan `storage/app` (dokumen privat), `storage/logs` bila diperlukan, dan `.env` **terenkripsi**
      secara terpisah.
- [ ] Jangan menyertakan `vendor/`, `node_modules/`, cache build.

### 5.3 Penyimpanan & akses

- [ ] Backup disimpan di **remote storage/server terpisah** — bukan disk yang sama dengan aplikasi.
- [ ] Enkripsi saat transit (SSH/SFTP/rsync over SSH) dan saat diam (enkripsi storage atau `age`/`gpg`).
- [ ] Kredensial backup **berbeda** dari kredensial aplikasi; akses dibatasi ke operator tertentu.
- [ ] Backup tidak dapat diakses publik (tanpa URL langsung).

### 5.4 Uji pemulihan (wajib, berkala)

- [ ] Minimal **triwulanan** (dan sebelum go-live) lakukan restore ke database **staging**:
      1. siapkan DB kosong; 2. `gunzip -c db-*.sql.gz | mariadb <db_staging>`; 3. bandingkan jumlah baris
      tabel utama; 4. jalankan aplikasi staging + smoke test (`/login`, `/koperasi`, buka satu profil).
- [ ] Catat **RPO/RTO** aktual dan hasil uji. Backup yang belum pernah di-restore **belum** dapat dianggap
      layak.

## 6. Pengerasan & pemantauan

- [ ] SSH: hanya key, `PermitRootLogin no`, firewall 22/80/443.
- [ ] `APP_DEBUG=false`; halaman error tidak membocorkan stack trace.
- [ ] Rotasi log + monitoring disk, sertifikat, dan keberhasilan backup (alert bila gagal).
- [ ] Tidak ada akun default berpassword; akun admin dibuat lewat `user:create`.
- [ ] Audit akses berkala ke server & backup.

## 7. Go-live (setelah semua di atas terpenuhi)

- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] `php artisan migrate --force` (terserialisasi) + `php artisan migrate:status`
- [ ] Smoke test: login admin, direktori publik, profil publik, tarik publikasi → hilang dari direktori.
- [ ] Smoke test FR-003: biarkan sesi idle melewati `SESSION_IDLE_TIMEOUT` → dipaksa login ulang; buka
      `/akun/keamanan` → daftar sesi tampil (driver database) dan “akhiri sesi lain” bekerja.
- [ ] Smoke test FR-004: minta tautan reset dari `/lupa-password` → email diterima → reset berhasil →
      password lama gagal; token yang sama tidak dapat dipakai ulang. Uji juga tombol **Kirim reset**
      dari `/admin/akun`.
- [ ] Smoke test US-009: admin membuat akun pengelola + menautkan koperasi; password tidak pernah
      tampil; akun dapat diaktifkan/nonaktifkan; admin tidak dapat menonaktifkan diri sendiri.
- [ ] Smoke test FR-191: ubah field publik → direktori/profil berubah; field di luar Q-008 ditolak;
      NIK/telepon pribadi/data anggota/omzet terlarang tidak tampil.
- [ ] Smoke test tambahan: halaman **419** (CSRF kedaluwarsa) dan **500** tampil ramah tanpa stack trace;
      filter **periode** dashboard/daftar konsisten; tautan “Daftar” mempertahankan filter aktif.
- [ ] Catat versi MariaDB, `sql_mode`, hasil suite (SQLite default dan uji MariaDB terpisah), serta hasil
      uji restore pada laporan go-live.
