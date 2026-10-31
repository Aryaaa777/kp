# Konfigurasi & Operasi Produksi

> **Status:** panduan dan template siap dipakai, tetapi **belum diterapkan** ke
> staging/produksi. Tidak ada asumsi bahwa server, domain, TLS, SMTP, atau akses
> Dinas sudah tersedia. Blocker eksternal ada di `docs/GO_LIVE_BLOCKERS.md`.
>
> Dokumen ini melengkapi `docs/DEPLOYMENT_CHECKLIST.md`. Checklist tetap menjadi
> daftar periksa utama; dokumen ini menjelaskan cara memenuhi item-itemnya.

## 1. Ruang lingkup

Dokumen ini mencakup:

- konfigurasi environment produksi dan pengelolaan secret;
- struktur direktori rilis, permission, dan deployment/rollback;
- konfigurasi web server/PHP-FPM, worker antrean, dan log;
- pengerasan keamanan serta pemantauan dasar;
- bukti yang harus disimpan sebelum go-live.

Backup dan restore dibahas terpisah di `docs/BACKUP_RESTORE.md`. Template
operasional ada di `deploy/`.

## 2. Topologi yang diasumsikan

Contoh minimum yang aman:

```text
Pengguna → HTTPS (reverse proxy/Nginx) → PHP-FPM → aplikasi Laravel
                                             └→ MariaDB 10.11 (host/VM terpisah atau localhost)
                                             └→ storage/app/private (dokumen)
                                             └→ backup remote (host terpisah)
```

Jangan mengakses database dari internet. Bila web server dan database berada
pada host berbeda, batasi firewall hanya dari host aplikasi. Bila memakai load
balancer, isi `TRUSTED_PROXIES` dengan IP/CIDR load balancer, bukan `*`.

## 3. Runtime dan paket

| Komponen | Target |
| --- | --- |
| OS | Ubuntu Server LTS atau AlmaLinux (sesuai Q-014) |
| PHP | 8.3–8.5, dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `fileinfo`, `curl` |
| Database | MariaDB 10.11 LTS |
| Web server | Nginx + PHP-FPM (contoh tersedia) |
| Node | 22.x hanya untuk build aset, tidak perlu runtime produksi |
| Composer | 2.x, install dengan `--no-dev --optimize-autoloader` |

Verifikasi:

```sh
php -v
php -m | grep -E 'pdo_mysql|mbstring|openssl|tokenizer|xml|ctype|json|bcmath|fileinfo|curl'
composer check-platform-reqs
```

## 4. Struktur direktori dan permission

Contoh layout:

```text
/var/www/koperasi/
├── current -> releases/20261031-120000
├── releases/
│   └── 20261031-120000/
├── shared/
│   ├── .env
│   └── storage/
└── ...

/var/backups/koperasi/          # backup database (host terpisah/remote)
/etc/koperasi/backup.cnf        # kredensial backup, mode 600
/etc/koperasi/backup.env        # variabel non-secret systemd backup
```

Prinsip:

- Web root **hanya** `public/`, bukan root proyek.
- `storage/app/private` tidak boleh diekspos web.
- `storage/` dan `bootstrap/cache/` harus dapat ditulis user PHP-FPM.
- `.env` mode `640`, pemilik user service, group web server.
- Jangan menjalankan aplikasi sebagai `root`.

Contoh (sesuaikan user/group):

```sh
sudo chown -R koperasi:www-data /var/www/koperasi/current
sudo find /var/www/koperasi/current -type d -exec chmod 750 {} \;
sudo find /var/www/koperasi/current -type f -exec chmod 640 {} \;
sudo chmod 640 /var/www/koperasi/shared/.env
sudo chown koperasi:www-data /var/www/koperasi/shared/.env
sudo chmod -R ug+rwX /var/www/koperasi/shared/storage
sudo chmod -R ug+rwX /var/www/koperasi/current/bootstrap/cache
```

## 5. Environment produksi

Gunakan `.env.production.example` sebagai daftar awal, lalu ganti semua
placeholder. File itu sengaja membuat `app:production-check` gagal sampai
dikonfigurasi.

| Variabel | Nilai produksi | Catatan |
| --- | --- | --- |
| `APP_ENV` | `production` | Wajib. |
| `APP_DEBUG` | `false` | Jangan pernah `true` di produksi. |
| `APP_KEY` | hasil `php artisan key:generate` | Jangan regenerate setelah data terenkripsi ada. |
| `APP_URL` | `https://<subdomain>.go.id` | Wajib HTTPS dan domain resmi. |
| `APP_TIMEZONE` | `Asia/Jakarta` | Diperiksa `app:production-check`. |
| `APP_LOCALE` | `id` | Antarmuka Indonesia. |
| `DB_CONNECTION` | `mysql` | Driver MariaDB memakai `mysql`. |
| `DB_HOST/PORT/DATABASE/USERNAME/PASSWORD` | kredensial khusus aplikasi | Jangan memakai user root. |
| `SESSION_DRIVER` | `database` | Diperlukan FR-003 (daftar/akhiri sesi). |
| `SESSION_SECURE_COOKIE` | `true` | Hanya dikirim melalui HTTPS. |
| `SESSION_HTTP_ONLY` | `true` | Mencegah akses cookie dari JavaScript. |
| `SESSION_SAME_SITE` | `lax` atau `strict` | Sesuaikan kebutuhan. |
| `SESSION_ENCRYPT` | `true` | Disarankan untuk data sesi sensitif. |
| `SESSION_IDLE_TIMEOUT` | `30` (atau kebijakan) | Minimal 1 menit. |
| `TRUSTED_PROXIES` | IP/CIDR proxy | Jangan `*` bila host dapat diakses langsung. |
| `CACHE_STORE` | `database` | Jangan `array`/`null` di produksi. |
| `QUEUE_CONNECTION` | `database` | Jalankan worker; lihat template systemd. |
| `MAIL_MAILER` | `smtp` | Jangan `log`/`array` di produksi. |
| `MAIL_SCHEME` | `null` atau `smtps` | `null` untuk STARTTLS (port 587); `smtps` untuk TLS langsung (port 465). |
| `MAIL_HOST/PORT/USERNAME/PASSWORD` | dari penyedia SMTP resmi | Jangan gunakan kredensial pribadi. Mode file `.env` wajib `640`. |
| `MAIL_FROM_ADDRESS` | alamat resmi dinas | Bukan `@example.com`. Wajib match domain SPF/DKIM dinas. |
| `MAIL_FROM_NAME` | nama aplikasi resmi | Default `${APP_NAME}`. |
| `SECURITY_HSTS_ENABLED` | `true` | Hanya dikirim pada HTTPS. |
| `SECURITY_CSP_ENABLED` | `true` | Verifikasi tidak ada pelanggaran CSP. |
| `SECURITY_CSP_REPORT_ONLY` | `false` | Gunakan `true` hanya saat uji CSP. |
| `SECURITY_X_FRAME_OPTIONS` | `DENY` | Jangan diubah tanpa alasan. |
| `ADMIN_REGION_SCOPE_ENABLED` | `false` | Jangan aktifkan sebelum Q-005 final. |
| `LOG_CHANNEL` / `LOG_STACK` | `stack` / `daily` | Rotasi otomatis; `LOG_DAILY_DAYS=14`. |
| `LOG_LEVEL` | `warning` atau `error` | Jangan `debug` di produksi. |

Jangan menaruh secret di repositori, unit systemd, atau argumen perintah.
Untuk backup, gunakan `/etc/koperasi/backup.cnf` dengan mode `600`:

```ini
[client]
host=127.0.0.1
port=3306
user=koperasi_backup
password=CHANGE_ME
```

### 5.1 Panduan Integrasi SMTP & Email Dinas

Aplikasi menggunakan sistem pengiriman email native Laravel untuk fitur keamanan dan operasional (khususnya reset password terproteksi hash `FR-004`). Pengiriman email menggunakan konfigurasi SMTP dinas yang terpusat pada file lingkungan (`.env`).

#### A. Parameter Konfigurasi SMTP
Pada server produksi (`/var/www/koperasi/shared/.env`), atur nilai-nilai berikut sesuai kredensial resmi dari administrator email dinas:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=smtp.mail.jabarprov.go.id
MAIL_PORT=587
MAIL_USERNAME=noreply-koperasi@jabarprov.go.id
MAIL_PASSWORD="RAHASIA_DARI_ADMIN_DINAS"
MAIL_FROM_ADDRESS="noreply-koperasi@jabarprov.go.id"
MAIL_FROM_NAME="Sistem Informasi Koperasi Jabar"
```

*Catatan Enkripsi / Skema:*
- Jika menggunakan port `587` (STARTTLS): `MAIL_SCHEME=null`.
- Jika penyedia mewajibkan TLS langsung pada port `465` (SMTPS): `MAIL_SCHEME=smtps`.

#### B. Daftar Informasi yang Harus Diminta ke Administrator Email Dinas
Sebelum rilis produksi atau pengujian SMTP staging, mintakan lembar data integrasi berikut ke tim pengelola email Pemprov Jabar / Diskominfo:

1. **SMTP Server Host**: Nama FQDN mail server (contoh: `smtp.jabarprov.go.id`).
2. **SMTP Port & Tipe Enkripsi**: Port yang dibuka (biasanya 587 STARTTLS atau 465 SSL/TLS).
3. **Akun Autentikasi**: Username dan Password khusus aplikasi (hindari menggunakan akun personal ASN/pejabat).
4. **Alamat Pengirim Resmi (Envelope From)**: Alamat email pengirim resmi (misal `noreply.koperasi@jabarprov.go.id`).
5. **Koneksi Jaringan & Firewall Whitelist**: Izin akses port SMTP outbound (egress port 587/465) dari IP server aplikasi/staging.
6. **Batas Kuota Pengiriman (Rate Limit)**: Batasan jumlah pengiriman email per menit / per jam / per hari.
7. **Reputasi Domain & Catatan DNS (SPF, DKIM, DMARC)**:
   - Pastikan IP server aplikasi terdaftar pada SPF record domain pengirim (`v=spf1 ...`).
   - Pastikan DKIM signature valid untuk domain pengirim.
   - Kebijakan DMARC tidak me-reject email resmi aplikasi.

#### C. Penanganan Kegagalan Pengiriman Email
- Alur reset password (`PasswordResetLinkController` dan `AdminUserController`) dibungkus blok `try-catch` terpusat:
  - Kegagalan transport SMTP akan dicatat ke log aplikasi via `report($exception)`.
  - Sistem tidak membocorkan pesan error atau jejak stack trace SMTP ke pengguna akhir (pesan tetap generik guna mencegah enumerasi akun).
- Antrean latar belakang (`queue:work`) menangani kegagalan melalui tabel `failed_jobs` tanpa menghilangkan notifikasi database in-app.


## 6. Deployment rilis

### 6.1 Preflight (sebelum mengubah apa pun)

```sh
php -v
composer check-platform-reqs
php artisan about
php artisan migrate:status
php artisan app:production-check --json > /tmp/production-check-before.json
```

Catat commit yang akan dideploy, hash `composer.lock`, dan versi database.

### 6.2 Backup sebelum deploy

Wajib. Lihat `docs/BACKUP_RESTORE.md`. Jangan menjalankan migrasi tanpa backup
yang checksum-nya sudah diverifikasi.

### 6.3 Build dan pasang rilis

Di server build atau server aplikasi:

```sh
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
```

Node tidak perlu tersedia di runtime produksi. Pastikan `public/build/manifest.json`
ada sebelum mengaktifkan rilis.

### 6.4 Migrasi

Migrasi harus terserialisasi (satu proses pada satu waktu):

```sh
php artisan migrate --force
php artisan migrate:status
```

Jangan memakai `migrate:fresh`, `db:wipe`, rollback massal, atau `DROP` pada
database berisi data. Bila migrasi gagal, hentikan deploy dan ikuti prosedur
rollback di `docs/BACKUP_RESTORE.md`.

### 6.5 Cache dan permission

```sh
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link   # hanya bila public storage memang dipakai
```

Pastikan `storage/` dan `bootstrap/cache/` dapat ditulis user PHP-FPM. Jangan
menjalankan `storage:link` bila tidak ada berkas publik yang perlu diekspos.

### 6.6 Aktifkan rilis

1. Arahkan symlink `current` ke release baru.
2. Reload PHP-FPM (`systemctl reload php8.4-fpm` atau versi yang dipakai).
3. Restart worker antrean (`systemctl restart koperasi-queue`).
4. Jalankan `php artisan app:production-check --json` dan simpan hasilnya.
5. Lakukan smoke test di `docs/DEPLOYMENT_CHECKLIST.md` §7.

## 7. Web server dan PHP-FPM

Contoh Nginx ada di `deploy/nginx/koperasi.conf.example`. Sesuaikan:

- `server_name` dan path sertifikat TLS;
- versi socket PHP-FPM (`php8.3-fpm.sock`, `php8.4-fpm.sock`, atau `php8.5-fpm.sock`);
- `client_max_body_size` bila batas unggah dokumen berubah;
- `real_ip_header`/`set_real_ip_from` bila di belakang load balancer.

Validasi:

```sh
sudo nginx -t
sudo systemctl reload nginx
```

Jangan menambahkan header keamanan yang sama di Nginx dan aplikasi secara
bersamaan. Aplikasi sudah memasang HSTS/CSP/`X-Frame-Options`/`nosniff` melalui
middleware.

## 8. Worker, scheduler, dan log

- **Queue:** template `deploy/systemd/koperasi-queue.service.example`. Jalankan
  bila `QUEUE_CONNECTION=database` dan ada job/notifikasi antrean. Pantau
  `failed_jobs`.
- **Scheduler:** saat ini tidak ada task terjadwal di `routes/console.php`.
  Bila nanti ditambahkan, gunakan systemd timer atau cron
  `php artisan schedule:run` setiap menit.
- **Log aplikasi:** `LOG_STACK=daily`, `LOG_DAILY_DAYS=14`. Contoh logrotate
  tambahan ada di `deploy/logrotate/koperasi.conf.example`.
- **Log Nginx:** pastikan rotasi dan retensi disetujui tim IT.

## 9. Pengerasan keamanan

- HTTPS wajib; redirect HTTP → HTTPS; HSTS hanya pada HTTPS.
- `APP_DEBUG=false`; halaman error tidak boleh menampilkan stack trace.
- CSP aktif tanpa `script-src 'unsafe-inline'`; tidak ada inline event handler
  baru di Blade.
- Cookie sesi `Secure`, `HttpOnly`, `SameSite=Lax/Strict`; sesi dienkripsi.
- `TRUSTED_PROXIES` dibatasi; jangan mempercayai header `X-Forwarded-*` dari
  internet terbuka.
- SSH hanya key, `PermitRootLogin no`, firewall hanya 22/80/443.
- User database aplikasi hak minimum; user backup terpisah.
- Dokumen privat di `storage/app/private`; jangan menaruh di `public/`.
- Tidak ada akun default berpassword; akun admin dibuat lewat
  `php artisan user:create`.
- Rotasi kredensial DB/SMTP dan akses backup dicatat sebagai perubahan terkontrol.
- Jangan mengaktifkan `ADMIN_REGION_SCOPE_ENABLED` sebelum Q-005 final dan diuji.

## 10. Pemantauan minimum

| Area | Pemeriksaan | Ambang/aksi |
| --- | --- | --- |
| Aplikasi | `GET /up` | Harus 200; alert bila gagal 2x berturut-turut. |
| HTTP | Log 5xx dan latency | Alert bila 5xx meningkat atau latency tidak wajar. |
| Database | Koneksi, disk, slow query | Alert disk > 80%; backup harian harus sukses. |
| Backup | Status systemd timer + checksum | Alert bila gagal; uji restore berkala. |
| Sertifikat | Masa berlaku TLS | Alert minimal 30 hari sebelum kedaluwarsa. |
| Disk | `df -h` host aplikasi | Alert > 80%; logs/storage tidak boleh memenuhi disk. |
| Antrean | `failed_jobs`, ukuran antrean | Alert bila ada job gagal/tertahan. |
| Keamanan | Login gagal berulang, akses admin | Tinjau log dan `activity_logs`. |

Simpan hasil `app:production-check --json`, status backup, dan bukti smoke test
sebagai lampiran laporan go-live.

## 11. Rollback

### 11.1 Rollback kode

1. Arahkan symlink `current` ke release sebelumnya.
2. Reload PHP-FPM dan restart worker.
3. Jalankan smoke test singkat dan `php artisan about`.
4. Jangan menghapus release lama sebelum masa retensi yang disetujui.

### 11.2 Rollback konfigurasi

1. Kembalikan `.env` dari salinan terenkripsi/terkontrol.
2. Jalankan `php artisan optimize:clear && php artisan config:cache`.
3. Reload PHP-FPM.
4. Verifikasi header keamanan, sesi, dan email.

### 11.3 Rollback database

**Tidak otomatis.** Migrasi yang sudah berjalan tidak boleh di-rollback massal
atau di-`fresh`. Bila data/skema rusak:

1. hentikan penulisan (maintenance mode);
2. amankan database saat ini bila memungkinkan;
3. restore dari backup yang sudah diverifikasi ke database baru/staging;
4. verifikasi jumlah tabel/baris dan smoke test;
5. baru pindahkan aplikasi ke database hasil restore dengan persetujuan eksplisit.

Detail ada di `docs/BACKUP_RESTORE.md`.

## 12. Bukti yang harus disimpan

- commit dan hash `composer.lock` yang dideploy;
- output `composer audit`;
- output `php artisan migrate:status`;
- checksum backup sebelum deploy;
- output `php artisan app:production-check --json` (0 FAIL, WARN ditinjau);
- hasil smoke test dan header HTTPS;
- status backup/restore staging;
- persetujuan go-live dari pemilik proyek/Dinas.
