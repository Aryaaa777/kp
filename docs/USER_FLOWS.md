# User Flows

## Sistem Informasi Pendataan dan Pemantauan Koperasi Provinsi Jawa Barat

| Field | Value |
| --- | --- |
| Versi | 1.0 (draft) |
| Terkait | `docs/PRD.md` (§7, §8, §12) |
| Tujuan | Memetakan alur pengguna utama sebagai acuan desain UI & pengujian |

> Label: **[FAKTA]**, **[ASUMSI]**, **[REKOMENDASI]**, **[NEEDS CONFIRMATION]**, **[PROPOSED]**.

---

## 1. Konvensi

- **Aktor:** Admin Dinas, Pengelola Koperasi, Masyarakat (guest).
- **Status pengajuan:** `draft`, `submitted`, `under_review`, `needs_revision`, `approved`, `rejected`, `archived`.
- Setiap alur menyertakan: prasyarat, langkah, hasil, kondisi gagal, dan FR terkait.

> **Status F5 (16 Oktober 2026).** Alur pengajuan & verifikasi yang sudah diimplementasikan:
> FLOW-ADM-03/04 (verifikasi serta tolak/minta perbaikan) dan FLOW-PGL-02/04/05 (mengajukan,
> menindaklanjuti perbaikan, melihat riwayat). Status mengikuti PRD §12.2 (`needs_revision`, bukan
> `revision_requested`). Verifikasi sementara **satu tingkat** oleh admin (Q-004) dan whitelist
> modul/field sementara (Q-006, saat ini hanya `koperasi_induk`). Rincian: `docs/DECISIONS.md` §12.
>
> **Belum mencakup / belum diimplementasikan:** FLOW-ADM-09 (membuat laporan/ekspor, FR-170/171)
> masih berada di fase **F6/F8**; FLOW-ADM-10/11/12 adalah alur **data periodik F4** (tata kelola,
> aset, riwayat keanggotaan) — **bukan** alur pengajuan/verifikasi.

---

## 2. Flow Admin Dinas

### FLOW-ADM-01 — Login ke dashboard
- **Prasyarat:** akun aktif dengan peran admin.
- **Langkah:** buka `/login` → isi email & password → submit.
- **Hasil:** diarahkan ke `/dashboard` (statistik & antrian pengajuan).
- **Gagal:** kredensial salah → pesan generik; akun nonaktif → pesan khusus; rate limit → coba lagi.
- **FR:** FR-001, FR-020, FR-021.

### FLOW-ADM-02 — Menambah/mengubah data koperasi
- **Prasyarat:** admin login.
- **Langkah:** menu Koperasi → Tambah/Ubah → isi form bertahap (identitas, wilayah, kontak) → simpan.
- **Hasil:** data tersimpan; tercatat di audit log.
- **Gagal:** nomor identitas duplikat → tolak; field invalid → error dekat field.
- **FR:** FR-040, FR-200, FR-150.

### FLOW-ADM-03 — Memverifikasi pengajuan (bahagia)
- **Prasyarat:** ada pengajuan berstatus `submitted`.
- **Langkah:** buka antrian → pilih pengajuan → sistem set `under_review` → bandingkan usulan vs data resmi → **Setujui**.
- **Hasil:** sistem menerapkan perubahan ke data resmi dalam transaksi DB; status `approved`; riwayat & audit tercatat; pengelola dinotifikasi.
- **Gagal:** konflik data → kembali `under_review` dengan catatan.
- **FR:** FR-141, FR-143, FR-150, FR-201.

### FLOW-ADM-04 — Menolak / minta perbaikan
- **Prasyarat:** pengajuan `under_review`.
- **Langkah:** pilih **Perlu perbaikan** atau **Tolak** → isi alasan (wajib) → submit.
- **Hasil:** status `needs_revision`/`rejected`; alasan tersimpan & tampil ke pengelola.
- **Gagal:** alasan kosong → validasi menolak.
- **FR:** FR-142.

### FLOW-ADM-05 — Mengelola dokumen
- **Langkah:** buka entitas (izin/aset) → unggah dokumen → validasi tipe/ukuran → simpan ke storage privat.
- **Hasil:** metadata tersimpan; berkas tidak dapat diakses publik.
- **Gagal:** tipe/ukuran salah → pesan spesifik.
- **FR:** FR-070, FR-071.

### FLOW-ADM-06 — Mengelola akun pengelola
- **Langkah:** menu Akun → buat akun pengelola → kaitkan ke koperasi → set status.
- **Hasil:** akun pengelola aktif & terhubung satu koperasi.
- **Gagal:** email duplikat → tolak; koperasi tidak ada → wajib pilih.
- **FR:** FR-010, BR-017.

### FLOW-ADM-07 — Mencatat hasil penilaian kesehatan (kewenangan resmi)
- **Langkah:** buka koperasi → Kesehatan → tambah periode → isi skor/predikat/penilai → set `final`.
- **Hasil:** tercatat per periode (unik koperasi+periode).
- **Gagal:** periode duplikat → tolak/arahkan revisi.
- **FR:** FR-080, BR-010.

### FLOW-ADM-08 — Publikasi informasi koperasi
- **Prasyarat:** data resmi (approved).
- **Langkah:** buka koperasi → Publikasi → pilih field yang boleh tampil → aktifkan `is_published` → simpan.
- **Hasil:** profil muncul di direktori publik sesuai field yang diizinkan.
- **Gagal:** data belum approved → tombol publikasi nonaktif.
- **FR:** FR-190, FR-191, BR-008, BR-020.

### FLOW-ADM-09 — Membuat laporan/ekspor
- **Langkah:** menu Laporan → pilih filter (wilayah/jenis/status/periode) → tampilkan/pratinjau → ekspor (jika tersedia).
- **Hasil:** rekap berbasis data terverifikasi; ekspor tercatat di audit log.
- **FR:** FR-170, FR-171, FR-151.

### FLOW-ADM-10 — Mengelola data tata kelola (periodik)
> **PROVISIONAL — MENUNGGU KONFIRMASI DINAS (Q-010).** Periode untuk sementara **tahunan**; indikator
> (`meeting_count`, `summary`) hanyalah contoh, bukan indikator resmi.
- **Langkah:** buka koperasi → **Tata Kelola** → Tambah → isi tahun/periode/indikator → Simpan (draft/final internal).
- **Hasil:** catatan tersimpan per (`period_year`, `period`); satu catatan per periode (unik).
- **Gagal:** periode duplikat → tervalidasi tolak (AC-PER-01); field wajib kosong → error dekat field.
- **Arsip:** hapus = **soft delete** (data tidak dihapus permanen, BR-011).
- **FR:** FR-090.

### FLOW-ADM-11 — Mengelola data aset (snapshot periodik)
> **PROVISIONAL — MENUNGGU KONFIRMASI DINAS (Q-010).** Snapshot **tahunan**; `quantity`,
> `acquisition_date`, `condition` adalah kolom tambahan; tanpa integrasi akuntansi.
- **Langkah:** buka koperasi → **Aset** → Tambah → pilih tahun/periode, jenis aset, nilai → Simpan.
- **Hasil:** beberapa baris aset per periode; **periode berbeda tidak saling menimpa** (AC-PER-02).
- **Gagal:** nilai negatif / jenis kosong / kondisi tidak dikenal → tervalidasi tolak.
- **Arsip:** hapus = **soft delete** (BR-011).
- **FR:** FR-100, FR-101.

### FLOW-ADM-12 — Mengelola riwayat keanggotaan (per kejadian)
> **PROVISIONAL — MENUNGGU KONFIRMASI DINAS (Q-010).** Dicatat **per kejadian/perubahan**, bukan
> rekap tahunan. **Tidak** mengumpulkan data anggota individual/NIK.
- **Langkah:** buka koperasi → **Riwayat Keanggotaan** → Tambah → isi tanggal, jenis perubahan, jumlah bertambah/berkurang → Simpan.
- **Hasil:** daftar kejadian terurut terbaru-dulu + selisih bersih (AC-PER-03).
- **Gagal:** `event_date` melebihi hari ini → tolak; kedua jumlah kosong → tolak (minimal salah satu diisi).
- **Arsip:** hapus = **soft delete** (BR-011).
- **FR:** FR-110, FR-111.

---

## 3. Flow Pengelola Koperasi

### FLOW-PGL-01 — Login & melihat profil
- **Prasyarat:** akun pengelola terhubung ke koperasi.
- **Langkah:** login → dashboard → Profil koperasi.
- **Hasil:** data resmi & status terlihat (read-only).
- **Gagal:** akun belum dikaitkan → halaman "akun belum terhubung, hubungi admin".
- **FR:** FR-041, FR-030.

### FLOW-PGL-02 — Memperbarui data (mengajukan perubahan)
- **Prasyarat:** pengelola login; koperasi terhubung.
- **Langkah:** pilih modul (**saat ini hanya “Data Induk Koperasi”**, `koperasi_induk`) → isi/ubah field yang diizinkan → simpan draft → kirim.
- **Hasil:** pengajuan `submitted`; data resmi **tidak** berubah sampai approve.
- **Gagal:** field terlarang → ditolak; validasi → error dekat field.
- **FR:** FR-042, FR-140, FR-100, BR-009, BR-014.

### FLOW-PGL-03 — Mengunggah dokumen pendukung
- **Langkah:** pada pengajuan → unggah dokumen → validasi → simpan privat.
- **Hasil:** dokumen terkait pengajuan; hanya pengelola pemilik & admin yang bisa unduh.
- **Gagal:** tipe/ukuran salah → pesan spesifik.
- **FR:** FR-070, FR-071.

### FLOW-PGL-04 — Menindaklanjuti permintaan perbaikan
- **Prasyarat:** ada pengajuan `needs_revision` (pengajuan `rejected` bersifat terminal — tidak dapat dikirim ulang).
- **Langkah:** buka daftar perbaikan → baca alasan → perbaiki → kirim ulang **pada pengajuan yang sama**.
- **Hasil:** pengajuan yang sama kembali berstatus `submitted`; riwayat transisi tetap tersimpan (`submission_reviews` + audit `activity_logs`).
- **Gagal:** alasan belum terbaca tuntas → tetap tersedia di layar; revisi tidak mengubah data resmi.
- **FR:** FR-031, FR-142, FR-140.
- **Catatan:** FR-031 menyebut "versi pengajuan baru" (pengajuan terpisah); perilaku versi baru tersebut **belum diimplementasikan** — keputusan F5-5 memakai pengajuan yang sama (lihat `docs/DECISIONS.md` §12).

### FLOW-PGL-05 — Melihat riwayat pengajuan
- **Langkah:** menu Riwayat → daftar pengajuan + status + waktu.
- **Hasil:** jejak pengajuan koperasi sendiri.
- **FR:** FR-150 (sisi pengelola), FR-011.

### FLOW-PGL-06 — Melihat data periodik koperasi sendiri (hanya baca)
> **PROVISIONAL — MENUNGGU KONFIRMASI DINAS (Q-010).** Penulisan oleh pengelola (via pengajuan, F5)
> belum tersedia; saat ini **read-only**. Kesehatan merupakan kewenangan admin (BR-010).
- **Prasyarat:** pengelola login; koperasi terhubung.
- **Langkah:** menu **Kesehatan / Tata Kelola / Aset / Anggota** → daftar (pagination) → **Detail**.
- **Hasil:** pengelola hanya melihat data **koperasinya sendiri**; tidak ada tombol tambah/ubah/arsip.
- **Gagal (otorisasi server-side):** membuka resource milik koperasi lain → **403** (Policy ownership);
  akun belum tertaut koperasi → 403; guest → redirect login. Menulis lewat route admin → **403** (middleware role).
- **FR:** FR-041/030 (profil), FR-080/090/100/110 (baca), BR-009, BR-014.

---

## 4. Flow Masyarakat (guest)

### FLOW-PUB-01 — Menelusuri direktori
- **Prasyarat:** tidak perlu login.
- **Langkah:** buka `/koperasi` → lihat daftar koperasi terpublikasi → buka profil.
- **Hasil:** hanya koperasi `is_published=true` & approved; field sensitif tidak tampil.
- **Gagal:** koperasi tidak dipublikasikan → tidak muncul (404 bila akses langsung).
- **FR:** FR-180, FR-182, BR-009.

### FLOW-PUB-02 — Mencari & memfilter
- **Langkah:** isi kata kunci (nama) → pilih jenis → pilih kabupaten/kota → submit.
- **Hasil:** hasil relevan dengan pagination.
- **Gagal:** tidak ada hasil → empty state + saran ubah filter.
- **FR:** FR-181, FR-200.

### FLOW-PUB-03 — Melihat statistik agregat
- **Prasyarat:** statistik publik diizinkan dinas **[NEEDS CONFIRMATION]**.
- **Langkah:** buka `/statistik` → lihat agregat (jumlah koperasi per jenis/wilayah).
- **Hasil:** angka agregat tanpa data pribadi.
- **FR:** FR-183, NFR-SEC-015.

---

## 5. Diagram alur (Mermaid)

### 5.1 Alur pengajuan & verifikasi (end-to-end)

```mermaid
flowchart TD
    A[Pengelola login] --> B{Pilih modul data}
    B --> C[Isi / ubah data]
    C --> D{Simpan draft?}
    D -- Ya --> E[Status: draft]
    D -- Tidak --> F[Kirim pengajuan]
    E --> F
    F --> G[Status: submitted]
    G --> H[Admin meninjau menjadi under_review]
    H --> I{Keputusan admin}
    I -- Setujui --> J[Terapkan ke data resmi + riwayat]
    J --> K[Status: approved]
    I -- Perlu perbaikan --> L[Status: needs_revision + alasan]
    I -- Tolak --> M[Status: rejected + alasan]
    L --> C
    K --> N{Publikasi?}
    N -- Ya --> O[Tampil di direktori publik]
    N -- Tidak --> P[Tetap internal]
```

### 5.2 Alur otorisasi & isolasi data

```mermaid
flowchart TD
    Q[Request ke resource] --> R[Middleware auth]
    R --> S{Peran sesuai?}
    S -- Tidak --> T[403]
    S -- Ya --> U[Policy: cek kepemilikan]
    U --> V{Pemilik / berwenang?}
    V -- Tidak --> W[403 / 404 + catat percobaan]
    V -- Ya --> X[Lanjutkan operasi]
```

### 5.3 Alur publikasi

```mermaid
flowchart TD
    A[Data resmi approved] --> B[Admin buka Publikasi]
    B --> C[Pilih field yang boleh tampil]
    C --> D{Aktifkan publikasi?}
    D -- Ya --> E[is_published = true, published_at, published_by]
    D -- Tidak --> F[Tetap internal]
    E --> G[Muncul di direktori publik sesuai field]
```

---

## 6. Matriks ringkas alur → FR

| Flow | Aktor | FR terkait |
| --- | --- | --- |
| FLOW-ADM-01 | Admin | FR-001, FR-020, FR-021 |
| FLOW-ADM-02 | Admin | FR-040, FR-150, FR-200 |
| FLOW-ADM-03 | Admin | FR-141, FR-143, FR-150, FR-201 |
| FLOW-ADM-04 | Admin | FR-142 |
| FLOW-ADM-05 | Admin | FR-070, FR-071 |
| FLOW-ADM-06 | Admin | FR-010 |
| FLOW-ADM-07 | Admin | FR-080 |
| FLOW-ADM-08 | Admin | FR-190, FR-191 |
| FLOW-ADM-09 | Admin | FR-170, FR-171, FR-151 |
| FLOW-PGL-01 | Pengelola | FR-041, FR-030 |
| FLOW-PGL-02 | Pengelola | FR-042, FR-140 |
| FLOW-PGL-03 | Pengelola | FR-070, FR-071 |
| FLOW-PGL-04 | Pengelola | FR-031, FR-142, FR-140 |
| FLOW-PGL-05 | Pengelola | FR-011 |
| FLOW-PUB-01 | Masyarakat | FR-180, FR-182 |
| FLOW-PUB-02 | Masyarakat | FR-181 |
| FLOW-PUB-03 | Masyarakat | FR-183 |
