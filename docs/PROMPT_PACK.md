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

## Prompt F6 — Dashboard & Laporan

```
Implementasikan **F6 — Dashboard, Pencarian, Laporan Dasar** sesuai docs/IMPLEMENTATION_PLAN.md §3 (F6).

**Cakupan:**
- FR-020/021 (dashboard admin/pengelola), FR-030/031 (pencarian), FR-170 (laporan).
- Dashboard admin: statistik koperasi per status/wilayah, antrian verifikasi (count `submissions.status=submitted`), alert izin mendekati kadaluarsa (30 hari).
- Dashboard pengelola: kelengkapan data (% field terisi), reminder perbaikan (bila ada `revision_requested`), status pengajuan terbaru.
- Pencarian global: filter wilayah/jenis/status/keyword (nama/nomor identitas), pagination.
- Laporan rekapitulasi: per wilayah/jenis/status/periode, hanya data `status_verifikasi=approved`, view-only (ekspor di F8).

**UI:**
- `admin/dashboard`: card statistik + tabel antrian + chart (opsional, bisa Chart.js/Alpine).
- `pengelola/dashboard`: progress bar kelengkapan + alert revisi.
- `admin/laporan/index`: form filter + tabel hasil.

**Optimisasi:**
- Query dengan index yang tepat (wilayah, status, jenis).
- Cache count statistik bila perlu (5 menit).

**Testing:**
- AC-REP-01: laporan hanya data approved (data `pending`/`draft` tidak muncul).
- Dashboard admin hitung antrian benar.
- Pengelola tidak lihat statistik koperasi lain.

**Catatan:**
- Ekspor (CSV/Excel) **defer ke F8**.
- Chart JS opsional — bisa plain table dulu.
- Update docs bila ada query kompleks baru.

Laporkan card/chart yang dibuat + hasil test.
```

---

## Prompt F7 — Direktori Publik

```
Implementasikan **F7 — Direktori Publik & Kontrol Publikasi** sesuai docs/IMPLEMENTATION_PLAN.md §3 (F7).

**Cakupan:**
- FR-180–182 (direktori/profil publik), FR-190/191 (kontrol publikasi).
- Route publik `/public/direktori` (tanpa auth), filter/pencarian.
- Route `/public/koperasi/{slug}` menampilkan profil koperasi (hanya field yang diizinkan — [TUNGGU Q-008]).
- Admin toggle `cooperatives.is_published` (default `false`).
- Migration `settings` (key-value) untuk kebijakan field publik global.
- Masking field sensitif (email/telepon) dengan helper `maskEmail()`/`maskPhone()`.

**Otorisasi:**
- Route publik middleware `web` saja (no auth).
- Hanya koperasi `is_published=true` + `status_verifikasi=approved` muncul di direktori.
- Data `draft`/`pending` tidak pernah publik.

**UI:**
- `public/direktori/index`: card grid koperasi, filter wilayah/jenis.
- `public/direktori/show`: profil (nama, jenis, wilayah, kontak termasking, website).
- Footer: "Data per [tanggal update terakhir]".

**Masking (NFR-SEC-015):**
- Email: `ab***@example.com` (2 karakter pertama + `***` + domain).
- Telepon: `0812****5678` (4 digit awal + `****` + 4 digit akhir).

**Testing:**
- AC-PUB-01: hanya `is_published=true` muncul.
- AC-PUB-02: field sensitif dimasking.
- AC-PUB-03: data `draft`/`pending` tidak muncul meski `is_published=true`.

**Catatan:**
- Setting field publik bisa hard-coded dulu (array di config) bila Q-008 belum dikonfirmasi.
- Update docs (DECISIONS §F7 untuk asumsi field publik sementara).

Laporkan route publik + hasil test + masking helper.
```

---

## Prompt F8 — Lanjutan (sesuai prioritas dinas)

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

**Catatan umum F8:**
- Semua fitur F8 opsional; pilih sesuai waktu/prioritas dinas.
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
