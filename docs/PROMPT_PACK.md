# Prompt Pack — Template Instruksi untuk AI Coding Agent

> Dokumen ini menyediakan template prompt untuk AI agent (OpenCode/Cursor/Claude Code) yang bekerja
> pada fase F4–F8. Setiap prompt **harus** disesuaikan dengan keputusan/konfirmasi aktual dari dinas
> sebelum dieksekusi.

**Prasyarat:**
- Agent sudah membaca `AGENTS.md`/`CLAUDE.md` (panduan kerja).
- Dokumen perencanaan (`PRD.md`, `DATA_DICTIONARY.md`, `USER_FLOWS.md`, `IMPLEMENTATION_PLAN.md`) sudah tersedia.
- Fase sebelumnya sudah selesai & di-review.

---

## Prompt F4 — Data Periodik

```
Implementasikan **F4 — Data Periodik** sesuai docs/IMPLEMENTATION_PLAN.md §3 (F4) dan docs/ROADMAP_REMAINING.md.

**Cakupan:**
- FR-080/081 (aset), FR-100/101 (anggota), FR-090/091 (kesehatan), FR-110/111 (tata kelola), FR-120/121 (pengurus).
- 5 migrasi: `cooperative_assets`, `member_histories`, `health_assessments`, `governance_records`, `officers`.
- Unique constraint per (`cooperative_id`, `periode_*`) — format periode: [TUNGGU KONFIRMASI Q-010 — gunakan `tahun` INT sementara].
- CRUD per modul: admin (semua), pengelola (aset/anggota/pengurus RW, kesehatan read-only — BR-010).
- Tampilan riwayat multi-periode tanpa menimpa data lama.

**Otorisasi:**
- Middleware `auth`+`active`+`role` per route.
- Policy: admin akses penuh; pengelola hanya koperasinya; kesehatan read-only untuk pengelola.
- Scoped binding untuk isolasi antar koperasi.

**Validasi:**
- Periode wajib, unique per koperasi+periode (AC-PER-01).
- Nilai numerik >= 0 (aset, anggota).
- Tanggal rapat <= hari ini (governance).

**Testing:**
- AC-PER-01: entri ganda per periode ditolak.
- AC-PER-02: data periode lama tetap ada saat entri baru.
- AC-PER-03: pengelola tidak bisa ubah kesehatan (403).
- Isolasi: pengelola A tidak lihat data koperasi B.

**Catatan:**
- Formula kesehatan otomatis (FR-082) **defer ke F8** — input manual dulu.
- Jangan membuat data dummy/seeder (kecuali opt-in demo).
- Model + Factory + Feature test wajib.
- Update docs (DATA_DICTIONARY footnote F4, DECISIONS bila ada asumsi baru).

Laporkan file yang diubah/dibuat + hasil `php artisan test` setelah selesai.
```

---

## Prompt F5 — Workflow Pengajuan & Audit

```
Implementasikan **F5 — Workflow Pengajuan & Verifikasi + Audit** sesuai docs/IMPLEMENTATION_PLAN.md §3 (F5).

**Cakupan:**
- FR-140–143 (pengajuan/verifikasi), FR-150/151 (audit), FR-201 (notifikasi in-app).
- 4 migrasi: `submissions`, `submission_items` (polimorfik: model+id+field+old/new value), `submission_reviews`, `activity_logs`.
- `SubmissionService` dengan state machine (`draft`→`submitted`→`under_review`→`approved`/`rejected`/`revision_requested`).
- Penerapan perubahan dalam **DB transaction** (update data resmi + catat nilai lama + audit).
- Middleware/Event listener untuk audit trail otomatis pada aksi sensitif.

**State Machine (guard):**
- `draft` → `submitted` (pengelola, validasi lengkap).
- `submitted` → `under_review` (admin claim).
- `under_review` → `approved`/`rejected`/`revision_requested` (admin, alasan wajib bila tolak/revisi).
- `revision_requested` → `submitted` (pengelola perbaiki).
- Transisi lain throw exception.

**UI:**
- Pengelola: form pengajuan (pilih field yang diubah — [TUNGGU Q-006 untuk whitelist]), riwayat status.
- Admin: antrian (`submitted`+`under_review`), aksi setujui/tolak/revisi dengan form alasan.

**Otorisasi:**
- Pengelola hanya koperasinya; admin semua.
- Pengelola tidak bisa langsung `approve` (hanya submit).

**Testing:**
- AC-WF-01: state machine (transisi tidak sah → exception).
- AC-WF-02: item diff terekam (old/new value).
- AC-WF-03: penerapan transaksional (rollback bila gagal).
- AC-WF-04: audit tercatat (actor, target, timestamp).
- AC-WF-05: pengelola tidak bisa approve sendiri.

**Catatan:**
- Notifikasi email/WA **defer** — gunakan notifikasi in-app dulu (Laravel `notifications` table).
- Jangan auto-approve — selalu butuh review admin (BR-014).
- Update docs (DATA_DICTIONARY §F5, DECISIONS workflow matrix).

Laporkan state machine implementation + hasil test + migration batch.
```

---

## Prompt F6 — Dashboard, Pencarian, Laporan Dasar

```
Implementasikan **F6 — Dashboard, Pencarian, Laporan Dasar** sesuai docs/IMPLEMENTATION_PLAN.md §3 (F6)
dan keputusan pemilik pada **docs/DECISIONS.md §15** (Tahap 56). **Implementasi sudah ada (Tahap 57) tetapi
berstatus MENUNGGU PENINJAUAN — belum "selesai" dan belum di-commit.** Blocker tertunda (FR-031/FR-142
"pengajuan terpisah", asumsi FR-030, filter FR-020/FR-160 — FR-021 umur **sudah** diimplementasikan Tahap 60)
tercatat di **docs/DECISIONS.md §16**; **seluruhnya sudah diputuskan & dieksekusi pada Tahap 63**
(**D-1/D-2/D-3 diimplementasikan; D-5 ditunda dengan hambatan tercatat** — lihat **docs/DECISIONS.md §17**).
Verifikasi Tahap 63: `php artisan test` 341 tes / 1.233 asersi PASS (SQLite; **bukan** bukti MariaDB).
**Tindak lanjut audit (Tahap 65, docs/DECISIONS.md §18):** **T-1** serialisasi pembuatan pengajuan per
koperasi (kunci baris koperasi + cek konflik dalam satu transaksi — pengamanan tingkat **aplikasi**, bukan
constraint DB), **S-1** kartu "Butuh Perbaikan" = definisi actionable yang sama dengan daftar, **S-2**
`status_verifikasi` daftar koperasi dinormalisasi via `CooperativeFilters::normalizeStatus()`. Verifikasi
Tahap 65: `php artisan test` 352 tes / 1.287 asersi PASS (SQLite saja). **F6 tetap MENUNGGU PENINJAUAN;
FR-160 tetap Sebagian** (filter periode daftar koperasi belum ada — D-5/Q-010).
**Tindak lanjut audit (Tahap 66, docs/DECISIONS.md §19):** **R-4 diperbaiki** — bucket wilayah dashboard &
laporan dikelompokkan per **`region_id`** stabil (bukan `regions.name` yang tidak unik); tautan "Daftar"
memakai `region_id` (NULL → `none`) sehingga angka bucket = hasil daftar. Audit **source** migrasi
`2026_10_19_000001_*` + **rencana verifikasi MariaDB** disusun (belum dijalankan; butuh izin eksplisit).
Verifikasi Tahap 66: `php artisan test` 355 tes / 1.305 asersi PASS (SQLite saja). Migrasi **masih
`Pending`**; **Q-006/Q-010/Q-012 tetap terbuka**; F6 **tetap MENUNGGU PENINJAUAN**.
**Tindak lanjut audit (Tahap 67, docs/DECISIONS.md §20):** audit kesiapan verifikasi MariaDB (read-only) +
**matriks blocker F6 (§20.7)**. Regresi R-4 **tidak ditemukan** (+1 tes: Σ bucket = total); audit source
migrasi menemukan **rasional SQLite yang tidak akurat (§20.2)** dan **celah idempotensi `up()` pasca-gagal
sebagian (§20.3)** — keduanya **belum** diperbaiki (butuh izin pemilik); audit **T-1** memastikan kedua
jalur pembuatan mengunci koperasi lebih dulu, tetapi **efektivitas lock belum terbukti** (SQLite no-op,
MariaDB belum diuji). Verifikasi Tahap 67: `php artisan test` 356 tes / 1.312 asersi PASS (SQLite saja),
`pint --test` PASS, `npm run build` PASS, `git diff --check` bersih, `migrate:status` 22 Ran + 1 Pending.
**Tidak ada** item F6 yang ditutup pada Tahap 67.
**Tindak lanjut audit (Tahap 68, docs/DECISIONS.md §21):** **idempotensi `up()` diperbaiki** — kolom,
indeks, dan self-FK diperiksa terpisah; eksekusi ulang setelah gagal sebagian kini **melengkapi** FK yang
belum ada (sebelumnya `return` di kolom → FK bisa hilang senyap). FK ekuivalen bernama lain tetap diterima;
FK menyimpang → migrasi berhenti dengan diagnostik. `down()` memakai **nama constraint yang dilaporkan
driver** & melepas indeks sebelum kolom. Dokumentasi SQLite **dikoreksi**: migrasi sengaja melewati self-FK
pada SQLite → **FK tidak divalidasi** suite SQLite (perlu MariaDB). Verifikasi Tahap 68: `php artisan test`
376 tes / 1.390 asersi PASS (SQLite saja; +20 tes/78 asersi), `pint --test` PASS, `npm run build` PASS,
`git diff --check` bersih, `migrate:status` 22 Ran + 1 Pending. Migrasi **tetap `Pending`**; T-1/Q-006/Q-010/
Q-012/R-4 **tetap terbuka**; F6 **tetap MENUNGGU PENINJAUAN**.
**Tindak lanjut audit (Tahap 70, docs/DECISIONS.md §21.7):** tindak lanjut audit Tahap 69 —
**F-69-01** kepemilikan indeks (`classifyIndex()`: nama **dan** definisi; indeks pihak lain tidak pernah
dihapus), **F-69-02** `down()` **destruktif** terhadap tautan versi (butuh persetujuan eksplisit),
**F-69-03** `DROP INDEX` dapat gagal lebih awal di MariaDB (errno 1553), **F-69-05** penjagaan urutan
dipertahankan, **F-69-06** serialisasi migrasi = persyaratan operasional, **F-69-07/08/10** tes diperkuat,
**F-69-09** ditandai **[TIDAK DAPAT DIVERIFIKASI]**, **F-69-11a/b/c** dokumentasi diselaraskan. Verifikasi
Tahap 70: `php artisan test` 392 tes / 1.481 asersi PASS (SQLite saja), `pint --test` PASS, `npm run build`
PASS, `git diff --check` bersih. Migrasi **tetap `Pending`** (tidak dijalankan); F6 **tetap MENUNGGU
PENINJAUAN**.

**Cakupan resmi (FR-020, FR-021, FR-030, FR-031, FR-170):**
- Dashboard admin (FR-020): statistik koperasi per status/wilayah/periode dengan **kontrol filter wilayah/jenis/status/periode** (Tahap 63, D-3 — komponen bersama `CooperativeFilters`); antrian verifikasi (FR-021; count `submissions.status` = `submitted`/`under_review`).
- Izin **sudah kedaluwarsa** saja (FR-050): `cooperative_licenses.tanggal_berlaku_sampai` + `CooperativeLicense::isExpired()`. **FR-022** (izin mendekati kadaluarsa) = FASE-2 → **di luar F6**.
- Dashboard pengelola (FR-030): kelengkapan data per **field wewenang pengelola** (Tahap 63, D-2 — modul kewenangan admin dikeluarkan dari persentase), status pengajuan terbaru, daftar permintaan perbaikan (FR-031; status resmi **`needs_revision`**, bukan `revision_requested`).
- Filter **wilayah** pada daftar koperasi yang sudah ada (FR-160) — **bukan** halaman pencarian global baru. **Filter periode** daftar koperasi **ditunda** (D-5, Q-010 belum dijawab — §17.4).
- Laporan rekapitulasi (FR-170): per wilayah/jenis/status/periode, hanya data `status_verifikasi=approved`, view-only (ekspor FR-171 di F8).

**Definisi terkunci (docs/DECISIONS.md §15):**
- "Data terverifikasi" = `status_verifikasi = 'approved'` (F6-2).
- Periode = tahun `cooperatives.created_at` **[SEMENTARA]** (F6-3) — jangan dicampur dengan `period_year` data periodik.
- Wilayah: `region_id` NULL → "Belum ditetapkan"; **tanpa seeding** DB dev; pengujian memakai `RegionFactory` (F6-4).
- **Tanpa migrasi baru** — semua objek & indeks sudah ada (§15.3), **kecuali** pengecualian Tahap 63 (D-1):
  migrasi **baru** `2026_10_19_000001_add_parent_submission_id_to_submissions_table.php` (§17.1); migrasi lama tidak diubah.
- Revisi pengajuan `needs_revision` membuat **versi pengajuan baru** (`parent_submission_id`), bukan menyunting record yang sama (D-1, §17.1).

**UI:**
- `admin/dashboard`: card statistik + tabel antrian + tabel izin kedaluwarsa.
- `pengelola/dashboard`: progress bar kelengkapan + daftar permintaan perbaikan.
- `admin/laporan/index`: form filter + tabel hasil.

**Otorisasi:** dashboard/laporan admin di bawah `role:admin_dinas`; dashboard pengelola di bawah `role:pengelola_koperasi`; agregasi sisi pengelola difilter `cooperative_id` milik akun (anti-IDOR).

**Optimisasi:**
- Query memakai indeks yang sudah ada (wilayah, status, jenis) — tanpa migrasi baru.
- Cache count statistik bila perlu (5 menit).

**Testing:**
- AC-REP-01: laporan hanya data approved (data `draft`/`pending` tidak muncul).
- Dashboard admin hitung antrian benar.
- Pengelola tidak lihat statistik koperasi lain.

**Catatan:**
- Ekspor (CSV/Excel) **defer ke F8** (FR-171 = FASE-2).
- Chart JS opsional — bisa plain table dulu.
- Update docs bila ada query kompleks baru.

Laporkan card/tabel yang dibuat + hasil test.
```

---

## Prompt F7 — Direktori Publik

```
Implementasikan **F7 — Direktori Publik & Kontrol Publikasi** sesuai docs/IMPLEMENTATION_PLAN.md §3 (F7).

**Cakupan:**
- FR-180–182 (direktori/profil publik), FR-190/191 (kontrol publikasi).
- Route publik `/koperasi` (tanpa auth), filter/pencarian; profil `/koperasi/{slug}`.
  Path mengikuti **PRD §9** (`/koperasi`, `/koperasi/{slug}`) & `TEST-08-01`; naskah lama
  `/public/...` adalah konflik penamaan — lihat catatan rekonsiliasi di bawah.
- Profil menampilkan hanya field yang diizinkan — batas maksimum **Q-008** (sudah diputuskan).
- Admin toggle `cooperatives.is_published` (default `false`) via **POST**
  `/admin/koperasi/{cooperative}/publikasikan` & `/tarik-publikasi` (bukan GET).
- Tabel `settings` (key-value) untuk kebijakan field publik global — **sudah diimplementasikan**
  sebagai migrasi aditif + UI `/admin/pengaturan/publik`; Q-008 tetap batas maksimum.
- Masking field sensitif (email/telepon) dengan `App\Support\PiiMask::email()` / `::phone()`.

**Otorisasi:**
- Route publik middleware `web` saja (no auth).
- Hanya koperasi `is_published=true` **dan** berstatus resmi (`config('koperasi.public.visible_statuses')`,
  saat ini `approved`) muncul di direktori — aturan yang sama dipakai kontrol publikasi admin.
- Data `draft`/`pending` tidak pernah publik.

**UI:**
- `publik/direktori` (route `publik.direktori`): card grid koperasi, filter wilayah/jenis.
- `publik/profil` (route `publik.koperasi`): profil (nama, jenis, wilayah, kontak termasking, website).
- Footer: "Data per [tanggal update terakhir]" — **belum diterapkan** (tanggal acuan publikasi belum
  ditetapkan dinas); halaman menampilkan "Menampilkan X dari N koperasi."

**Masking (NFR-SEC-015):**
- Email: `ab***@example.com` (2 karakter pertama + `***` + domain).
- Telepon: `0812****5678` (4 digit awal + `****` + 4 digit akhir).

**Testing (label diselaraskan dengan PRD §10.6):**
- AC-PUB-01: koperasi belum dipublikasikan tidak muncul di direktori/endpoint publik. (FR-180/190)
- AC-PUB-02: filter publik (nama/jenis/wilayah) mengembalikan hasil relevan. (FR-181)
- AC-PUB-03: field sensitif tidak tampil di halaman publik. (FR-191, NFR-SEC-015)
- Data `draft`/`pending` **tidak pernah** publik meski `is_published=true` (kelayakan = `visible_statuses`).

**Catatan:**
- Setting field publik bisa hard-coded dulu (array di config) bila Q-008 belum dikonfirmasi.
- Update docs (DECISIONS §F7 untuk asumsi field publik sementara).

Laporkan route publik + hasil test + masking helper.
```

> **Catatan rekonsiliasi (Tahap 86 — `docs/DECISIONS.md` §24; diperbarui 24 Oktober 2026).** Naskah
> prompt di atas sudah diselaraskan dengan implementasi aktual: path mengikuti PRD §9 (`/koperasi`,
> `/koperasi/{slug}`), helper bernama `App\Support\PiiMask`, allowlist memakai Q-008, dan tabel
> `settings` kini **sudah dibuat** (migrasi aditif) dengan UI admin; migrasi belum dijalankan pada DB
> persisten. Label **AC-PUB-01/02/03** mengikuti rumusan PRD §10.6.

---

## Prompt F8 — Lanjutan (sesuai prioritas dinas)

> **STATUS (26 Oktober 2026): F8 sudah diimplementasikan secara lokal.** Prompt di bawah adalah
> **arsip historis** dan **jangan dijalankan ulang**. Implementasi aktual memakai CSV tanpa paket
> Excel/PDF, `users.region_id` (bukan tabel `admin_regions`), `previous_version_id` (bukan
> `parent_document_id`), dan formula/checklist PROVISIONAL di `config/koperasi.php`. Rincian:
> `docs/DECISIONS.md` §33.

```
Implementasikan **F8 — [pilih sub-fitur]** sesuai prioritas:

**Opsi A: Ekspor Laporan (FR-171)**
- Install `maatwebsite/excel` / `barryvdh/laravel-dompdf`.
- Tambahkan tombol "Ekspor CSV/Excel/PDF" di laporan (F6).
- Audit ekspor: `export_logs` (user, tanggal, jenis, filter yang dipakai).
- Testing: file ter-generate, audit tercatat, pengelola hanya ekspor koperasinya.

**Opsi B: Sub-Peran Admin (FR-012)**
- Migration `admin_regions` (many-to-many `users`↔`regions`).
- Middleware `RestrictAdminToRegions` (bila pembatasan aktif, filter query per wilayah admin).
- UI: saat buat/edit akun admin, pilih wilayah (multi-select).
- Testing: admin wilayah A tidak lihat koperasi wilayah B.

**Opsi C: Perhitungan Kesehatan Otomatis (FR-082)**
- Service `HealthScoreCalculator` (input: aset, anggota, governance → output: skor per aspek).
- Formula dari [TUNGGU Q-007 — gunakan placeholder: (modal/aset)*100 untuk permodalan].
- Tombol "Hitung Otomatis" di form kesehatan (admin).
- Testing: skor dihitung benar, tidak overwrite manual entry.

**Opsi D: Versi Dokumen (FR-072)**
- Tambahkan kolom `documents.parent_document_id` (self-relation).
- Saat replace file, buat row baru dengan `parent_id` → versi lama tetap ada.
- UI: riwayat versi + restore versi lama.
- Testing: versi lama bisa diunduh, restore tidak hilangkan versi baru.

**Catatan umum F8 (historis):**
- Semua fitur F8 opsional; pilih sesuai waktu/prioritas dinas.
- **FR-183** (`/statistik`) sudah diimplementasikan pada F8 (26 Oktober 2026) sebagai agregat
  published + approved.
- Update docs (DECISIONS, SETUP) bila ada konfigurasi baru.
- Testing wajib untuk setiap sub-fitur.

Laporkan sub-fitur yang dipilih + hasil implementasi + test.
```

---

## Catatan Umum

1. **Sebelum eksekusi prompt**, pastikan:
   - Pertanyaan P0 terkait (Q-xxx) sudah dijawab dinas, atau asumsi sementara didokumentasikan dengan jelas di `docs/DECISIONS.md`.
   - Fase sebelumnya sudah di-review & di-commit.
   - Agent sudah membaca context (`PRD.md`, `DATA_DICTIONARY.md`, dll.).

2. **Setelah fase selesai**, agent harus:
   - Jalankan `php artisan test`, `./vendor/bin/pint --test`, `npm run build`, `git diff --check`.
   - Laporkan file yang diubah/dibuat + migration batch + hasil test.
   - **Berhenti dan tunggu review** — jangan lanjut ke fase berikutnya tanpa persetujuan.

3. **Format commit** (setelah disetujui):
   ```
   feat(Fx): <ringkasan fase dalam 1 baris>
   
   - FR-xxx: <deliverable 1>
   - FR-yyy: <deliverable 2>
   - Testing: <jumlah tes> pass, <jumlah assertions> assertions
   
   Refs: docs/IMPLEMENTATION_PLAN.md §3 (Fx)
   ```

4. **Jangan membuat asumsi besar** tanpa konfirmasi — tandai `[NEEDS CONFIRMATION]` atau `[ASUMSI]` di kode/docs dan laporkan untuk keputusan.

---

Rujuk `docs/IMPLEMENTATION_PLAN.md` untuk detail DoD & checklist per fase.
