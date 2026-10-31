# Backup, Restore, dan Pemulihan

> **Status:** prosedur dan script siap diuji, tetapi **belum dijalankan pada
> staging/produksi**. Verifikasi lokal 26 tabel bukan pengganti restore staging.
> RPO/RTO dan retensi di bawah adalah **usulan**, bukan kebijakan resmi sampai
> disetujui tim IT/Dinas.
>
> Jangan menjalankan operasi destruktif pada database berisi data. Script
> `scripts/db-backup-verify.sh` hanya membuat database scratch baru dan
> menolak memakai database scratch yang sudah ada.

## 1. Aset yang harus dilindungi

| Aset | Lokasi contoh | Frekuensi | Catatan |
| --- | --- | --- | --- |
| Database | MariaDB 10.11 | Harian | Dump logis + checksum + uji restore scratch. |
| Dokumen privat | `storage/app/private` | Mingguan | Dokumen unggahan; jangan ekspos ke web. |
| Berkas publik | `storage/app/public` (bila ada) | Mingguan | Hanya bila benar-benar dipakai. |
| `.env` | `/var/www/koperasi/shared/.env` | Saat berubah | Enkripsi terpisah; jangan masuk tar biasa. |
| Kode rilis | release Git/artifact | Setiap rilis | Simpan commit + `composer.lock`. |
| Konfigurasi server | Nginx/PHP-FPM/systemd | Saat berubah | Simpan di repositori konfigurasi terpisah. |
| Kredensial backup | `/etc/koperasi/backup.cnf` | Saat rotasi | Mode `600`; akses terbatas. |

## 2. Peran dan pemisahan akses

| Peran | Tanggung jawab |
| --- | --- |
| Dinas IT / operator server | Menyediakan host, disk, remote storage, jadwal, dan akses. |
| DBA | Membuat user backup, memverifikasi dump, dan menyetujui restore produksi. |
| Pemilik aplikasi | Menyetujui RPO/RTO, retensi, dan restore yang mengubah produksi. |
| Aplikasi/developer | Menyediakan script, dokumentasi, dan verifikasi aplikasi setelah restore. |

User backup **berbeda** dari user aplikasi. User backup hanya membutuhkan hak
baca database + `SHOW VIEW`/`TRIGGER`/`ROUTINE` bila routine/trigger dipakai.
Jangan memakai kredensial root.

## 3. Target RPO/RTO (usulan)

| Skenario | RPO usulan | RTO usulan | Keputusan |
| --- | --- | --- | --- |
| Database rusak/hilang | 24 jam | 4 jam | **BUTUH PERSETUJUAN** |
| Filesystem/dokumen hilang | 7 hari | 8 jam | **BUTUH PERSETUJUAN** |
| Host aplikasi hilang total | 7 hari | 1 hari kerja | **BUTUH PERSETUJUAN** |

Nilai ini belum diukur di staging. Setelah restore staging, catat durasi nyata
dan ganti angka usulan dengan hasil pengukuran.

## 4. Retensi (usulan)

| Jenis | Retensi usulan | Status |
| --- | --- | --- |
| Backup harian database | 14 hari | **BUTUH PERSETUJUAN** |
| Backup mingguan database | 8 minggu | **BUTUH PERSETUJUAN** |
| Backup bulanan | 6 bulan | **BUTUH PERSETUJUAN** |
| Backup filesystem mingguan | 8 minggu | **BUTUH PERSETUJUAN** |
| Salinan `.env` terenkripsi | 2 versi terakhir | **BUTUH PERSETUJUAN** |

Retensi harus memenuhi kebijakan arsip Dinas. Jangan menghapus backup sebelum
retensi disetujui dan restore terakhir dinyatakan berhasil.

## 5. Backup database harian

### 5.1 Prasyarat

- `mariadb-dump`/`mysqldump`, client `mariadb`/`mysql`, `gzip`, `sha256sum`.
- File kredensial `/etc/koperasi/backup.cnf`, mode `600`, dapat dibaca user
  backup.
- Direktori tujuan `BACKUP_DIR` (contoh `/var/backups/koperasi`), mode `700`.
- Ruang disk cukup untuk dump + database scratch sementara.
- Database sumber **tidak** boleh sama dengan `VERIFY_DB`.

### 5.2 Perintah utama

```sh
sudo -u koperasi-backup env \
  DB_CNF=/etc/koperasi/backup.cnf \
  DB_DATABASE=koperasi_jabar \
  BACKUP_DIR=/var/backups/koperasi \
  bash /var/www/koperasi/current/scripts/db-backup-verify.sh
```

Yang dilakukan script:

1. membuat dump `db-<database>-<timestamp>.sql.gz` dengan
   `--single-transaction --quick --routines --triggers --no-tablespaces`;
2. menguji integritas gzip dan menulis checksum SHA-256;
3. membuat database scratch unik;
4. restore dump ke scratch;
5. membandingkan daftar tabel dan jumlah baris;
6. menghapus scratch, kecuali `KEEP_VERIFY_DB=1`;
7. **tidak** mengubah database sumber.

Backup cepat tanpa uji restore (hanya untuk keperluan mendesak):

```sh
VERIFY=0 ... bash scripts/db-backup-verify.sh
```

Tetap jalankan verifikasi restore terjadwal. Backup yang belum pernah
diverifikasi belum layak dianggap.

### 5.3 Memeriksa hasil

```sh
cd /var/backups/koperasi
ls -lh db-*.sql.gz*
gzip -t db-<timestamp>.sql.gz
sha256sum -c db-<timestamp>.sql.gz.sha256
```

Simpan checksum di lokasi remote yang terpisah dari file dump bila memungkinkan.

## 6. Backup filesystem dan `.env`

### 6.1 Dokumen

```sh
sudo tar -C /var/www/koperasi/shared \
  -czf /var/backups/koperasi/files-$(date +%Y%m%d-%H%M%S).tar.gz \
  storage/app/private storage/app/public
```

Bila salah satu direktori tidak ada, sesuaikan perintah atau gunakan
`--ignore-failed-read` setelah dipastikan aman. Jangan memasukkan `vendor/`,
`node_modules/`, cache build, atau log yang tidak diperlukan.

### 6.2 `.env`

`.env` berisi `APP_KEY`, kredensial DB, dan SMTP. Backup terpisah dan
terenkripsi:

```sh
sudo install -m 600 /var/www/koperasi/shared/.env /var/backups/koperasi/env-$(date +%Y%m%d).env
sudo age -r <public-key-operator> -o /var/backups/koperasi/env-$(date +%Y%m%d).env.age \
  /var/backups/koperasi/env-$(date +%Y%m%d).env
sudo shred -u /var/backups/koperasi/env-$(date +%Y%m%d).env
```

Gunakan `gpg` bila organisasi memakai GPG. Kunci privat dekripsi **tidak** boleh
disimpan di host aplikasi. Uji dekripsi minimal sekali sebelum go-live.

### 6.3 Transfer remote

```sh
rsync -av --partial /var/backups/koperasi/ backup@<host-backup>:/srv/backup/koperasi/
```

- Gunakan SSH key, bukan password.
- Enkripsi saat transit (SSH) dan saat diam (storage terenkripsi/age/gpg).
- Jangan memakai `--delete` tanpa kebijakan retensi yang disetujui.
- Backup remote tidak boleh dapat diakses publik.

## 7. Restore ke database scratch

Gunakan ini untuk latihan/verifikasi tanpa menyentuh database aplikasi:

```sh
DB_CNF=/etc/koperasi/backup.cnf \
DB_DATABASE=koperasi_jabar \
BACKUP_DIR=/var/backups/koperasi \
VERIFY_DB=koperasi_restore_check_20261031_01 \
bash /var/www/koperasi/current/scripts/db-backup-verify.sh
```

Script menolak `VERIFY_DB` yang sudah ada. Jangan membuat nama yang sama dengan
database aplikasi, `mysql`, `information_schema`, `performance_schema`, atau
`sys`.

Untuk mempertahankan scratch setelah verifikasi:

```sh
KEEP_VERIFY_DB=1 ... bash scripts/db-backup-verify.sh
```

Hapus scratch secara manual hanya setelah pemeriksaan selesai dan dipastikan
nama database benar.

## 8. Restore ke staging

1. Pastikan staging **bukan** produksi dan tidak melayani pengguna nyata.
2. Catat versi aplikasi dan skema staging saat ini.
3. Aktifkan maintenance mode bila staging dipakai bersama:
   `php artisan down --retry=60`.
4. Buat database staging kosong atau gunakan database scratch baru.
5. Restore dump:
   ```sh
   gunzip -c /var/backups/koperasi/db-<timestamp>.sql.gz \
     | mariadb --defaults-extra-file=/etc/koperasi/restore.cnf <database-staging>
   ```
6. Pulihkan `storage/app/private` dan `storage/app/public` dari tar.
7. Pulihkan `.env` staging dari salinan terenkripsi; jangan memakai `.env`
   produksi.
8. Jalankan:
   ```sh
   php artisan optimize:clear
   php artisan config:cache
   php artisan migrate:status
   php artisan app:production-check --json
   ```
9. Smoke test: login, direktori, profil, dokumen, laporan, reset password
   (bila SMTP staging tersedia).
10. Catat waktu restore, jumlah tabel/baris, dan masalah yang ditemukan.

## 9. Restore darurat ke produksi

**Hanya dengan persetujuan eksplisit pemilik proyek/DBA.** Jangan menjalankan
langkah ini secara otomatis.

1. Umumkan insiden dan hentikan penulisan:
   `php artisan down --retry=60`; hentikan worker.
2. Bila memungkinkan, buat backup darurat database saat ini (jangan menimpa
   backup terakhir yang baik).
3. Pilih backup dengan checksum valid dan sudah diuji.
4. Restore ke database baru terlebih dahulu bila ada ruang; verifikasi.
5. Arahkan aplikasi ke database hasil restore melalui `.env` dan
   `php artisan config:cache`.
6. Jalankan `php artisan app:production-check --json`; minimal 0 FAIL.
7. Smoke test alur kritis.
8. `php artisan up`; aktifkan worker; pantau log.
9. Simpan kronologi, backup yang dipakai, dan bukti verifikasi.

Jangan memakai `migrate:fresh`, `db:wipe`, atau rollback massal sebagai cara
memulihkan produksi.

## 10. Pemulihan host total

Urutan yang disarankan:

1. Siapkan host baru dengan OS, PHP, Nginx, MariaDB client, dan user service.
2. Pasang kode dari commit yang tercatat; jalankan `composer install --no-dev`.
3. Pulihkan `.env` terenkripsi dan `storage/`.
4. Buat database kosong; restore dump terbaru yang sudah diverifikasi.
5. `php artisan storage:link` bila diperlukan; atur permission.
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
7. `php artisan app:production-check --json`; smoke test.
8. Aktifkan Nginx/TLS, worker, monitoring, dan backup.
9. Catat RTO aktual.

## 11. Skenario kegagalan

| Skenario | Tindakan awal | Jangan lakukan |
| --- | --- | --- |
| Database korup | Hentikan aplikasi, restore backup terverifikasi ke DB baru. | `migrate:fresh`, `db:wipe`, restore tanpa cek checksum. |
| Dokumen terhapus | Restore `storage/app/private` dari tar terbaru; verifikasi checksum. | Menimpa seluruh `storage/` tanpa memeriksa versi. |
| Host hilang | Pulihkan host baru sesuai §10. | Menganggap backup lokal host ikut selamat. |
| Deploy buruk | Switch symlink ke release sebelumnya; rollback config. | Rollback migrasi destruktif tanpa persetujuan. |
| Sertifikat kedaluwarsa | Perbarui/reload TLS; verifikasi HSTS dan redirect. | Mematikan HTTPS sebagai solusi permanen. |
| SMTP gagal | Periksa kredensial/relay; gunakan jalur reset manual sesuai kebijakan. | Menyimpan password SMTP di tiket/log. |
| Backup gagal | Perbaiki penyebab, jalankan ulang, verifikasi checksum. | Menghapus backup lama sebelum backup baru valid. |

## 12. Bukti restore

Simpan tabel berikut untuk setiap uji restore:

| Item | Isi |
| --- | --- |
| Tanggal/waktu | |
| Operator | |
| Backup yang dipakai | nama file + SHA-256 |
| Target restore | staging/scratch/produksi |
| Versi aplikasi | commit |
| Jumlah tabel | |
| Jumlah baris tabel utama | |
| `migrate:status` | |
| `app:production-check` | PASS/WARN/FAIL |
| Smoke test | hasil |
| Durasi restore | |
| Masalah/tindak lanjut | |

## 13. Batas

- Script saat ini hanya membackup **database**. Filesystem dan `.env` harus
  ditangani prosedur terpisah di atas.
- Verifikasi lokal 26 tabel bukan bukti staging/produksi.
- RPO/RTO/retensi belum disetujui.
- Backup remote, enkripsi at-rest, dan restore staging belum diuji.
- Status blocker ada di `docs/GO_LIVE_BLOCKERS.md`.
