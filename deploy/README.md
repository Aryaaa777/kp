# Template Deployment — Contoh, Bukan Konfigurasi Aktif

Direktori ini berisi **contoh** konfigurasi operasional untuk tim IT. Tidak ada
file di sini yang dibaca otomatis oleh aplikasi. Jangan menaruh domain, IP,
password, token, sertifikat, atau kunci privat nyata di repositori.

## Isi

| Berkas | Kegunaan |
| --- | --- |
| `.env.production.example` (di root repo) | Daftar variabel environment produksi dan placeholder. Salin ke `.env` di server. |
| `deploy/nginx/koperasi.conf.example` | Contoh virtual host Nginx + PHP-FPM + TLS. |
| `deploy/systemd/koperasi-queue.service.example` | Contoh worker antrean Laravel. |
| `deploy/systemd/koperasi-backup.service.example` | Contoh service backup database. |
| `deploy/systemd/koperasi-backup.timer.example` | Contoh jadwal harian backup. |
| `deploy/logrotate/koperasi.conf.example` | Contoh rotasi log aplikasi/Nginx. |

## Cara memakai

1. Baca `docs/PRODUCTION_CONFIG.md`, `docs/BACKUP_RESTORE.md`, dan
   `docs/DEPLOYMENT_CHECKLIST.md`.
2. Salin template ke lokasi sistem yang sesuai, lalu ganti semua placeholder
   `CHANGE_ME`, `koperasi.example.go.id`, dan path bila struktur direktori
   berbeda.
3. Simpan secret di file terpisah dengan izin ketat:
   - `/etc/koperasi/backup.cnf` — kredensial `[client]` untuk backup, mode `600`.
   - `/etc/koperasi/backup.env` — variabel non-secret untuk systemd backup.
   - `.env` aplikasi — mode `640`, pemilik service + group.
4. Validasi sebelum mengaktifkan:
   ```sh
   nginx -t
   systemd-analyze verify /etc/systemd/system/koperasi-queue.service
   systemd-analyze verify /etc/systemd/system/koperasi-backup.service
   bash -n /var/www/koperasi/current/scripts/db-backup-verify.sh
   ```
5. Jangan menjalankan `systemctl start` pada service backup sebelum kredensial
   dan direktori tujuan diverifikasi. Script backup hanya menulis ke
   `BACKUP_DIR` dan database scratch; database sumber tidak diubah.

## Batas

Template ini tidak membuktikan staging, domain, TLS, SMTP, atau backup remote
sudah tersedia. Status blocker ada di `docs/GO_LIVE_BLOCKERS.md`.
