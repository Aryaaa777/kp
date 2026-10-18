# Roadmap Sisa — Fase F4 hingga F8

> Dokumen ini merangkum fase-fase implementasi yang **belum** dikerjakan setelah F3 selesai.
> Rujukan lengkap ada di `docs/IMPLEMENTATION_PLAN.md` §3.

**Status saat ini (16 Oktober 2026):**
- ✅ **F0** — Inisialisasi (commit `e6d905e`)
- ✅ **F1** — Autentikasi & RBAC (commit `cdb447e`)
- ✅ **F2** — Data Induk Koperasi (commit `1da75bb`)
- ✅ **F3** — Perizinan & Dokumen (sudah di-commit lokal `d495d62`; belum di-push ke `origin/main`; menunggu persetujuan)
- ✅ **F4** — Data Periodik (implementasi & pengujian selesai; menunggu review)
- ✅ **F5** — Workflow Pengajuan & Audit (implementasi & pengujian selesai; menunggu review)
- ⏳ **F6** — Dashboard & Laporan
- ⏳ **F7** — Direktori Publik
- ⏳ **F8** — Lanjutan (Post-MVP)

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

### Cakupan
- **Dashboard admin** (FR-020): statistik koperasi per status/wilayah, antrian verifikasi, alert kadaluarsa izin.
- **Dashboard pengelola** (FR-021): kelengkapan data (%), reminder perbaikan, status pengajuan.
- **Pencarian global** (FR-030): filter multi-kriteria (wilayah, jenis, status, keyword).
- **Laporan/rekapitulasi** (FR-170): per wilayah/jenis/status/periode, hanya data `approved`, view-only (ekspor di F8).

### Deliverables
- Blade: `admin/dashboard`, `pengelola/dashboard`, `admin/laporan/index`.
- Query builder efisien (dengan index yang tepat).
- Feature test AC-REP-01 (laporan hanya data approved, pengelola tidak lihat data koperasi lain).

### Pertanyaan terbuka
- **Q-022**: Kebutuhan laporan pimpinan/eksekutif spesifik?

---

## F7 — Direktori Publik & Kontrol Publikasi

**Dependensi:** F5  
**FR terkait:** 180–182, 190, 191  
**Estimasi:** ~3 hari

### Cakupan
- **Direktori publik** (FR-180): halaman `/public/direktori` (tanpa auth), pencarian/filter koperasi.
- **Profil publik** (FR-181): `/public/koperasi/{slug}` menampilkan field yang diizinkan (BR-021).
- **Kontrol publikasi** (FR-190/191): admin toggle `is_published` per koperasi; tabel `settings` untuk kebijakan field publik global.
- **Masking sensitif** (NFR-SEC-015): email/telepon dimasking (`email: ab***@example.com`).

### Deliverables
- Route publik (middleware `guest`/`web` saja).
- Blade: `public/direktori/index`, `public/direktori/show`.
- Migration `settings` + seeder untuk kebijakan default.
- Feature test AC-PUB-01/02/03 (hanya `is_published=true` muncul, field sensitif dimasking, data `draft` tidak muncul).

### Pertanyaan terbuka
- **Q-008**: Aturan publikasi field spesifik?

---

## F8 — Lanjutan (Post-MVP)

**Dependensi:** F7  
**FR terkait:** 012, 022, 072, 082, 091, 171, 183  
**Estimasi:** ~6 hari (opsional/sesuai kebutuhan)

### Cakupan
- **Ekspor laporan** (FR-171): CSV/Excel/PDF + audit ekspor (`export_logs`).
- **Sub-peran admin** (FR-012): pembatasan wilayah per admin (relasi `admin_regions`).
- **Statistik agregat publik** (FR-183): jumlah koperasi per wilayah/jenis (untuk landing page).
- **Versi dokumen** (FR-072): `documents.version` + restore versi lama.
- **Perhitungan kesehatan otomatis** (FR-082/091): bila formula resmi tersedia, hitung skor dari data aset/anggota.

### Deliverables
- Library ekspor (Laravel Excel / DomPDF).
- Migration `admin_regions`.
- API publik (opsional) untuk statistik agregat.
- Feature test ekspor + pembatasan wilayah.

### Pertanyaan terbuka
- **Q-007**: Formula kesehatan?
- **Q-015**: Format ekspor (kolom/header)?
- **Q-020**: Integrasi sistem pemerintah lain?

---

## Catatan Eksekusi

1. **Urutan wajib:** F4 → F5 → F6 → F7 → F8.
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
