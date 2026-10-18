# Implementation Plan

## Sistem Informasi Pendataan dan Pemantauan Koperasi Provinsi Jawa Barat

| Field | Value |
| --- | --- |
| Versi | 1.0 (draft) |
| Terkait | `docs/PRD.md`, `docs/DATA_DICTIONARY.md`, `docs/USER_FLOWS.md` |
| Peran | Rencana implementasi bertahap untuk OpenCode + Laravel + Blade + Tailwind + MySQL |
| Prasyarat | PRD disetujui & pertanyaan **P0** (§21 PRD) terkonfirmasi |

> Dokumen ini adalah **rencana**, bukan instruksi untuk membangun seluruh aplikasi sekaligus. Eksekusi dilakukan per fase/task, dengan review di tiap akhir fase.

---

## 1. Ringkasan Stack & Versi Terverifikasi

| Komponen | Versi | Sumber verifikasi | Status |
| --- | --- | --- | --- |
| Laravel | 13.x (`^13.0`) | laravel.com/docs/13.x/releases | **[REKOMENDASI]** |
| PHP | 8.3–8.5 (dev lokal 8.4.0) | laravel.com/docs/13.x/releases | Terverifikasi lokal |
| Composer | 2.8.9 | lingkungan lokal | Terverifikasi |
| Tailwind CSS | v4.3 (`@tailwindcss/vite`) | tailwindcss.com/docs (Laravel guide) | **[REKOMENDASI]** |
| Node.js | 22.15.1 | lingkungan lokal | Terverifikasi |
| MySQL | 8.x | kebutuhan proyek | **[NEEDS CONFIRMATION]** — belum terdeteksi di PATH |
| Git | 2.48.1 | lingkungan lokal | Terverifikasi |

> **Jangan menetapkan versi final dari tebakan.** Saat `composer create-project laravel/laravel`, biarkan Composer memilih patch terbaru yang kompatibel; catat versi yang benar-benar terpasang di `docs/`.

---

## 2. Prinsip Eksekusi

1. **Fase berurutan** — selesaikan & uji satu fase sebelum mulai berikutnya.
2. **Vertical slice** — tiap task menghasilkan alur yang bisa diuji dari UI sampai DB.
3. **Uji seiring fitur** — tulis feature test bersamaan dengan fitur, bukan di akhir.
4. **Server-side authorization** — setiap route & aksi dilindungi middleware/Policy.
5. **Tidak ada data dummy di produksi** — seeder hanya untuk referensi (wilayah, role) & testing.
6. **Commit kecil & jelas** — satu task = satu commit bermakna pada branch fitur.

---

## 3. Fase Implementasi

### F0 — Inisialisasi Proyek
**Dependensi:** —
**Deliverable:** skeleton Laravel + Tailwind + DB + Git.

- [ ] `composer create-project laravel/laravel .` (Laravel 13).
- [ ] Konfigurasi `.env` (DB, `APP_URL`, `APP_ENV`); pastikan tidak ter-commit.
- [ ] Install Tailwind v4 via `npm install tailwindcss @tailwindcss/vite`; konfigurasi `vite.config.js` + `resources/css/app.css` (`@import "tailwindcss"`).
- [ ] Inisialisasi Git + `.gitignore` benar (`.env`, `vendor/`, `node_modules/`, storage).
- [ ] Buat layout dasar `layouts/app`, `layouts/guest`, `layouts/public`.
- [ ] Buat komponen dasar: button, input, select, badge, alert, stat-card, table, modal, empty-state.
- [ ] Konfigurasi Pint + format standar.

### F1 — Autentikasi, RBAC, Akun
**Dependensi:** F0
**FR:** 001–004, 010, 011

- [ ] Migration `users` (+ `koperasi_id`, `region_id`, `status`), `roles`, `role_user`.
- [ ] Seeder role: `admin_dinas`, `pengelola_koperasi`.
- [ ] Auth (login/logout) + rate limiting + `last_login_at`.
- [ ] Middleware `role` + `EnsureUserIsActive`.
- [ ] Manajemen akun pengelola (CRUD) + pengaitan koperasi (placeholder sampai F2).
- [ ] Reset password (self-service/admin).
- [ ] Feature test: AC-AUTH-01/02/03/04, AC-AUTHZ-01/02/03.

### F2 — Data Induk Koperasi & Referensi Wilayah
**Dependensi:** F1
**FR:** 040–043, 160

- [ ] Migration `regions` + `RegionSeeder` (sumber resmi **[NEEDS CONFIRMATION]**).
- [ ] Migration `cooperatives` (unique `nomor_identitas`, `slug`).
- [ ] CRUD data induk (admin) + Model + Policy + Form Request.
- [ ] Profil koperasi (pengelola, read-only) + halaman "akun belum terhubung".
- [ ] Pencarian, filter, pagination, pengurutan (whitelist kolom).
- [ ] Feature test: AC-CRUD-01/02/03.

### F3 — Perizinan & Dokumen
**Dependensi:** F2
**FR:** 050, 051, 060, 061, 070, 071

- [ ] Migration `cooperative_licenses` + `documents` (polimorfik).
- [ ] CRUD izin usaha & izin simpan pinjam + verifikasi (admin).
- [ ] Storage privat `storage/app/private/documents`; unggah/unduh via controller ber-Policy.
- [ ] Validasi tipe/ukuran berkas.
- [ ] Feature test: AC-LIC-01, AC-DOC-01/02/03.

### F4 — Data Periodik (Aset, Anggota, Kesehatan, Tata Kelola, Pengurus)
**Dependensi:** F2
**FR:** 080, 081, 090, 100, 101, 110, 111, 120, 121

- [x] Migrations: `health_assessments`, `governance_records`, `cooperative_assets`, `member_histories`, `officers`.
- [x] CRUD per modul dengan unique constraint periode (`cooperative_id`,`period_year`,`period` untuk kesehatan & tata kelola).
- [x] Tampilan riwayat antar periode (tanpa menimpa) + pencarian/filter/pagination.
- [x] Otorisasi: kesehatan = kewenangan admin (BR-010); modul lain admin CRUD; pengelola **read-only** (koperasinya sendiri).
- [x] Feature test: AC-PER-01/02/03 (tercapai saat penyelesaian F4: 189 tes hijau / 607 assertions — angka historis, **bukan** hasil suite terkini; hasil terkini pada catatan F5 di bawah).
- [x] Seeder demo opt-in (idempoten, menolak produksi, tanpa akun) untuk keempat modul.

> **PROVISIONAL — MENUNGGU KONFIRMASI DINAS (Q-010).** F4 diimplementasikan dengan asumsi sementara
> (kesehatan/tata kelola/aset = tahunan; keanggotaan = per kejadian) agar tidak terblokir. Aturan & nilai
> belum resmi dan **wajib ditinjau ulang** setelah Q-010 terjawab. Rincian: `docs/DECISIONS.md` §10;
> skema: `docs/DATA_DICTIONARY.md` §9–§12.
> **Belum dijalankan pada DB pengembangan** (migrasi hanya diuji pada SQLite in-memory). Pengelola
> menulis via workflow pengajuan = **F5** (belum ada).

### F5 — Workflow Pengajuan & Verifikasi + Audit
**Dependensi:** F3, F4
**FR:** 140–143, 150, 151

- [x] Migrations: `submissions`, `submission_items`, `submission_reviews`, `activity_logs` (+ `notifications` Laravel untuk FR-201).
- [x] `SubmissionService` (buat, kirim, transisi, terapkan) dengan state guard.
- [x] Antrian verifikasi (admin) + aksi setujui/perbaikan/tolak (alasan wajib).
- [x] Penerapan perubahan ke data resmi dalam **DB transaction** + simpan nilai lama.
- [x] Audit trail otomatis untuk aksi sensitif.
- [x] Notifikasi in-app status (FR-201).
- [x] Feature test: AC-WF-01…05, AC-AUD-01/02.

> **Status F5: SELESAI (menunggu peninjauan).** Migrasi `2026_10_16_130001` ... `130005`
> (termasuk tabel `notifications` Laravel untuk FR-201) **belum dijalankan** pada DB pengembangan
> (hanya diuji pada SQLite in-memory). Verifikasi: `php artisan test` **262 tes / 866 asersi PASS**
> (F5: 49 tes), `pint --test` PASS, `npm run build` PASS, `composer audit` bersih.
>
> **Perbaikan pasca-audit (TAHAP 11):** konkurensi (lock + conditional update), whitelist defense-in-depth,
> semantik arsip (status + soft delete), kegagalan notifikasi, target koperasi tidak tersedia, unique
> `submission_items`, dan validasi alasan. Lihat `docs/DECISIONS.md` §12 (F5-15…F5-20).
>
> **PROVISIONAL — MENUNGGU KONFIRMASI DINAS:** Q-004 (verifikasi **satu tingkat** oleh admin dinas),
> Q-006 (whitelist modul/field — saat ini hanya `koperasi_induk`), Q-011 (notifikasi **in-app** saja).
> Aksi sensitif yang diaudit saat ini: workflow pengajuan, CRUD/arsip data induk, login, verifikasi
> izin, dan unduh dokumen. Rincian keputusan & interpretasi: `docs/DECISIONS.md` §12.

### F6 — Dashboard, Pencarian, Laporan Dasar
**Dependensi:** F5
**FR:** 020, 021, 030, 031, 170

- [ ] Dashboard admin (statistik + antrian) & dashboard pengelola (kelengkapan + perbaikan).
- [ ] Laporan/rekapitulasi per wilayah/jenis/status/periode (hanya data approved).
- [ ] Feature test: AC-REP-01.

### F7 — Direktori Publik & Kontrol Publikasi
**Dependensi:** F5
**FR:** 180–182, 190, 191

- [ ] Route publik (tanpa auth) + halaman direktori, pencarian/filter, profil publik.
- [ ] `settings` untuk kebijakan field publik + kontrol `is_published` per koperasi.
- [ ] Masking field sensitif (NFR-SEC-015).
- [ ] Feature test: AC-PUB-01/02/03.

### F8 — Lanjutan (Post-MVP)
**Dependensi:** F7
**FR:** 012, 022, 072, 082, 091, 171, 183

- [ ] Ekspor CSV/Excel/PDF + audit ekspor.
- [ ] Sub-peran admin + pembatasan wilayah.
- [ ] Statistik agregat publik.
- [ ] Versi dokumen & arsip.
- [ ] Perhitungan kesehatan otomatis (bila formula resmi tersedia).

---

## 4. Backlog Task (contoh, per fase)

> Format: `T-<fase><nn>`. Estimasi kasar (hari) bersifat indikatif untuk kerja praktik.

| Task | Deskripsi | Fase | Est. |
| --- | --- | --- | --- |
| T-001 | Init Laravel + Tailwind + Git | F0 | 1 |
| T-002 | Layout & design system (Blade components) | F0 | 2 |
| T-010 | Migration auth + roles seeder | F1 | 1 |
| T-011 | Login/logout + rate limit | F1 | 1 |
| T-012 | Middleware role + Policies dasar | F1 | 1 |
| T-013 | Manajemen akun pengelola | F1 | 1 |
| T-020 | Migration regions + seeder | F2 | 1 |
| T-021 | Migration + CRUD cooperatives | F2 | 2 |
| T-022 | Profil koperasi (pengelola) | F2 | 1 |
| T-023 | Pencarian/filter/pagination | F2 | 1 |
| T-030 | Licenses CRUD + verifikasi | F3 | 2 |
| T-031 | Documents upload/download privat | F3 | 2 |
| T-040 | Migrations data periodik | F4 | 1 |
| T-041 | CRUD aset & anggota + riwayat | F4 | 2 |
| T-042 | CRUD kesehatan & tata kelola (admin) | F4 | 2 |
| T-043 | CRUD pengurus & periode | F4 | 1 |
| T-050 | Migrations submissions + items + reviews | F5 | 1 |
| T-051 | SubmissionService + state guard | F5 | 2 |
| T-052 | UI antrian & keputusan verifikasi | F5 | 2 |
| T-053 | Penerapan perubahan + audit + notifikasi | F5 | 2 |
| T-060 | Dashboard admin & pengelola | F6 | 2 |
| T-061 | Laporan/rekapitulasi dasar | F6 | 2 |
| T-070 | Direktori & profil publik | F7 | 2 |
| T-071 | Kontrol publikasi + settings | F7 | 1 |
| T-080 | Ekspor laporan | F8 | 2 |
| T-081 | Sub-peran & pembatasan wilayah | F8 | 2 |

> Estimasi total indikatif MVP (F0–F7) ≈ 35–45 hari kerja. Sesuaikan dengan jadwal KP. Jika mepet, pangkas F4/F6/F7 bertahap (lihat §19.2 PRD).

---

## 5. Urutan Kerja yang Disarankan (Definition of Ready)

Sebelum task dimulai, pastikan:
- FR & AC terkait sudah jelas (rujuk PRD §7, §10).
- Tabel & kolom terkait sudah didefinisikan (rujuk `DATA_DICTIONARY.md`).
- Alur terkait sudah dipetakan (rujuk `USER_FLOWS.md`).
- Pertanyaan P0 yang relevan sudah terjawab.

---

## 6. Checklist Pengujian per Fase

| Fase | Fokus uji | Minimal |
| --- | --- | --- |
| F1 | Authn/Authz | AC-AUTH-*, AC-AUTHZ-* |
| F2 | CRUD & isolasi data | AC-CRUD-*, AC-AUTHZ-01 |
| F3 | Perizinan & dokumen | AC-LIC-01, AC-DOC-* |
| F4 | Data periodik | AC-PER-* |
| F5 | Workflow & audit | AC-WF-*, AC-AUD-* |
| F6 | Laporan | AC-REP-01 |
| F7 | Publik | AC-PUB-* |
| F8 | Ekspor & lanjutan | §18 PRD |

**Contoh perintah:**
- `php artisan test` — jalankan seluruh tes.
- `./vendor/bin/pint` — format kode.
- `npm run build` — build aset untuk produksi.

---

## 7. Checklist Definition of Done (ringkas)

Lihat §22 PRD. Ringkasnya: fungsional ✓, validasi ✓, otorisasi ✓, keamanan ✓, data/migration ✓, audit ✓, UI/UX ✓, tes hijau ✓, dokumentasi ✓, review ✓, commit jelas ✓, AC terbukti ✓.

---

## 8. Deployment (garis besar)

1. `composer install --no-dev --optimize-autoloader`
2. `npm ci && npm run build`
3. Set `.env` produksi; `php artisan key:generate` (sekali).
4. `php artisan migrate --force`
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
6. Atur permission `storage/` & `bootstrap/cache/`.
7. Web root → `public/`. Pastikan storage privat **tidak** terekspos web.
8. Backup DB harian + dokumentasi prosedur restore.

> Detail infrastruktur dinas **[NEEDS CONFIRMATION]** (Q-014). Jangan mengasumsikan server/domain/CI tertentu.

---

## 9. Definition of Ready/Done untuk AI Coding Agent

Saat meminta agent mengimplementasikan sebuah task, sertakan:
- ID FR & AC terkait.
- Tabel & kolom terkait (dari DATA_DICTIONARY).
- Alur terkait (USER_FLOWS).
- Batasan (otorisasi, validasi, keamanan).
- Kriteria tes yang harus lulus.

Contoh prompt:
> "Implementasikan T-021 (CRUD `cooperatives`) sesuai FR-040 & AC-CRUD-01. Gunakan Form Request + Policy. Nomor identitas unik. Sertakan feature test untuk duplikasi & validasi. Jangan buat data dummy di seeder."

---

## 10. Risiko Implementasi Utama

Rujuk PRD §20. Sorotan:
- **R-01** format data existing → selesaikan data discovery lebih dulu.
- **R-02** cakupan besar → jaga MVP & urutan fase.
- **R-03** aturan resmi tidak tersedia → tetap manual, label NEEDS CONFIRMATION.
