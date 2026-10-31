# Panduan Pengujian Email Lokal dengan Gmail SMTP (Development Only)

> **PENTING / PERHATIAN KEAMANAN:**
> - Konfigurasi Gmail SMTP ini **HANYA** ditujukan untuk pengujian lokal/development.
> - **DILARANG KERAS** menggunakan Gmail pribadi untuk lingkungan server staging atau produksi dinas.
> - Produksi resmi wajib menggunakan server SMTP resmi Pemerintah Provinsi Jawa Barat (lihat `docs/PRODUCTION_CONFIG.md` §5.1).
> - Jangan pernah meng-commit file `.env` atau mencatat App Password Gmail ke dalam Git/dokumentasi.

---

## 1. Persyaratan Akun Google
1. Akun Google telah mengaktifkan **Verifikasi 2 Langkah (2-Step Verification / 2FA)**.
2. Buat **Sandi Aplikasi (App Password)** khusus:
   - Buka pengaturan akun Google: [https://myaccount.google.com/security](https://myaccount.google.com/security)
   - Pada bagian *Cara Anda login ke Google*, pilih **Verifikasi 2 Langkah**.
   - Gulir ke bawah hingga bagian **Sandi Aplikasi** (App Passwords).
   - Masukkan nama aplikasi (misal: `Koperasi Jabar Dev`).
   - Google akan menghasilkan 16 karakter acak (contoh: `abcd efgh ijkl mnop`).
   - Simpan 16 karakter tersebut tanpa spasi (`abcdefghijklmnop`).

---

## 2. Pengaturan pada `.env` Lokal
Salin nilai berikut ke file `.env` lokal Anda (bukan `.env.example` atau `.env.production`):

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=alamat_email_anda@gmail.com
MAIL_PASSWORD=enam_belas_karakter_app_password
MAIL_FROM_ADDRESS=alamat_email_anda@gmail.com
MAIL_FROM_NAME="Sistem Informasi Koperasi Jabar (Dev)"
```

*Catatan:*
- Jika ingin menggunakan port 465 (SSL):
  ```env
  MAIL_PORT=465
  MAIL_ENCRYPTION=ssl
  ```

Setelah mengubah `.env`, bersihkan cache konfigurasi lokal:
```sh
php artisan config:clear
```

---

## 3. Menjalankan Perintah Pengujian Manual (Eksplisit)

Aplikasi menyediakan perintah Artisan khusus:
```sh
php artisan mail:test {tujuan_email}
```

### Aturan Keamanan Perintah:
1. **Hanya diizinkan di `APP_ENV=local` atau `testing`**. Perintah akan menolak dijalankan jika `APP_ENV=production`.
2. Mengharuskan konfirmasi interaktif sebelum mengirim.
3. Menampilkan status konfigurasi saat ini (driver, host, port, enkripsi, from address).
4. Tidak pernah menampilkan password/kredensial ke terminal.
5. Menangkap kegagalan SMTP dan menyajikan pesan error secara aman.

### Contoh Eksekusi:
```sh
php artisan mail:test dev.tester@example.com
```

Output yang diharapkan:
```text
Pengujian Pengiriman Email (Hanya untuk Development/Testing)
===========================================================
Mailer:         smtp
Host:           smtp.gmail.com
Port:           587
Encryption:     tls
From Address:   alamat_email_anda@gmail.com
From Name:      Sistem Informasi Koperasi Jabar (Dev)
Penerima:       dev.tester@example.com

Kirim email pengujian sekarang? (yes/no) [no]:
> yes

Mengirim email uji coba...
[BERHASIL] Email uji coba berhasil dikirim ke dev.tester@example.com.
Periksa kotak masuk (inbox) atau folder spam.
```

---

## 4. Troubleshooting Masalah Umum Gmail SMTP

1. **Error: `Connection could not be established with host "smtp.gmail.com:587"`**
   - Pastikan koneksi internet aktif dan port 587 tidak diblokir oleh firewall lokal atau ISP.
2. **Error: `535-5.7.8 Username and Password not accepted`**
   - Anda menggunakan password login akun Google biasa, bukan **App Password 16 karakter**.
   - Akun Google belum mengaktifkan Verifikasi 2 Langkah (2FA).
3. **Error: `Connection timed out`**
   - Coba ganti ke port 465 dengan `MAIL_ENCRYPTION=ssl` pada `.env`.

---

## 5. Kembali ke Mode Lokal Standar (Log)
Setelah pengujian selesai, Anda disarankan mengembalikan mailer lokal ke mode `log` agar tidak mengirim email sungguhan:
```env
MAIL_MAILER=log
```
Email yang terpicu akan dicatat di `storage/logs/laravel.log`.
