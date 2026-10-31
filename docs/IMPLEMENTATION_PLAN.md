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

- [x] Migration `users` (+ `koperasi_id`, `status`), `roles`, `role_user`; `region_id` tetap FASE-2.
- [x] Seeder role: `admin_dinas`, `pengelola_koperasi`.
- [x] Auth (login/logout) + rate limiting + `last_login_at`.
- [x] Middleware `role` + `EnsureUserIsActive` + `EnforceSessionIdleTimeout`.
- [x] Manajemen akun pengelola (daftar/cari/tambah/ubah/status) + pengaitan koperasi.
- [x] Reset password (self-service/admin) memakai password broker Laravel + tautan email.
- [x] Feature test: AC-AUTH-01/02/03/04, AC-AUTHZ-01/02/03.

> **Status F1 (finalisasi 24 Oktober 2026): SELESAI untuk cakupan F1.** Login/logout, rate limit,
> middleware `role`/`active`/`idle`, RBAC, isolasi data koperasi, audit login, **FR-003** (idle timeout
> 30 menit + daftar/pengakhiran sesi pada driver database), **FR-004** (reset password self-service &
> kirim tautan oleh admin), dan **US-009** (manajemen akun pengelola via UI) sudah diimplementasikan
> dan diuji. Provisioning awal tetap tersedia lewat `php artisan user:create`; akun berikutnya dapat
> dibuat admin dari `/admin/akun` dengan tautan reset (tanpa password teks biasa).
> **Bukti:** `tests/Feature/Auth/SessionSecurityTest.php`, `PasswordResetTest.php`,
> `tests/Feature/Admin/AdminUserManagementTest.php`; suite penuh saat itu **583 tes / 2.609 asersi PASS** pada
> SQLite `:memory:`. **Batas:** daftar sesi lintas perangkat hanya untuk `SESSION_DRIVER=database`;
> transport email produksi dan staging MariaDB 10.11 belum diverifikasi; suite 10.11.19 lokal
> sudah lulus (Q-014).

### F2 — Data Induk Koperasi & Referensi Wilayah
**Dependensi:** F1
**FR:** 040–043, 160

- [ ] Migration `regions` + importer dataset resmi (Kepmendagri 300.2.2-2138/2025; lihat
  `docs/REGION_DATA_SOURCES.md`). Tidak ada `RegionSeeder` berisi data karangan.
- [ ] Migration `cooperatives` (unique `nomor_identitas`, `slug`).
- [ ] CRUD data induk (admin) + Model + Policy + Form Request.
- [ ] Profil koperasi (pengelola, read-only) + halaman "akun belum terhubung".
- [ ] Pencarian, filter, pagination, pengurutan (whitelist kolom).
- [ ] Feature test: AC-CRUD-01/02/03.

> **Status Q-012 (diperbarui 26 Oktober 2026):** dataset resmi (6.612 baris), importer idempoten,
> audit read-only, dan form berjenjang tersedia. Dataset **sudah diimpor ke database lokal**
> MariaDB 10.4.32. `cooperatives.region_id` tetap nullable karena audit menemukan satu koperasi tanpa
> wilayah dan tidak ada pemetaan deterministik; **tidak ada backfill/`NOT NULL`**. Rincian:
> `docs/REGION_DATA_SOURCES.md`, `docs/DECISIONS.md` §30 dan §33.

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
>
> **Diperbarui Tahap 56:** migrasi F4 **sudah dijalankan** pada `koperasi_jabar` dan workflow pengajuan
> F5 **sudah ada** (Tahap 53 → 25 tabel / 22 migrasi / 28 FK / batch 6). Kalimat di atas berlaku untuk
> keadaan **sebelum** Tahap 53.

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
>
> **Status peninjauan (Tahap 56):** F4 & F5 **DITERIMA** pemilik proyek sehingga Definition of Ready F6
> terpenuhi (keputusan F6-1, `docs/DECISIONS.md` §15). Seluruh commit F3–F5 **tetap lokal tanpa push**
> (push belum disetujui). Seluruh migrasi F4/F5 **sudah dijalankan** pada `koperasi_jabar` (Tahap 53 →
> 25 tabel / 22 migrasi / 28 FK / batch 6); suite terkini (Tahap 54): **280 tes / 902 asersi PASS**.

### F6 — Dashboard, Pencarian, Laporan Dasar
**Dependensi:** F5
**FR:** 020, 021, 030, 031, 170

- [x] Dashboard admin (statistik + antrian) & dashboard pengelola (kelengkapan + perbaikan).
- [x] Melengkapi filter **wilayah** pada daftar koperasi yang sudah ada (FR-160) — tanpa halaman pencarian global baru.
- [x] Laporan/rekapitulasi per wilayah/jenis/status/periode (hanya data approved).
- [x] Feature test: AC-REP-01.

> **STATUS F6 (Tahap 57): diimplementasikan & teruji — MENUNGGU PENINJAUAN (bukan "selesai", belum di-commit).**
> Bukti nyata: `php artisan test` **314 tes / 1.084 asersi PASS** (baseline 280/902 + 34 tes baru F6/182 asersi);
> `pint --test` PASS; `npm run build` PASS; `git diff --check` bersih; `migrate:status` 22 migrasi `Ran`, tanpa pending.
> **Tanpa migrasi/DDL/DML** dan **tanpa mengubah data DB dev**; semua pemeriksaan MariaDB bersifat baca-saja.
> Berkas baru: `app/Services/Reporting/{PeriodExpression,DashboardStatistics,CooperativeReport,CompletenessReport}.php`,
> `app/Http/Controllers/Admin/ReportController.php`, `resources/views/admin/laporan/index.blade.php`,
> `resources/views/components/stat-card.blade.php`, `tests/Feature/Reporting/` (4 berkas).
> **Pelajaran portabilitas:** pengujian baca-saja pada MariaDB 10.4.32 menemukan MySQL `ONLY_FULL_GROUP_BY`
> menolak `GROUP BY COALESCE(regions.name, ?)` → diganti `GROUP BY regions.name`.
> **Ditinjau ulang Tahap 66:** `GROUP BY regions.name` ternyata menggabungkan wilayah bernama sama dan
> membuat tautan daftar tidak konsisten dengan angkanya (temuan R-4); kini memakai
> **`GROUP BY cooperatives.region_id`** dengan label `MAX(regions.name)` (§19.1).
> **FR-031 — penyimpangan tercatat:** alasan & tautan perbaikan tampil, tetapi frasa AC PRD
> "revisi membuat pengajuan **terpisah**" **tidak** diterapkan karena workflow F5 yang **sudah diterima**
> (F5-3 / PRD §12.2) memakai record yang sama (`needs_revision → submitted`). Konflik dokumentasi ini
> **dilaporkan ke pemilik**, tidak diubah sendiri.
> **Masih PROVISIONAL:** periode = tahun `created_at` (F6-3, Q-010); wilayah kosong = "Belum ditetapkan"
> (F6-4, Q-012, tanpa seeding DB dev); rumus bobot kelengkapan per modul bersifat **[ASUMSI]** (Q-006/Q-010);
> kebutuhan format laporan pimpinan (Q-022) belum ditetapkan sehingga laporan dibatasi pada rekap agregat dasar.
>
> **TINDAK LANJUT AUDIT (Tahap 58/59) — F6 TETAP MENUNGGU PENINJAUAN (belum "selesai").** Rincian:
> `docs/DECISIONS.md` §16. Audit read-only menemukan **blocker/keputusan tertunda** berikut — **bukan** bukti
> bahwa AC F6 telah lulus seluruhnya:
> (1) **FR-031/FR-142** — frasa AC "revisi membuat pengajuan/versi terpisah" **belum terpenuhi**; perilaku
> record-sama **dipertahankan sementara** (keputusan pemilik D-1 diperlukan; opsi B = `parent_submission_id`
> + migrasi baru + harmonisasi PRD §12.2/§12.5);
> (2) **FR-030** — rumus **bobot setara 7 modul** tetap **[ASUMSI]**, belum ditetapkan PRD (D-2); 6 dari 7
> modul tidak dapat diisi pengelola;
> (3) **filter** — FR-021 (**umur** pengajuan) **sudah diimplementasikan (Tahap 60)**: dasar `updated_at`,
> ambang angka hari dari admin `[ASUMSI]` (lihat §16.5); FR-020 (filter dashboard; D-3) & FR-160 (filter
> **periode** daftar koperasi; D-5) **masih menunggu keputusan**.
> Lulus-nya 323 tes (Tahap 60) **tidak** berarti F6 siap ditutup; keputusan pemilik yang tersisa:
> **D-1, D-2, D-3, D-5** (**D-4** sudah dijawab Tahap 60).
>
> **EKSEKUSI KEPUTUSAN (Tahap 63) — D-1/D-2/D-3 diimplementasikan; D-5 ditunda; F6 TETAP MENUNGGU PENINJAUAN.**
> Rincian lengkap & bukti: `docs/DECISIONS.md` §17. Pemilik memilih **opsi B** untuk D-1/D-2/D-3 dan
> **opsi B bersyarat** untuk D-5:
> (1) **D-1** — versioning pengajuan: migrasi **baru** `2026_10_19_000001_add_parent_submission_id_to_submissions_table.php`
> (kolom nullable + indeks + self-FK; migrasi lama tidak diubah), `EDITABLE_STATUSES=['draft']`, revisi membuat
> versi baru, satu pengajuan aktif per koperasi+modul, PRD §12.2/§12.5 + FLOW-PGL-04 diselaraskan;
> (2) **D-2** — kelengkapan FR-030 = per **field wewenang pengelola** (bukan bobot 7 modul), modul admin
> dikeluarkan dari persentase & ditampilkan sebagai informasi;
> (3) **D-3** — kontrol filter wilayah/jenis/status/periode pada dashboard admin via komponen bersama
> `CooperativeFilters` (dipakai juga oleh laporan → angka konsisten);
> (4) **D-5** — filter periode pada daftar koperasi **ditunda**: Q-010 belum dijawab & definisi periode masih
> `[SEMENTARA]` (F6-3), jadi **tidak dikarang** (hambatan tercatat, §17.4).
> **Migrasi baru berstatus `Pending`** pada DB dev `koperasi_jabar` (belum dijalankan; butuh izin terpisah).
> Verifikasi Tahap 63: `php artisan test` **341 tes / 1.233 asersi PASS** (SQLite `:memory:` saja — **bukan**
> bukti kompatibilitas MariaDB), `pint --test` PASS, `npm run build` PASS, `git diff --check` bersih.
> **Tindak lanjut audit (Tahap 65):** temuan T-1 (serialisasi pembuatan pengajuan per koperasi),
> S-1 (kartu "Butuh Perbaikan" = definisi actionable yang sama dengan daftar), S-2 (normalisasi
> `status_verifikasi` daftar koperasi via `CooperativeFilters::normalizeStatus()`), serta koreksi
> dokumentasi R-1…R-3 dikerjakan — `docs/DECISIONS.md` §18. Verifikasi Tahap 65: `php artisan test`
> **352 tes / 1.287 asersi PASS** (SQLite saja). **FR-160 tetap Sebagian** (filter periode daftar
> koperasi **belum** diimplementasikan; D-5 ditunda, Q-010 belum final). F6 **tetap MENUNGGU PENINJAUAN**.
> **Tindak lanjut audit (Tahap 66):** **R-4 diperbaiki** — bucket wilayah dashboard & laporan dikelompokkan
> per **`region_id`** stabil (bukan `regions.name` yang tidak unik), tautan "Daftar" memakai `region_id`
> (NULL → `none`) sehingga angka bucket = hasil daftar (FR-020 AC) — `docs/DECISIONS.md` §19.1. Audit
> **source** migrasi `2026_10_19_000001_*` (tipe kolom, indeks, self-FK `nullOnDelete`, `up()`/`down()`)
> dan **rencana verifikasi MariaDB** disusun (§19.2; belum dijalankan). Verifikasi Tahap 66:
> `php artisan test` **355 tes / 1.305 asersi PASS** (SQLite saja). Migrasi **masih `Pending`**;
> **Q-006/Q-010/Q-012 tetap terbuka**; F6 **tetap MENUNGGU PENINJAUAN**.
> **Tindak lanjut audit (Tahap 67):** audit kesiapan verifikasi MariaDB (read-only) + **matriks blocker
> F6** — `docs/DECISIONS.md` §20. Audit regresi **R-4** tidak menemukan cacat (+1 tes regresi: Σ bucket =
> total). Audit **source** migrasi menemukan **dua hal yang belum diperbaiki**: rasional SQLite di komentar
> tidak akurat (§20.2) dan `up()` **tidak idempoten** bila FK gagal di statement kedua (§20.3). Audit
> **T-1**: kedua jalur pembuatan mengunci koperasi lebih dulu di dalam transaksi; tidak ada jalur
> alternatif — **efektivitas lock belum terbukti** (SQLite no-op, konkurensi MariaDB belum diuji). Verifikasi Tahap 67:
> `php artisan test` **356 tes / 1.312 asersi PASS** (SQLite saja), `pint --test` PASS, `npm run build` PASS,
> `git diff --check` bersih, `migrate:status` **22 Ran + 1 Pending**. Migrasi **tetap `Pending`**;
> **Q-006/Q-010/Q-012 tetap terbuka**; F6 **tetap MENUNGGU PENINJAUAN**.
> **Tindak lanjut audit (Tahap 68):** **idempotensi `up()` migrasi `2026_10_19_000001_*` diperbaiki** —
> kolom, indeks, dan self-FK diperiksa **terpisah** (`classify()`: `missing`/`present`/`conflict`), sehingga
> eksekusi ulang setelah gagal sebagian **melengkapi** komponen yang hilang (FK tidak lagi bisa hilang
> senyap). FK ekuivalen bernama lain tetap diterima; FK menyimpang → **berhenti** dengan diagnostik.
> `down()` melepas FK memakai **nama constraint yang dilaporkan driver** dan melepas indeks sebelum kolom.
> Klaim "SQLite tidak mendukung FK pada `ALTER TABLE`" **dihapus/dikoreksi**: migrasi **secara eksplisit**
> melewati self-FK pada SQLite → FK **tidak** divalidasi oleh suite SQLite (memerlukan MariaDB). Rincian:
> `docs/DECISIONS.md` §21. Verifikasi Tahap 68: `php artisan test` **376 tes / 1.390 asersi PASS** (SQLite
> saja), `pint --test` PASS, `npm run build` PASS, `git diff --check` bersih, `migrate:status` **22 Ran +
> 1 Pending**. Migrasi **tetap `Pending`**; **Q-006/Q-010/Q-012 tetap terbuka**; R-4 & T-1 **menunggu
> verifikasi MariaDB**; F6 **tetap MENUNGGU PENINJAUAN**.
>
> **Tindak lanjut audit (Tahap 70):** tindak lanjut audit Tahap 69 (F-69-01…F-69-11) — lihat
> `docs/DECISIONS.md` §21.7. **F-69-01** kepemilikan indeks (`classifyIndex()`: nama **dan** definisi;
> indeks pihak lain tidak pernah dihapus); **F-69-02** `down()` **destruktif** terhadap tautan versi
> (didokumentasikan, butuh persetujuan eksplisit); **F-69-03** `DROP INDEX` dapat gagal lebih awal di
> MariaDB (errno 1553); **F-69-05** penjagaan urutan dipertahankan (pola `2026_10_16_100004`);
> **F-69-06** serialisasi eksekusi migrasi = persyaratan operasional; **F-69-07/08/10** tes diperkuat;
> **F-69-09** ditandai **[TIDAK DAPAT DIVERIFIKASI]**; **F-69-11a/b/c** dokumentasi diselaraskan.
> Verifikasi Tahap 70: `php artisan test` **392 tes / 1.481 asersi PASS** (SQLite saja), `pint --test`
> PASS, `npm run build` PASS, `git diff --check` bersih. Migrasi **tetap `Pending`** (tidak dijalankan);
> R-4 & T-1 **menunggu verifikasi MariaDB**; F6 **tetap MENUNGGU PENINJAUAN**.

> **KEPUTUSAN PEMILIK (Tahap 56) — cakupan F6 terkunci; implementasi BELUM dimulai.** Rincian: `docs/DECISIONS.md` §15.
> (1) F4/F5 diterima → DoR F6 terpenuhi, commit tetap lokal; (2) laporan hanya `status_verifikasi='approved'`
> (AC-REP-01); (3) periode = tahun `cooperatives.created_at` **[PROVISIONAL]**; (4) dimensi wilayah **masuk** F6,
> `region_id` NULL → "Belum ditetapkan", **tanpa seeding** DB dev (tes memakai factory); (5) pencarian = pelengkap
> filter wilayah (bukan pencarian global baru); (6) **tanpa** widget izin ≤30 hari — hanya **izin yang sudah
> kedaluwarsa** (FR-050); **FR-022** (FASE-2) & **FR-171** (ekspor, FASE-2) **di luar** F6.
> **Tanpa migrasi baru** — semua objek & indeks sudah ada (§15.3).
> **Pengecualian (Tahap 63):** keputusan **D-1 opsi B** menambahkan satu migrasi **baru** `parent_submission_id`
> (§17.1); **tidak ada migrasi lama/`Ran` yang diubah**. Berkas migrasi baru itu **masih `Pending`** dan
> **isinya disempurnakan** pada Tahap 68 & Tahap 70 — penyempurnaan **tidak** berarti migrasi sudah
> dijalankan (§21.7, F-69-11b).

> **Tahap 107 — penyesuaian dimensi wilayah (F6):** karena `region_id` kini dapat menunjuk
> desa/kelurahan, dashboard dan laporan mengelompokkan ke **kabupaten/kota** lewat
> `App\Services\Reporting\RegencyGrouping`; filter wilayah memakai **descendant** (memilih
> kabupaten/kota mencakup desa di bawahnya). Opsi filter dashboard dibatasi ke kabupaten/kota.
> Tautan "Daftar" tetap konsisten dengan angka bucket. Tes: `AdminDashboardTest` (19/95); gabungan
> `AdminDashboardTest|AdminReportTest|CooperativeRegionFilterTest` **41 tes / 224 asersi**.

> **STATUS FINAL F6 (finalisasi 24 Oktober 2026): SELESAI — implementasi & pengujian.**
> Seluruh AC F6 yang dapat diverifikasi lokal telah lulus: FR-020 (dashboard + filter + angka = daftar),
> FR-021 (antrian + filter umur), FR-030 (kelengkapan per field wewenang), FR-031 (versi pengajuan
> terpisah), dan FR-170/AC-REP-01 (hanya `approved`; rekap wilayah/jenis/periode).
> **D-5 dieksekusi pada finalisasi** memakai definisi periode **PROVISIONAL** F6-3 yang sudah ada
> (tahun `cooperatives.created_at`), sehingga FR-160 kini juga memiliki filter **periode**; UI dan
> dokumen menandainya PROVISIONAL sampai **Q-010** dijawab. Tautan "Daftar" pada seluruh tabel
> dashboard mempertahankan filter aktif dan setara dengan daftar koperasi. Rekap per periode
> ditambahkan ke laporan (bukan hanya filter).
> **Bukti tes final (baseline F6/F7):** `php artisan test --no-coverage` **543 tes / 2.443 asersi PASS**
> (SQLite `:memory:`). Setelah penutupan gap F1/FR-191, suite saat itu adalah **583 tes / 2.609
> asersi PASS**. Tes F6 terkait:
> `AdminDashboardTest`, `AdminReportTest`, `CooperativeRegionFilterTest`, `PengelolaDashboardTest`,
> `SubmissionQueueAgeFilterTest`, `SubmissionVersioningTest`.
> **Batas eksternal yang tetap terbuka:** Q-010 (definisi resmi periode) — implementasi memakai
> definisi sementara dan tidak mengklaimnya final; Q-006 (whitelist field pengajuan) — hanya modul
> data induk yang aktif; Q-022 (format laporan pimpinan) — laporan masih agregat dasar; verifikasi
> runtime/DDL **MariaDB 10.11.19** sudah diuji lokal; staging belum (Q-014).

### F7 — Direktori Publik & Kontrol Publikasi
**Dependensi:** F5
**FR:** 180–182, 190, 191
**Status final (finalisasi 24 Oktober 2026): SELESAI — implementasi & pengujian.** FR-180/181/182 & FR-190
sudah diimplementasikan; **FR-191** kini memiliki **UI admin** dan penyimpanan persisten di tabel
`settings` (migrasi aditif `2026_10_24_000003_create_settings_table`), dengan **Q-008 sebagai batas
maksimum** yang juga ditanam di `PublicFieldSettings`. **D-6 selesai** (jejak penarikan publikasi).
**Status operasional koperasi selesai** (Tahap 106, `docs/DECISIONS.md` §29). Path publik mengikuti
**PRD §9** (`/koperasi`, `/koperasi/{slug}`). Rincian: `docs/DECISIONS.md` §22–§24, §28–§29.
Finalisasi menambahkan halaman error **419/500** yang ramah (FR-200), penjagaan skema URL website
publik untuk data lama/import, serta peringatan admin bila koperasi masih ditandai publikasi tetapi
status verifikasinya tidak lagi memenuhi syarat tampil.

- [x] Route publik (tanpa auth) + halaman direktori, pencarian/filter, profil publik. (FR-180/181/182)
- [x] Kontrol `is_published` per koperasi + jejak publikasi/penarikan (FR-190, D-6).
      POST `/admin/koperasi/{cooperative}/publikasikan` & `/tarik-publikasi`.
- [x] Masking field sensitif (NFR-SEC-015). (`App\Support\PiiMask` + `PublicFieldSettings` berbasis allowlist Q-008)
- [x] Feature test: AC-PUB-01/02/03. (`DirektoriPublikTest`, `CooperativePublicationTest`, `PublicSurfaceExposureTest`, `PublicSettingsTest`)
- [x] FR-191 — UI admin `/admin/pengaturan/publik`, penyimpanan `settings`, validasi server-side,
      default aman, dan integrasi ke direktori/profil publik. Field di luar Q-008 tetap ditolak
      (validasi + penyaringan ganda di service).
- [x] **Status operasional koperasi** — kolom `status_operasional` (`aktif`/`tidak_aktif`/`bubar`, nullable
      tanpa default), wajib saat tambah, data lama `NULL` dilengkapi lewat edit (Tahap 106,
      `docs/DECISIONS.md` §29). Data lama yang belum dikonfirmasi **tidak** dianggap aktif.
- [x] **Data wilayah (Q-012)** — alamat publik memakai hierarki `regions`; dataset resmi + importer
      tersedia (Tahap 107, `docs/REGION_DATA_SOURCES.md`). **Catatan:** impor ke database persisten
      belum dijalankan, sehingga selama `regions` kosong alamat publik menampilkan "Belum ditetapkan".

> **STATUS FINAL F7 (finalisasi 24 Oktober 2026): SELESAI — implementasi & pengujian.**
> AC-PUB-01/02/03 lulus: hanya koperasi `is_published` + status resmi yang tampil; field publik
> mengikuti pilihan admin yang dibatasi allowlist Q-008; email/telepon termasking; profil
> langsung tidak dapat diakses bila tidak layak. Kontrol publikasi/penarikan, jejak D-6, status operasional, dan halaman error
> 403/404/419/500 sudah tersedia. **Bukti:** `DirektoriPublikTest`, `PublicSurfaceExposureTest`,
> `CooperativePublicationTest`, `PublicSettingsTest`, `ErrorHandlingTest`,
> `CooperativeStatusOperasionalTest`.
> **Batas eksternal (diperbarui 26 Oktober 2026):** migrasi `settings` dan migrasi F6/F7 lain
> **sudah dijalankan di DB lokal**; impor dataset wilayah ke DB lokal juga selesai (6.612 baris).
> Backfill `region_id`/`NOT NULL` belum (Q-012); runtime MariaDB 10.11.19 sudah diuji lokal,
> staging/produksi belum (Q-014). F7 tidak dinyatakan sebagai bukti bahwa data wilayah sudah terisi di server
> produksi.

### F8 — Lanjutan (Post-MVP)
**Dependensi:** F7
**FR:** 012, 022, 072, 082, 091, 171, 183
**Catatan:** **FR-183** (`/statistik`, statistik agregat publik) berlabel **FASE-2** di PRD — berada di
F8, **di luar** MVP F7 (Tahap 86).

- [x] Ekspor CSV + audit ekspor (FR-171). Excel/PDF tidak ditambahkan tanpa persetujuan dependency.
- [x] Sub-peran admin + pembatasan wilayah (FR-012) — `admin_wilayah`, default **nonaktif** (PROVISIONAL, Q-005).
- [x] Statistik agregat publik (FR-183) — hanya koperasi published + approved.
- [x] Versi dokumen & arsip (FR-072) — versi lama disimpan, ditandai `superseded_at`.
- [x] Perhitungan kesehatan otomatis (FR-082) + checklist kepatuhan (FR-091) — formula/checklist
  **PROVISIONAL**, dapat diubah lewat `config/koperasi.php`; bukan formula resmi dinas (Q-007).
- [x] Peringatan izin mendekati kedaluwarsa (FR-022) — ambang hari konfigurabel, default 30.

> **STATUS FINAL F8 (26 Oktober 2026): SELESAI secara lokal — implementasi & pengujian.**
> FR-012 (peran `admin_wilayah`, `users.region_id`, Policy/middleware, default nonaktif),
> FR-022, FR-072 (versi dokumen + retensi berkas), FR-082 (5 komponen skor berbobot),
> FR-091 (checklist kepatuhan), FR-171 (ekspor CSV + audit), dan FR-183 (`/statistik` agregat)
> sudah tersedia. Formula/ambang/pembatasan wilayah bersifat **PROVISIONAL** (Q-005/Q-007/Q-010),
> bukan aturan resmi. Impor wilayah ke database lokal selesai (6.612 baris); `cooperatives.region_id`
> tetap nullable karena audit menemukan koperasi tanpa wilayah. Verifikasi produksi/MariaDB 10.11
> tetap **Q-014** dan belum dilakukan.

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
8. Backup DB harian + uji restore berkala dengan `scripts/db-backup-verify.sh`; simpan checksum dan
   catat hasil restore staging.

> Detail infrastruktur dinas **[NEEDS CONFIRMATION]** (Q-014). Jangan mengasumsikan server/domain
> dinas; CI repositori memakai GitHub Actions, sedangkan target deployment produksi tetap terbuka.

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

---

## 11. Pengerasan Produksi (di luar fase F0-F8)

**Status 26-31 Oktober 2026:** pengerasan kode, verifikasi database target lokal, verifikasi
backup/restore lokal, dan CI MariaDB hijau selesai; go-live tetap menunggu staging/HTTPS/SMTP
dan restore staging.

- Header keamanan global (`SecurityHeaders`): HSTS hanya HTTPS, `X-Frame-Options`, `nosniff`,
  `Referrer-Policy`, `Permissions-Policy`, COOP, dan CSP konfigurabel. CSP produksi memakai
  `script-src 'self'`; inline `onsubmit` diganti `data-confirm` + listener JavaScript.
- `php artisan app:production-check` (read-only, `--json`, `--skip-database`) memeriksa konfigurasi
  produksi, header, storage, aset, DB/versi, migrasi, wilayah, dan admin.
- Suite penuh lulus pada SQLite, MariaDB 10.4.32, dan MariaDB **10.11.19 portabel** (database uji
  terpisah). Tes FK dibuat sadar driver; simulasi skema parsial menangani `errno 1553`.
- `phpunit.mariadb.xml.example` disediakan untuk uji MySQL/MariaDB; file salinan
  `phpunit.mariadb.xml` di-.gitignore.
- `scripts/db-backup-verify.sh` membuat dump `.sql.gz`, checksum SHA-256, restore ke database
  scratch, membandingkan daftar tabel/jumlah baris, lalu menghapus scratch. Verifikasi lokal
  26 Oktober 2026: 26 tabel, jumlah baris sama (MariaDB 10.4.32).
- `.github/workflows/ci.yml` menjalankan suite SQLite + MariaDB 10.11, build aset, Pint, dan
  `composer audit` pada push/PR dengan matriks PHP 8.3/8.4/8.5. Run GitHub `c03011b` **sukses**;
  CI bukan staging. Dua kegagalan awal menemukan peringatan kompilasi migrasi dan XML contoh
  yang tidak valid; keduanya diperbaiki dan dijaga tes regresi. Run `8294505` menemukan
  **CVE-2026-45070**; `symfony/mime` diperbaiki ke v7.4.22 dan lock dipatok ke platform PHP 8.3.0.
  Run `1b87874` sukses pada matriks PHP 8.3/8.4/8.5.
- Batas: 10.11.19 portabel dan verifikasi backup/restore lokal bukan staging; `sql_mode` produksi,
  HTTPS/domain, SMTP, restore staging/produksi, dan uji beban belum dilakukan.
  Q-005/Q-007/Q-010/Q-012/Q-014 tetap terbuka.
