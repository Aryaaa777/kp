# Panduan Agen AI — Sistem Informasi Pendataan dan Pemantauan Koperasi Provinsi Jawa Barat

> Dokumen ini adalah panduan kerja untuk agen AI (OpenCode, Claude Code, Cursor, dsb.) yang bekerja
> di repositori ini. `AGENTS.md` dan `CLAUDE.md` sengaja dibuat **identik** agar konsisten.
> Bila mengubah salah satunya, ubah keduanya.

## 1. Sumber kebenaran (source of truth)

Baca dokumen berikut sebelum mengerjakan bagian apa pun:

| Dokumen | Isi |
| --- | --- |
| `docs/PRD.md` | Kontrak kebutuhan utama (FR/BR/AC/NFR-SEC) — rujukan tertinggi |
| `docs/IMPLEMENTATION_PLAN.md` | Fase F0–F8, backlog task, Definition of Done |
| `docs/DATA_DICTIONARY.md` | Skema tabel/kolom/constraint |
| `docs/USER_FLOWS.md` | Alur pengguna + diagram Mermaid |
| `docs/SETUP.md` | Cara menjalankan & menguji secara lokal |
| `docs/VERSIONS.md` | Versi terpasang & isu yang belum terselesaikan |

Aturan: bila instruksi mana pun bertentangan dengan dokumen di `docs/`, **dokumen di `docs/` yang menang**.
Perbarui dokumen terkait bila ada keputusan teknis yang berubah. **Jangan mengubah dokumen perencanaan
tanpa alasan yang jelas.**

## 2. Prasyarat lingkungan

Verifikasi bahwa perkakas tersedia:

```sh
php -v        # target proyek: PHP 8.3–8.5 (dev lokal 8.4.0)
composer -V   # Composer 2.x
node -v       # Node 22.x
npm -v        # npm 10.x
```

Instalasi prasyarat mengubah lingkungan mesin, jadi lakukan **hanya bila PHP/Composer benar-benar belum ada**
dan **dengan persetujuan pemilik proyek**. Jika perlu, deteksi OS pengguna dan gunakan installer resmi berikut:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

Setelah instalasi, minta pengguna menutup dan membuka ulang terminal. Jika agen membutuhkan shell yang sudah
di-restart, minta pengguna membuka ulang terminal lalu menjalankan kembali prompt semula.
Detail lingkungan lokal ada di `docs/SETUP.md`.

> Jangan menyimpulkan versi dari tebakan. Catat versi yang benar-benar terpasang di `docs/VERSIONS.md`.

## 3. Laravel Boost — TIDAK dipakai

**Laravel Boost sengaja tidak digunakan pada proyek ini** (keputusan F0/F1 dicatat di `docs/SETUP.md`
dan `docs/VERSIONS.md`). Jangan menambahkan instruksi untuk:

- `composer require laravel/boost --dev`
- `php artisan boost:install`

Jangan menjalankan kedua perintah di atas, dan **jangan mengembalikan** instruksi Boost ke dokumen ini,
kecuali pemilik proyek secara eksplisit mengubah keputusan ini.
Paket/dependency lain pun hanya ditambahkan bila ada kebutuhan teknis yang jelas dan setelah disetujui.

## 4. Prinsip kerja wajib

1. **Fase berurutan & terpisah** — setiap fase (F0…F8) dikerjakan **sendiri**, **diuji**, lalu **ditinjau**
   sebelum fase berikutnya dimulai. Jangan menggabungkan beberapa fase dalam satu pekerjaan.
2. **Vertical slice** — setiap task menghasilkan alur yang bisa diuji dari UI sampai database.
3. **Tes seiring fitur** — tulis feature/unit test bersamaan dengan fiturnya, bukan di akhir.
4. **Otorisasi di sisi server** — setiap route/aksi dilindungi middleware, Policy, atau Gate.
   Menyembunyikan menu/tombol **bukan** pengamanan.
5. **Anti-IDOR** — pengguna koperasi hanya boleh mengakses data koperasi yang tertaut ke akunnya.
6. **Tanpa data dummy / akun default berpassword** — seeder hanya untuk data referensi (role, wilayah).
   Jangan menanam kredensial di source code.
7. **Tanpa perubahan destruktif database** — dilarang `migrate:fresh`, `migrate:rollback` massal,
   `db:wipe`, `DROP`, `TRUNCATE`, atau penghapusan data tanpa persetujuan eksplisit.
   Imigrasi lama yang sudah berjalan **tidak boleh diubah/dihapus**; buat migrasi baru.
8. **Tanpa secret di repositori** — `.env` tidak di-commit; gunakan `.env.example` berisi placeholder.
9. **Tanpa dependency/paket auth eksternal** — gunakan autentikasi bawaan Laravel
   (session guard). Jangan menambahkan Breeze/Jetstream/Sanctum tanpa alasan kuat & persetujuan.
10. **Stack tetap** — Laravel + Blade + Tailwind v4 + MySQL. Jangan beralih ke SPA/kerangka JS
    tanpa alasan yang kuat dan persetujuan.

## 5. Keputusan terbuka — jangan dikarang

Beberapa hal **belum dikonfirmasi dinas**. Jangan mengarang nilainya; tandai dengan jelas dan gunakan
pendekatan sementara yang tidak mengunci desain secara permanen:

- **Target DB produksi** (MySQL vs MariaDB) masih menunggu konfirmasi. Dev lokal memakai MariaDB 10.4.32;
  ini **bukan** konfirmasi target produksi.
- **Pertanyaan P0** (PRD §21, Q-001…Q-008): format & sumber resmi nomor identitas koperasi, jenis/bentuk
  koperasi, prosedur & status verifikasi resmi, peran/kewenangan admin & pembatasan wilayah, field yang
  boleh diubah pengelola, formula penilaian kesehatan, dan aturan publikasi.
- **Referensi wilayah** (Q-012) belum ditetapkan sumber resminya.
- **Sub-peran admin & pembatasan wilayah** masuk fase lanjutan (F8), bukan MVP.

## 6. Konvensi teknis

- Bahasa antarmuka & pesan: **Indonesia**; zona waktu `Asia/Jakarta` (`APP_TIMEZONE`).
- Tailwind v4 melalui `@tailwindcss/vite` — tanpa `tailwind.config.js`; token tema di directive `@theme`
  pada `resources/css/app.css`.
- Otorisasi F1 (acuan pola): middleware `role` dan `active`, serta Gate `access-koperasi`.
- Perintah pengujian & kualitas:
  - `php artisan test` — seluruh tes
  - `./vendor/bin/pint` — format kode
  - `npm run build` — build aset produksi (di PowerShell lingkungan ini: `npm.cmd run build`)
  - `php artisan migrate:status` — status migrasi
  - `composer audit` — cek advisory keamanan
- Membuat akun pengguna pertama: `php artisan user:create` (password diminta interaktif, tidak ditanam di kode).

## 7. Alur kerja per fase

1. Baca dokumen relevan (PRD FR/AC, DATA_DICTIONARY, USER_FLOWS, IMPLEMENTATION_PLAN) dan pastikan
   pertanyaan **P0** terkait sudah terjawab.
2. Implementasikan **hanya** dalam cakupan fase tersebut.
3. Jalankan verifikasi: `php artisan test`, `npm run build`, `php artisan migrate:status`, periksa `git diff`
   dan pastikan tidak ada secret yang masuk.
4. Laporkan file yang dibuat/diubah, hasil tes beserta bukti perintah, serta isu yang belum selesai.
5. **Berhenti dan tunggu peninjauan/persetujuan sebelum memulai fase berikutnya.**

## 8. Definition of Done

Rujuk PRD §22 dan `docs/IMPLEMENTATION_PLAN.md` §7. Ringkasnya: FR & AC terbukti, validasi di server,
otorisasi server-side, keamanan, migrasi/FK/soft delete sesuai rancangan, audit, UI/UX konsisten,
tes hijau tanpa regresi, dokumentasi diperbarui, ditinjau, dan dikomit dengan pesan jelas.
