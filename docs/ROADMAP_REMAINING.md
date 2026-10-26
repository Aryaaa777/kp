# Roadmap Sisa — Fase F4 hingga F8

> Dokumen ini merangkum fase-fase implementasi yang **belum** dikerjakan setelah F3 selesai.
> Rujukan lengkap ada di `docs/IMPLEMENTATION_PLAN.md` §3.

**Status final (24 Oktober 2026):**
- ✅ **F0** — Inisialisasi (commit `e6d905e`)
- ✅ **F1** — Autentikasi & RBAC: login/logout/RBAC/anti-IDOR, **FR-003** (sesi), **FR-004** (reset password), dan **US-009** (manajemen akun) **selesai** (lihat `docs/IMPLEMENTATION_PLAN.md` §F1)
- ✅ **F2** — Data Induk Koperasi (commit `1da75bb`)
- ✅ **F3** — Perizinan & Dokumen (commit lokal `d495d62`; belum di-push)
- ✅ **F4** — Data Periodik (implementasi & pengujian selesai; diterima pemilik Tahap 56)
- ✅ **F5** — Workflow Pengajuan & Audit (implementasi & pengujian selesai; diterima pemilik Tahap 56)
- ✅ **F6** — Dashboard, Pencarian, Laporan Dasar — **SELESAI** (implementasi & pengujian; lihat status final di `docs/IMPLEMENTATION_PLAN.md` §F6). D-5 dieksekusi dengan definisi periode **PROVISIONAL**; Q-010 tetap terbuka.
- ✅ **F7** — Direktori Publik & Kontrol Publikasi — **SELESAI** (implementasi & pengujian; lihat `docs/IMPLEMENTATION_PLAN.md` §F7). Impor wilayah ke database lokal selesai 26 Oktober 2026; backfill/`NOT NULL` tetap menunggu Q-012.
- ✅ **F8** — Lanjutan (Post-MVP) — **SELESAI secara lokal** (26 Oktober 2026): FR-012, FR-022, FR-072, FR-082, FR-091, FR-171, FR-183. Formula/ambang/pembatasan wilayah tetap **PROVISIONAL**; verifikasi produksi tetap Q-014.

> **Catatan historis:** blok-blok “MENUNGGU PENINJAUAN” di bawah adalah jejak audit tahap sebelumnya.
> Status yang berlaku adalah ringkasan final di atas dan blok **STATUS FINAL** pada
> `docs/IMPLEMENTATION_PLAN.md`.

---

## F4 — Data Periodik (Aset, Anggota, Kesehatan, Tata Kelola, Pengurus)

**Dependensi:** F2  
**FR terkait:** 080, 081, 090, 100, 101, 110, 111, 120, 121  
**Estimasi:** ~6 hari

### Cakupan
- **Aset koperasi** (FR-080/081): nilai aset, kewajiban, modal per periode → migrasi `cooperative_assets` (unique per koperasi+periode).
- **Riwayat anggota** (FR-100/101): jumlah anggota aktif/pasif/keluar per periode → `member_histories`.
- **Penilaian kesehatan** (FR-090/091): aspek permodalan, likuiditas, rentabilitas, dst. → `health_assessments` (admin-only, BR-010).
- **Tata kelola** (FR-110/111): catatan rapat/keputusan/RAT → `governance_records`.
- **Pengurus** (FR-120/121): nama, jabatan, periode masa bakti → `officers`.

### Deliverables
- 5 migrasi (tabel periodik + unique constraint per periode).
- CRUD per modul dengan otorisasi: admin (semua modul), pengelola (aset/anggota/pengurus read-write, kesehatan read-only).
- Tampilan riwayat multi-periode (tanpa menimpa data lama).
- Feature test AC-PER-01/02/03 (entri ganda per periode ditolak, data lama tetap ada saat entri baru, pengelola tidak bisa ubah kesehatan).

### Pertanyaan terbuka
- **Q-010**: Periodisitas (tahunan/semesteran/bulanan)?
- **Q-007**: Formula penilaian kesehatan resmi (FR-082 perhitungan otomatis)?

### Risiko
- Perubahan aturan periodisitas → perlu migrasi unique constraint.
- Formula kesehatan belum final → input manual dulu (F8 nanti otomatis).

---

## F5 — Workflow Pengajuan & Verifikasi + Audit

**Dependensi:** F3, F4  
**FR terkait:** 140–143, 150, 151  
**Estimasi:** ~7 hari

### Cakupan
- **Pengajuan perubahan** (FR-140): pengelola mengajukan perubahan data resmi (profil koperasi, izin, data periodik) → tabel `submissions` (status: `draft`/`submitted`/`under_review`/`needs_revision`/`approved`/`rejected`/`archived` — sesuai PRD §12.2).
- **Item pengajuan** (FR-141): `submission_items` menyimpan perubahan per field (nilai lama → nilai baru).
- **Antrian verifikasi** (FR-142): admin melihat antrian, aksi setujui/perbaikan/tolak dengan alasan wajib → `submission_reviews`.
- **Penerapan perubahan** (FR-143): `SubmissionService` dalam **DB transaction** (update data resmi, catat nilai lama, rekam audit).
- **Audit trail** (FR-150/151): `activity_logs` mencatat aksi sensitif (create/update/delete/verify/approve) dengan actor, target, nilai lama/baru, IP, timestamp.
- **Notifikasi** (FR-201): status pengajuan → notifikasi in-app (tabel `notifications` Laravel).

> **Status implementasi (16 Oktober 2026): SELESAI (menunggu review).** 5 migrasi
> (`submissions`, `submission_items`, `submission_reviews`, `activity_logs`, `notifications`)
> **belum dijalankan** pada DB pengembangan. Verifikasi: 262 tes / 866 asersi PASS (F5: 49 tes),
> `pint --test` PASS, `npm run build` PASS, `composer audit` bersih.
> **Perbaikan audit (TAHAP 11):** arsip = status `archived` + soft delete; konkurensi via
> `lockForUpdate()` + conditional update; whitelist disaring ulang di handler. Rincian: `docs/DECISIONS.md` §12.
> **Provisional:** Q-004 (verifikasi satu tingkat), Q-006 (whitelist modul — saat ini hanya
> `koperasi_induk`), Q-011 (notifikasi in-app). Nama status resmi memakai `needs_revision`
> (bukan `revision_requested`) sesuai PRD §12.2.
>
> **Diperbarui Tahap 56:** F4/F5 **diterima** pemilik (DoR F6 terpenuhi, keputusan F6-1); kelima migrasi
> F5 **sudah dijalankan** pada `koperasi_jabar` (Tahap 53 → 25 tabel / 22 migrasi / 28 FK / batch 6);
> suite terkini (Tahap 54): 280 tes / 902 asersi PASS. Commit tetap lokal tanpa push. Rincian:
> `docs/DECISIONS.md` §15.

### Deliverables
- 5 migrasi (`submissions`, `submission_items`, `submission_reviews`, `activity_logs`, `notifications`).
- `SubmissionService` dengan state guard (mencegah transisi tidak sah, mis. `draft` → `approved` tanpa `submitted`).
- UI: form pengajuan (pengelola), antrian verifikasi (admin), riwayat pengajuan (pengelola).
- Middleware audit untuk aksi sensitif (hook via event listener).
- Feature test AC-WF-01…05 (state machine, item diff, penerapan transaksional, rollback bila gagal, audit tercatat).

### Pertanyaan terbuka
- **Q-004**: Prosedur verifikasi multi-tahap? Approver tunggal/berjenjang?
- **Q-006**: Field mana saja yang boleh diajukan pengelola vs admin-only?
- **Q-011**: Kanal notifikasi (in-app/email/WA)?

### Risiko
- Workflow kompleks → butuh testing menyeluruh untuk race condition + transaction rollback.
- Notifikasi email/WA → butuh konfigurasi SMTP/API eksternal (defer ke F8 bila tidak ready).

---

## F6 — Dashboard, Pencarian, Laporan Dasar

**Dependensi:** F5  
**FR terkait:** 020, 021, 030, 031, 170  
**Estimasi:** ~4 hari

> **Keputusan pemilik (Tahap 56) — cakupan terkunci; implementasi BELUM dimulai.** Rincian lengkap:
> `docs/DECISIONS.md` §15. (1) F4/F5 **diterima** → DoR F6 terpenuhi, commit tetap lokal tanpa push;
> (2) laporan hanya `status_verifikasi='approved'` (AC-REP-01); (3) periode = tahun
> `cooperatives.created_at` **[PROVISIONAL]**; (4) dimensi wilayah **masuk** F6, `region_id` NULL →
> "Belum ditetapkan", **tanpa seeding** DB dev (tes memakai factory); (5) pencarian = pelengkap
> **filter wilayah** pada daftar koperasi, **bukan** pencarian global baru; (6) **tanpa** widget izin
> ≤30 hari — hanya **izin yang sudah kedaluwarsa** (FR-050). **FR-022** (FASE-2) & **FR-171** (ekspor,
> FASE-2) **di luar** cakupan. **Tanpa migrasi baru** (kecuali pengecualian D-1 Tahap 63 — §17.1). Label FR di
> bawah mengikuti **PRD** (sumber kebenaran); pelabelan FR pada versi dokumen ini sebelumnya keliru
> (lihat `docs/DECISIONS.md` §15.1).
>
> **Implementasi (Tahap 57) — selesai & teruji; MENUNGGU PENINJAUAN (bukan "selesai", belum di-commit).**
> `php artisan test` **314 tes / 1.084 asersi PASS** (baseline 280/902 + 34 tes/182 asersi F6),
> `pint --test` PASS, `npm run build` PASS, `git diff --check` bersih, `migrate:status` 22 migrasi `Ran`
> tanpa pending. **Tanpa migrasi/DDL/DML**; tidak ada data DB dev yang diubah (pemeriksaan MariaDB = baca-saja).
> Query agregat diverifikasi juga pada **MariaDB 10.4.32** dan menemukan satu cacat **portabilitas** nyata:
> MySQL `ONLY_FULL_GROUP_BY` menolak `GROUP BY COALESCE(regions.name, ?)` → diperbaiki menjadi
> `GROUP BY regions.name` (bukti: error 1055 sebelum, hasil benar sesudah).
> **Ditinjau ulang Tahap 66 (R-4):** `GROUP BY regions.name` menggabungkan wilayah bernama sama (nama
> **tidak** unik) dan membuat tautan daftar tidak cocok dengan angka bucket → kini
> `GROUP BY cooperatives.region_id` + label `MAX(regions.name)` (§19.1).
> **FR-031 — penyimpangan tercatat:** alasan & tautan perbaikan tampil, tetapi frasa AC PRD "revisi membuat
> pengajuan **terpisah**" **tidak** diterapkan karena workflow F5 yang **sudah diterima** (F5-3 / PRD §12.2)
> memakai record yang sama (`needs_revision → submitted`); dilaporkan ke pemilik, tidak diubah sendiri.
>
> **Tindak lanjut audit (Tahap 58/59) — F6 tetap MENUNGGU PENINJAUAN (bukan "selesai").** Rincian:
> `docs/DECISIONS.md` §16. Keputusan pemilik yang masih tertunda: **(D-1)** FR-031/FR-142 — terima deviasi
> (rekomendasi) atau bangun pengajuan/versi terpisah (`parent_submission_id` + migrasi baru + harmonisasi
> PRD §12.2/§12.5); **(D-2)** FR-030 — rumus bobot setara 7 modul tetap `[ASUMSI]`; **(D-3)** FR-020 (filter
> dashboard) & **(D-5)** FR-160 (filter **periode** daftar koperasi) **belum diputuskan**.
> **Catatan Tahap 60:** **(D-4)** filter **FR-021 (umur pengajuan) sudah diimplementasikan** dengan dasar
> `updated_at` (ambang angka hari dari admin, `[ASUMSI]`; `docs/DECISIONS.md` §16.5). Keputusan pemilik yang
> tersisa: **D-1, D-2, D-3, D-5** (D-4 sudah dijawab); F6 tetap **MENUNGGU PENINJAUAN**.
>
> **Eksekusi keputusan (Tahap 63) — D-1/D-2/D-3 diimplementasikan, D-5 ditunda; F6 TETAP MENUNGGU PENINJAUAN.**
> Rincian: `docs/DECISIONS.md` §17. **(D-1)** versioning pengajuan `parent_submission_id` (migrasi **baru**
> `2026_10_19_000001_*`, `Pending` di DB dev) + harmonisasi PRD §12.2/§12.5 & FLOW-PGL-04; **(D-2)** kelengkapan
> FR-030 per field wewenang pengelola (modul admin dikeluarkan dari persentase); **(D-3)** kontrol filter
> wilayah/jenis/status/periode pada dashboard admin (`CooperativeFilters` bersama dengan laporan); **(D-5)**
> filter periode daftar koperasi **ditunda** — Q-010 belum dijawab & definisi periode masih `[SEMENTARA]`,
> jadi tidak dikarang (hambatan §17.4). Verifikasi: `php artisan test` **341 tes / 1.233 asersi PASS**
> (SQLite `:memory:`; **bukan** bukti kompatibilitas MariaDB), `pint --test` PASS, `npm run build` PASS,
> `git diff --check` bersih.
>
> **Tindak lanjut audit (Tahap 65) — F6 TETAP MENUNGGU PENINJAUAN.** Rincian: `docs/DECISIONS.md` §18.
> (1) **T-1** serialisasi pembuatan pengajuan per koperasi (kunci baris koperasi + cek konflik di dalam
> transaksi; pengamanan **tingkat aplikasi**, bukan constraint DB); (2) **S-1** kartu "Butuh Perbaikan"
> memakai definisi actionable yang sama dengan daftar; (3) **S-2** `status_verifikasi` daftar koperasi
> dinormalisasi via `CooperativeFilters::normalizeStatus()` (nilai tidak valid diabaikan); (4) koreksi
> dokumentasi R-1…R-3 (angka asersi, kontradiksi FR-160, catatan FR-030 per field vs per modul).
> Verifikasi: `php artisan test` **352 tes / 1.287 asersi PASS** (SQLite saja). **FR-160 tetap Sebagian**
> (filter periode daftar koperasi **belum** diimplementasikan — D-5, §17.4); migrasi `2026_10_19_000001_*`
> **masih `Pending`**; R-4 (tautan wilayah by name) **belum** dikerjakan pada Tahap 65.
>
> **Tindak lanjut audit (Tahap 66) — F6 TETAP MENUNGGU PENINJAUAN.** Rincian: `docs/DECISIONS.md` §19.
> (1) **R-4 diselesaikan**: bucket wilayah dashboard & laporan kini dikelompokkan per **`region_id`**
> stabil (bukan `regions.name` yang tidak unik); tautan "Daftar" memakai `region_id` (NULL → `none`),
> sehingga angka bucket = hasil daftar (FR-020 AC). (2) **Audit source** migrasi `2026_10_19_000001_*`
> (tipe kolom, indeks, self-FK `nullOnDelete`, urutan `up()`/`down()`) — pemeriksaan source, **bukan**
> bukti runtime. (3) **Rencana verifikasi MariaDB** disusun (belum dijalankan; butuh izin eksplisit).
> Verifikasi: `php artisan test` **355 tes / 1.305 asersi PASS** (SQLite saja). **FR-160 tetap Sebagian**;
> migrasi **masih `Pending`**; **Q-006/Q-010/Q-012 tetap terbuka** (tidak ada yang ditandai disetujui).
>
> **Tindak lanjut audit (Tahap 67) — F6 TETAP MENUNGGU PENINJAUAN.** Rincian & matriks blocker:
> `docs/DECISIONS.md` §20. (1) **Audit regresi R-4**: pengelompokan/tautan terbukti memakai `region_id`,
> bucket `NULL` terpisah, wilayah bernama sama tidak digabung, JOIN tidak menggandakan baris; **tidak
> ditemukan cacat**, +1 tes regresi (Σ bucket = total, dengan & tanpa filter). (2) **Audit source migrasi**:
> tipe/indeks/self-FK benar; **dua temuan** — rasional SQLite di komentar §19.2 tidak akurat (§20.2) dan
> `up()` **tidak idempoten pasca-gagal sebagian** (FK bisa hilang senyap, §20.3) — keduanya **belum**
> diperbaiki (butuh keputusan pemilik). (3) **Audit T-1**: kedua jalur pembuatan mengunci koperasi lebih
> dulu dalam transaksi, tidak ada jalur alternatif; **efektivitas lock belum terbukti** (SQLite no-op).
> Verifikasi: `php artisan test` **356 tes / 1.312 asersi PASS** (SQLite saja), `pint --test` PASS,
> `npm run build` PASS, `git diff --check` bersih, `migrate:status` **22 Ran + 1 Pending**.
> **Tidak ada** item F6 yang ditutup pada Tahap 67.
>
> **Tindak lanjut audit (Tahap 68) — F6 TETAP MENUNGGU PENINJAUAN.** Rincian: `docs/DECISIONS.md` §21.
> **Idempotensi `up()` diperbaiki**: kolom, indeks, dan self-FK kini diperiksa **terpisah** — eksekusi
> ulang setelah gagal sebagian akan **melengkapi** komponen yang hilang (tidak lagi tercatat `Ran` tanpa
> FK). FK ekuivalen bernama lain tetap diterima; FK menyimpang → migrasi **berhenti** dengan diagnostik.
> `down()` melepas FK memakai **nama constraint yang dilaporkan driver** (bukan asumsi nama konvensional)
> dan melepas indeks sebelum kolom; FK menyimpang tanpa nama **tidak** dihapus (gagal keras → periksa
> manual). Dokumentasi SQLite **dikoreksi**: klaim "SQLite tidak mendukung FK pada ALTER" dihapus —
> migrasi **secara eksplisit** melewati self-FK pada SQLite, sehingga **FK tidak diverifikasi** suite
> SQLite (validasi memerlukan MariaDB). Verifikasi: `php artisan test` **376 tes / 1.390 asersi PASS**
> (SQLite saja; +20 tes/78 asersi baru di `ParentSubmissionForeignKeyTest`), `pint --test` PASS,
> `npm run build` PASS, `git diff --check` bersih, `migrate:status` **22 Ran + 1 Pending**. Migrasi
> **tetap `Pending`**; **T-1, Q-006, Q-010, Q-012, R-4 tetap terbuka/Menunggu**; F6 **tetap MENUNGGU
> PENINJAUAN**.
>
> **Tindak lanjut audit (Tahap 70) — F6 TETAP MENUNGGU PENINJAUAN.** Rincian: `docs/DECISIONS.md` §21.7.
> **Kepemilikan indeks (F-69-01):** `down()` melepas indeks **hanya** bila namanya
> `submissions_parent_submission_id_index` **dan** definisinya sesuai — indeks pihak lain (unik/komposit/
> primary) **tidak pernah** dihapus; nama dipakai ulang untuk definisi lain → **berhenti** dengan
> diagnostik. **F-69-02:** `down()` didokumentasikan **destruktif** — kolom `parent_submission_id` dihapus
> sehingga tautan versi hilang permanen (baris tetap ada) → butuh persetujuan eksplisit. **F-69-03**
> dikoreksi: `DROP INDEX` dapat gagal lebih awal di MariaDB (errno 1553) — bukan satu titik gagal.
> **F-69-05:** perilaku penjagaan urutan **dipertahankan** (pola `2026_10_16_100004`, tabel induk dibuat di
> `2026_10_16_130001`). **F-69-06:** serialisasi eksekusi migrasi dicatat sebagai persyaratan operasional.
> **F-69-07/08/10:** tes diperkuat (urutan perintah Blueprint, assertion source FK, `down()` bermock, baris
> pasca-rollback, penamaan `test_sqlite_only_*`). **F-69-09:** ditandai **[TIDAK DAPAT DIVERIFIKASI]**
> (hanya satu dari enam nama tes A/B yang tercatat). **F-69-11a/b/c:** dokumentasi diselaraskan. Verifikasi:
> `php artisan test` **392 tes / 1.481 asersi PASS** (SQLite saja; +16 tes/+91 asersi), `pint --test` PASS,
> `npm run build` PASS, `git diff --check` bersih. Migrasi **tetap `Pending`** (tidak dijalankan);
> **T-1, Q-006, Q-010, Q-012, R-4 tetap terbuka/Menunggu**; **tidak ada** item F6 yang ditutup.

### Cakupan
- **Dashboard admin** (FR-020): statistik koperasi per status/**wilayah**, antrian verifikasi (FR-021), daftar **izin sudah kedaluwarsa** (FR-050; FR-022 di luar cakupan).
- **Dashboard pengelola** (FR-030): kelengkapan data (%), status pengajuan terbaru, **daftar permintaan perbaikan** (FR-031; status `needs_revision`/`rejected`).
- **Filter wilayah** pada daftar koperasi yang sudah ada (FR-160) — tanpa halaman pencarian global baru.
- **Laporan/rekapitulasi** (FR-170): per wilayah/jenis/status/periode, hanya data `approved`, view-only (ekspor FR-171 di F8).

### Deliverables
- Blade: `admin/dashboard`, `pengelola/dashboard`, `admin/laporan/index`.
- Query agregat **read-only** memakai indeks yang sudah ada. **Pengecualian Tahap 63:** satu migrasi **baru**
  `parent_submission_id` untuk D-1 (§17.1); **tidak ada** migrasi lama/`Ran` yang diubah. Berkas migrasi
  baru itu masih **`Pending`** dan isinya disempurnakan pada Tahap 68 & 70 — penyempurnaan **tidak** berarti
  migrasi sudah dijalankan (§21.7, F-69-11b).
- Feature test AC-REP-01 (laporan hanya data approved, pengelola tidak lihat data koperasi lain).

### Pertanyaan terbuka
- **Q-022**: Kebutuhan laporan pimpinan/eksekutif spesifik?
- **Q-010**: definisi resmi "periode" (F6 memakai `created_at` sebagai asumsi sementara — F6-3).
- **Q-012**: sumber resmi referensi wilayah **sudah tersedia** (Kepmendagri 300.2.2-2138/2025) dan
  diintegrasikan pada Tahap 107 (CSV 6.612 baris, importer, audit, form berjenjang). Sisa pekerjaan:
  impor ke database persisten, audit/backfill `region_id`, dan migrasi `NOT NULL` bila sudah tuntas.
  Lihat `docs/REGION_DATA_SOURCES.md` dan `docs/DECISIONS.md` §30.
  **Catatan:** aplikasi **tidak** mengasumsikan `regions.name` unik (hanya `regions.code` yang
  `unique`). Agregasi & tautan wilayah memakai `region_id`; dashboard/laporan mengelompokkan ke
  kabupaten/kota lewat `RegencyGrouping`.

---

## F7 — Direktori Publik & Kontrol Publikasi

**Dependensi:** F5  
**FR terkait:** 180–182, 190, 191  
**Estimasi:** ~3 hari

> **Status (Tahap 84): DIMULAI — melalui pengecualian urutan fase yang diputuskan pemilik.**
> F7 boleh dimulai sebelum F6 ditutup karena F7 **tidak** memakai `parent_submission_id` maupun
> alur laporan F6 (pemetaan Tahap 83). Pengecualian ini **hanya** mengubah gerbang dimulainya F7:
> F6 **tetap MENUNGGU PENINJAUAN**, verifikasi MariaDB F6 tetap tertunda, migrasi F6 tetap
> `Pending`, `commit`/`push` tetap belum diotorisasi, F8 belum dimulai. Rincian: `docs/DECISIONS.md` §22.
> **Path publik mengikuti PRD §9 (`/koperasi`, `/koperasi/{slug}`)** — `/public/...` pada dokumen
> ini adalah konflik penamaan yang dicatat sebagai isu dokumentasi terpisah (§22.4).
>
> **Progres (Tahap 85): FR-190 selesai.** Admin dinas dapat **menerbitkan** & **menarik** publikasi
> (`POST /admin/koperasi/{cooperative}/publikasikan` dan `/tarik-publikasi`; middleware
> `role:admin_dinas` + `CooperativePolicy::publish`). `published_at`/`published_by` diisi sistem,
> status verifikasi tidak diubah. **FR-191 kini selesai** dengan UI admin dan tabel `settings`
> (migrasi aditif, belum dijalankan di DB persisten). **F7 selesai** untuk cakupan implementasi &
> pengujian. Rincian: `docs/DECISIONS.md` §23, §28, §32.

### Cakupan
- **Direktori publik** (FR-180): halaman `/koperasi` (tanpa auth) — hanya `is_published=true` + status resmi.
- **Pencarian & filter publik** (FR-181): nama/jenis/kabupaten-kota, dengan pagination.
- **Profil publik** (FR-182): `/koperasi/{slug}` — hanya field yang diizinkan (BR-021).
- **Kontrol publikasi** (FR-190): admin dinas menerbitkan/menarik `is_published` per koperasi (POST).
- **Pengaturan field publik (FR-191)**: UI `/admin/pengaturan/publik`, penyimpanan `settings`, dibatasi
  allowlist Q-008.
- **Masking sensitif** (NFR-SEC-015): email/telepon dimasking (`email: ab***@example.com`).

### Deliverables
- [x] Route publik middleware **`web` saja**: `publik.direktori` (`/koperasi`), `publik.koperasi` (`/koperasi/{slug}`).
- [x] Blade: `publik/direktori`, `publik/profil` (+ `layouts/public`).
- [x] Migration `settings` (aditif) + UI pengaturan; **belum dijalankan** pada DB persisten dan tidak
      ada seeder kebijakan default yang berisi data karangan. Default aman berasal dari config.
- [x] Feature test **AC-PUB-01/02/03** menurut **PRD §10.6**: koperasi belum dipublikasikan tidak muncul
      & tidak dapat dijangkau (FR-180/190); filter publik (nama/jenis/wilayah) relevan (FR-181);
      field sensitif tidak tampil (FR-191, NFR-SEC-015). Lihat `tests/Feature/Publik/DirektoriPublikTest.php`,
      `tests/Feature/Admin/CooperativePublicationTest.php`, `tests/Feature/Publik/PublicSurfaceExposureTest.php`.
- [x] Pengerasan benturan slug publik vs segmen internal (**Tahap 88**, Opsi 1 + 1b): slug **baru** tidak
      lagi memakai segmen internal (`Cooperative::RESERVED_SLUGS`) dan route publik menolaknya lewat
      constraint. Slug **lama** yang bertabrakan **tidak** diubah datanya (masih ditangani route internal;
      perbaikan tuntas = Opsi 2/3, butuh keputusan pemilik). Rincian: `docs/DECISIONS.md` §26.
  - [x] **Audit Tahap 95 & keputusan Tahap 96:** Opsi 1 (status quo) **ditetapkan sebagai keputusan sementara**
      proyek — aman, teruji, tanpa blocker teknis baru. Opsi 2 (ubah data slug lama) & Opsi 3 (namespace
      URL) tetap terbuka untuk masa depan jika blocker terselesaikan & persetujuan diperoleh. Rincian:
      `docs/DECISIONS.md` §27.

> **FR-183** (`/statistik`) berlabel **FASE-2** di PRD → **bukan** bagian MVP F7 (dipetakan ke F8).

### Pertanyaan terbuka
- **Q-008**: Aturan publikasi field spesifik?

---

## F8 — Lanjutan (Post-MVP)

**Dependensi:** F7  
**FR terkait:** 012, 022, 072, 082, 091, 171, 183  
**Estimasi:** ~6 hari (opsional/sesuai kebutuhan)

### Status implementasi (26 Oktober 2026)
- ✅ **Ekspor laporan** (FR-171): CSV stream + audit ekspor. Excel/PDF **tidak** ditambahkan tanpa
  persetujuan dependency.
- ✅ **Sub-peran admin** (FR-012): peran `admin_wilayah` + `users.region_id` + Policy/middleware.
  Default **nonaktif**; pembagian wilayah resmi menunggu Q-005.
- ✅ **Statistik agregat publik** (FR-183): `/statistik`, hanya data published + approved.
- ✅ **Versi dokumen** (FR-072): `previous_version_id` + `superseded_at`; berkas lama dipertahankan.
- ✅ **Perhitungan kesehatan & kepatuhan** (FR-082/091): formula/checklist **PROVISIONAL** di
  `config/koperasi.php`; bukan formula resmi (Q-007).
- ✅ **Peringatan izin mendekati kedaluwarsa** (FR-022): ambang hari konfigurabel, default 30.

### Deliverables
- Ekspor CSV tanpa library tambahan (streamed response).
- Migrasi aditif: `users.region_id`, komponen kesehatan, kepatuhan tata kelola, versi dokumen.
- Halaman publik `/statistik` (agregat, tanpa data personal).
- Feature test ekspor, pembatasan wilayah, versi dokumen, kesehatan, kepatuhan, statistik publik.

### Pertanyaan terbuka
- **Q-005**: Pembagian kewenangan wilayah admin (default nonaktif).
- **Q-007**: Formula kesehatan & kriteria kepatuhan resmi (implementasi PROVISIONAL).
- **Q-015**: Format ekspor resmi (kolom/header).
- **Q-020**: Integrasi sistem pemerintah lain.
- **Q-012**: Backfill `region_id` & `NOT NULL` (audit belum bersih).

---

## Catatan Eksekusi

1. **Urutan wajib:** F4 → F5 → F6 → F7 → F8.
   - **Pengecualian tercatat (Tahap 84):** pemilik mengizinkan **F7 dimulai sebelum F6 ditutup**
     (`docs/DECISIONS.md` §22). Urutan fase **tidak** diubah secara global dan F6 **tidak**
     dinyatakan selesai; pengecualian hanya berlaku untuk gerbang dimulainya F7.
2. **Review di akhir tiap fase** — jangan lanjut sebelum fase sebelumnya disetujui.
3. **Jika waktu mepet** (skenario PRD §19.2):
   - **MVP minimum:** F0–F3 + F5 (workflow) + F6 (dashboard admin).
   - **Defer:** F4 (input periodik manual dulu), F7 (publik belakangan), F8 (post-launch).
4. **Asumsi yang belum terkonfirmasi** (lihat §21 PRD) harus ditandai `[NEEDS CONFIRMATION]` di kode/docs.

---

**Perkiraan total sisa:**  
F4 (6d) + F5 (7d) + F6 (4d) + F7 (3d) = **20 hari kerja** untuk MVP lengkap (F0–F7).  
F8 opsional tambahan ~6 hari.

Rujuk `docs/IMPLEMENTATION_PLAN.md` untuk detail task & DoD per fase.

---

## Pengerasan produksi (26-27 Oktober 2026)

- Header keamanan global (HSTS, CSP, `X-Frame-Options`, `nosniff`, `Referrer-Policy`,
  `Permissions-Policy`, COOP) dan `app:production-check` sudah ditambahkan.
- Suite penuh lulus pada SQLite dan MariaDB 10.11.19 portabel lokal (database uji terpisah);
  tes FK dibuat sadar driver.
- `scripts/db-backup-verify.sh` dan verifikasi restore lokal 26 tabel (jumlah baris sama) sudah
  tersedia; ini bukan pengganti restore staging/produksi.
- `.github/workflows/ci.yml` menjalankan suite SQLite + MariaDB 10.11, build, Pint, dan audit;
  CI bukan pengganti staging.
- **Belum go-live:** staging resmi, HTTPS/domain, SMTP, restore staging/produksi, `sql_mode`
  produksi, dan uji beban masih menjadi blocker eksternal. Lihat `docs/DECISIONS.md` 34-36 dan
  `docs/HANDOVER.md` 8.
