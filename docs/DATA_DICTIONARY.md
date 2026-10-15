# Data Dictionary

## Sistem Informasi Pendataan dan Pemantauan Koperasi Provinsi Jawa Barat

| Field | Value |
| --- | --- |
| Versi | 1.0 (draft) |
| Terkait | `docs/PRD.md` §14 (Rancangan Basis Data) |
| Basis Data | MySQL 8.x **[NEEDS CONFIRMATION]** |
| Konvensi | Tabel plural bahasa Inggris (konvensi Laravel); kolom domain boleh bahasa Indonesia bila sudah lazim (mis. `nama`, `alamat`) — konsisten per tabel. |

> **Aturan umum untuk semua tabel**
> - Primary key: `id` `BIGINT UNSIGNED AUTO_INCREMENT`.
> - Timestamp: `created_at`, `updated_at` (nullable kecuali tabel append-only).
> - Soft delete: `deleted_at` (nullable) hanya untuk entitas yang perlu pengarsipan.
> - Foreign key: `BIGINT UNSIGNED`, dengan constraint `ON DELETE RESTRICT` (default aman) kecuali dinyatakan lain.
> - Password: **hanya** hash (bcrypt/argon2). Tidak ada plaintext. **[FAKTA]**
> - Data periodik: **tidak boleh menimpa** nilai lama; baris baru per periode. **[FAKTA]**
> - Semua tabel dikelola via migration Laravel (bukan SQL manual di produksi).

Legend: **PK** = Primary Key, **FK** = Foreign Key, **UK** = Unique, **NN** = Not Null, **IDX** = Index.

---

## 1. `users`

Akun internal (Admin Dinas & Pengelola Koperasi).

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| name | VARCHAR(150) | NN | | Nama pengguna |
| email | VARCHAR(150) | NN | UK | Login identitas |
| password | VARCHAR(255) | NN | | **Hash**, bukan plaintext |
| phone | VARCHAR(30) | NULL | | Kontak |
| koperasi_id | BIGINT UNSIGNED | NULL | FK → cooperatives.id, `ON DELETE SET NULL` | Pengelola terhubung 1 koperasi (MVP). *Kolom dibuat F1; constraint FK ditambahkan pada migrasi F2 `2026_10_16_100003` (`nullOnDelete`).* |
| region_id | BIGINT UNSIGNED | NULL | FK → regions.id, IDX | Untuk pembatasan wilayah (FASE-2) |
| status | ENUM('active','inactive') | NN | IDX, default 'active' | Akun nonaktif tidak bisa login |
| email_verified_at | TIMESTAMP | NULL | | |
| last_login_at | TIMESTAMP | NULL | | |
| remember_token | VARCHAR(100) | NULL | | |
| created_at | TIMESTAMP | NULL | | |
| updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete |

**Index:** `email` (unique), `koperasi_id`, `region_id`, `status`.
**Relasi:** belongsToMany `roles`; belongsTo `cooperatives`; hasMany `submissions`, `activity_logs`.

---

## 2. `roles`

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| name | VARCHAR(50) | NN | UK | slug, mis. `admin_dinas`, `pengelola_koperasi`, `super_admin`, `verifikator`, `pimpinan`, `operator_publikasi` |
| display_name | VARCHAR(100) | NN | | Label UI |
| description | VARCHAR(255) | NULL | | |
| created_at / updated_at | TIMESTAMP | NULL | | |

**Seeded roles (MVP):** `admin_dinas`, `pengelola_koperasi` (+ sub-peran admin sebagai **[NEEDS CONFIRMATION]**).

---

## 3. `role_user` (pivot)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| user_id | BIGINT UNSIGNED | NN | FK → users.id, IDX | |
| role_id | BIGINT UNSIGNED | NN | FK → roles.id, IDX | |
| created_at / updated_at | TIMESTAMP | NULL | | |

**Unique:** (`user_id`, `role_id`).

---

## 4. `regions`

Referensi wilayah (provinsi → kabupaten/kota → kecamatan → desa).

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| code | VARCHAR(20) | NN | UK | Kode wilayah (sumber resmi **[NEEDS CONFIRMATION]**) |
| name | VARCHAR(150) | NN | | |
| type | ENUM('province','regency','city','district','village') | NN | IDX | |
| parent_id | BIGINT UNSIGNED | NULL | FK → regions.id, IDX | Hierarki |
| created_at / updated_at | TIMESTAMP | NULL | | |

**Index:** `code` (unique), `parent_id`, `type`.
**Catatan:** isi via `RegionSeeder` dari sumber resmi. Jawa Barat: 1 provinsi + kabupaten/kota. **[NEEDS CONFIRMATION]** sumber & kedalaman data.
**Status F2:** tabel dibuat (migrasi `2026_10_16_100001`) tetapi **belum di-seed** karena sumber & format kode wilayah (Q-012) belum dikonfirmasi. Tidak ada data wilayah yang dikarang.

---

## 5. `cooperatives` (data induk koperasi)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | Identitas sistem unik (BR-001) |
| nama | VARCHAR(200) | NN | IDX | Nama koperasi |
| slug | VARCHAR(220) | NN | UK | Untuk URL publik |
| nomor_identitas | VARCHAR(100) | NN | UK³ | Nomor resmi (format **[NEEDS CONFIRMATION]**, BR-002) |
| jenis_koperasi | VARCHAR(100) | NN | IDX | Enum dari konfirmasi dinas **[NEEDS CONFIRMATION]** |
| bentuk_koperasi | VARCHAR(100) | NULL | | |
| tanggal_berdiri | DATE | NULL | | |
| region_id | BIGINT UNSIGNED | NULL¹ | FK → regions.id, IDX | Kabupaten/kota |
| alamat | TEXT | NULL | | |
| telephone | VARCHAR(30) | NULL | | Kontak utama |
| email | VARCHAR(150) | NULL | | Kontak utama |
| website | VARCHAR(200) | NULL | | |
| status_verifikasi | ENUM('draft','submitted','under_review','needs_revision','approved','rejected','archived') | NN | IDX, default 'draft' | Status data resmi ⁴ |
| is_published | BOOLEAN | NN | IDX, default 0 | Opt-in publikasi (BR-016) |
| published_at | TIMESTAMP | NULL | | |
| published_by | BIGINT UNSIGNED | NULL | FK → users.id | |
| created_by | BIGINT UNSIGNED | NULL | FK → users.id | |
| updated_by | BIGINT UNSIGNED | NULL | FK → users.id | |
| archived_reason | TEXT | NULL | | ² Alasan pengarsipan (FR-043) |
| created_at / updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete (BR-011) |

¹ **Sementara** dibuat `NULL` pada F2 karena referensi wilayah belum tersedia (Q-012). Dinaikkan menjadi `NN` setelah wilayah di-seed.
² **Sementara** ditambahkan agar alasan arsip tersimpan sebelum `activity_logs` dibuat pada F5.
³ Keunikan berlaku **termasuk data terarsip** (soft delete). Ini **asumsi sementara [ASUMSI]** berbasis BR-019, **belum** dikonfirmasi dinas; sejalan dengan indeks unik pada database dan validasi aplikasi.
⁴ Pada F2 nilai `archived` **tidak** dapat dipilih lewat form; hanya tercapai melalui aksi arsip (FR-043) yang sekaligus soft delete. Penjaga matriks transisi resmi (PRD §12.2) menyusul pada F5.

**Index:** `nomor_identitas` (unique), `slug` (unique), `region_id`, `jenis_koperasi`, `is_published`, `status_verifikasi`.
**Relasi:** belongsTo `regions`; hasMany licenses, documents, health_assessments, governance_records, assets, member_histories, officers, submissions, contacts.

---

## 6. `cooperative_contacts` (opsional / FASE-2)

Kontak tambahan (PIC) selain kontak utama pada `cooperatives`.

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | |
| nama | VARCHAR(150) | NN | | Nama PIC |
| jabatan | VARCHAR(100) | NULL | | |
| telephone | VARCHAR(30) | NULL | | |
| email | VARCHAR(150) | NULL | | |
| is_primary | BOOLEAN | NN | default 0 | |
| created_at / updated_at | TIMESTAMP | NULL | | |

---

## 7. `cooperative_licenses` (izin usaha & izin usaha simpan pinjam)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | |
| license_type | ENUM('business','savings_loans') | NN | IDX | Discriminator |
| nomor | VARCHAR(100) | NN | | Nomor izin |
| tanggal_terbit | DATE | NN | | |
| tanggal_berlaku_sampai | DATE | NULL | IDX | Nullable = berlaku tanpa batas |
| penerbit | VARCHAR(200) | NULL | | |
| status | ENUM('active','expired','revoked','pending') | NN | IDX, default 'pending' | Status **[NEEDS CONFIRMATION]** nilai valid |
| verified_by | BIGINT UNSIGNED | NULL | FK → users.id | |
| verified_at | TIMESTAMP | NULL | | |
| catatan | TEXT | NULL | | |
| created_at / updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete |

**Unique:** (`cooperative_id`, `license_type`, `nomor`).
**Index:** `cooperative_id`, `license_type`, `status`, `tanggal_berlaku_sampai`.
**Validasi:** `tanggal_berlaku_sampai` ≥ `tanggal_terbit` (jika tidak null).

> **Catatan desain:** satu tabel untuk dua jenis izin (menu berbeda di UI). Jika field kedua jenis izin sangat berbeda, pisah menjadi `business_licenses` & `savings_loan_licenses`. **[NEEDS CONFIRMATION]**

---

## 8. `documents` (dokumen pendukung, polimorfik)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | Pemilik dokumen |
| documentable_type | VARCHAR(150) | NN | IDX | Kelas entitas terkait |
| documentable_id | BIGINT UNSIGNED | NN | IDX | ID entitas terkait |
| category | VARCHAR(100) | NN | IDX | mis. `akta_pendirian`, `izin_usaha`, `laporan_aset` |
| original_name | VARCHAR(255) | NN | | Nama asli berkas |
| file_path | VARCHAR(500) | NN | | Path di storage **privat** |
| mime_type | VARCHAR(120) | NN | | Hasil validasi |
| size_bytes | BIGINT UNSIGNED | NN | | Ukuran berkas |
| checksum | VARCHAR(128) | NULL | | Integritas (opsional) |
| version | INT UNSIGNED | NN | default 1 | Versi dokumen (FASE-2) |
| uploaded_by | BIGINT UNSIGNED | NN | FK → users.id | |
| created_at / updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete |

**Index:** (`documentable_type`, `documentable_id`), `cooperative_id`, `category`.
**Keamanan:** `file_path` **tidak** berada di `public/`; unduh via controller ber-Policy (NFR-SEC-009/010).
**Validasi unggah:** whitelist ekstensi/MIME (`pdf,jpg,jpeg,png`), ukuran maks **[PROPOSED: 5 MB]**.

---

## 9. `health_assessments` (hasil penilaian kesehatan)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | |
| period_year | SMALLINT UNSIGNED | NN | IDX | Tahun penilaian |
| period | ENUM('semester_1','semester_2','annual') | NN | default 'annual' | **[NEEDS CONFIRMATION]** |
| assessment_date | DATE | NN | | Tanggal penilaian |
| score | DECIMAL(6,2) | NULL | | Skor total |
| predicate | VARCHAR(50) | NULL | IDX | Predikat (mis. Sehat/Cukup Sehat) |
| assessor_id | BIGINT UNSIGNED | NULL | FK → users.id | Penilai (admin) |
| notes | TEXT | NULL | | |
| status | ENUM('draft','final') | NN | default 'draft' | Hanya `final` = resmi |
| created_at / updated_at | TIMESTAMP | NULL | | |

**Unique:** (`cooperative_id`, `period_year`, `period`).
**Otorisasi:** tulis hanya admin berwenang (BR-010); pengelola read-only.

---

## 10. `governance_records` (tata kelola)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | |
| period_year | SMALLINT UNSIGNED | NN | IDX | |
| period | ENUM('semester_1','semester_2','annual') | NULL | | Opsional |
| meeting_count | INT UNSIGNED | NULL | | Jumlah rapat (contoh indikator) |
| governance_score | DECIMAL(6,2) | NULL | | Ringkasan skor (jika ada) |
| summary | TEXT | NULL | | Catatan evaluasi |
| status | ENUM('draft','final') | NN | default 'draft' | |
| recorded_by | BIGINT UNSIGNED | NOT NULL | FK → users.id | |
| created_at / updated_at | TIMESTAMP | NULL | | |

**Unique:** (`cooperative_id`, `period_year`, `period`). **[NEEDS CONFIRMATION]** indikator tata kelola resmi.

---

## 11. `cooperative_assets` (aset & periode pencatatan)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | |
| period_year | SMALLINT UNSIGNED | NN | IDX | |
| asset_type | VARCHAR(100) | NN | IDX | Jenis aset |
| asset_name | VARCHAR(200) | NULL | | Nama detail |
| value | DECIMAL(18,2) | NN | | Nilai (Rupiah) |
| description | TEXT | NULL | | |
| recorded_by | BIGINT UNSIGNED | NN | FK → users.id | |
| created_at / updated_at | TIMESTAMP | NULL | | |

**Index:** `cooperative_id`, `period_year`, `asset_type`.
**Aturan:** data periode berbeda **tidak menimpa** (BR-007). Beberapa baris per periode dimungkinkan (multi jenis aset).

---

## 12. `member_histories` (riwayat jumlah anggota)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | |
| period_year | SMALLINT UNSIGNED | NN | IDX | |
| member_total | INT UNSIGNED | NN | | Total anggota |
| member_active | INT UNSIGNED | NULL | | Aktif |
| member_joined | INT UNSIGNED | NULL | | Anggota baru |
| member_left | INT UNSIGNED | NULL | | Keluar |
| recorded_by | BIGINT UNSIGNED | NN | FK → users.id | |
| created_at / updated_at | TIMESTAMP | NULL | | |

**Unique:** (`cooperative_id`, `period_year`).
**Validasi:** nilai ≥ 0; `member_active` ≤ `member_total`.

---

## 13. `officers` (susunan pengurus)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | |
| name | VARCHAR(150) | NN | | Nama pengurus |
| position | VARCHAR(100) | NN | IDX | Jabatan |
| identity_number | VARCHAR(50) | NULL | | **Data pribadi** — akses terbatas, tidak dipublikasikan |
| period_start | DATE | NN | | |
| period_end | DATE | NULL | | Null = masih menjabat |
| is_active | BOOLEAN | NN | IDX, default 1 | |
| created_at / updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete |

**Index:** `cooperative_id`, `position`, `is_active`.
**Validasi:** `period_end` ≥ `period_start` (jika tidak null).
**Privasi:** `identity_number` di-mask/di-hide pada halaman publik (NFR-SEC-015).

---

## 14. `submissions` (pengajuan data)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | |
| submitted_by | BIGINT UNSIGNED | NN | FK → users.id, IDX | Pembuat pengajuan |
| module | VARCHAR(50) | NN | IDX | Modul data (mis. `koperasi_induk`, `license`, `asset`) |
| title | VARCHAR(200) | NULL | | Ringkasan pengajuan |
| status | ENUM('draft','submitted','under_review','needs_revision','approved','rejected','archived') | NN | IDX, default 'draft' | Lihat §12 PRD |
| reason | TEXT | NULL | | Alasan tolak/perbaikan terakhir |
| reviewed_by | BIGINT UNSIGNED | NULL | FK → users.id | Admin peninjau |
| reviewed_at | TIMESTAMP | NULL | | |
| applied_at | TIMESTAMP | NULL | | Saat diterapkan ke data resmi |
| created_at / updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete |

**Index:** `cooperative_id`, `status`, `module`, `submitted_by`.

---

## 15. `submission_items` (detail perubahan field-level)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| submission_id | BIGINT UNSIGNED | NN | FK → submissions.id, IDX | |
| field | VARCHAR(150) | NN | | Nama field/kunci |
| old_value | TEXT / JSON | NULL | | Nilai sebelum (null bila baru) |
| new_value | TEXT / JSON | NULL | | Nilai usulan |
| created_at / updated_at | TIMESTAMP | NULL | | |

**Index:** `submission_id`.
**Peran:** menyimpan perbedaan agar data resmi tidak tertimpa sebelum approve (BR-014).

---

## 16. `submission_reviews` (log transisi & catatan tinjauan)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| submission_id | BIGINT UNSIGNED | NN | FK → submissions.id, IDX | |
| user_id | BIGINT UNSIGNED | NN | FK → users.id | Aktor transisi |
| from_status | VARCHAR(30) | NULL | | |
| to_status | VARCHAR(30) | NN | | |
| note | TEXT | NULL | | **Wajib** saat `needs_revision`/`rejected` |
| created_at | TIMESTAMP | NULL | | Append-only |

**Index:** `submission_id`.

---

## 17. `activity_logs` (audit trail)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| user_id | BIGINT UNSIGNED | NULL | FK → users.id, IDX | Null = aksi sistem |
| action | VARCHAR(100) | NN | IDX | mis. `login`, `approve_submission`, `publish`, `download_document`, `export` |
| subject_type | VARCHAR(150) | NULL | IDX | Kelas entitas |
| subject_id | BIGINT UNSIGNED | NULL | IDX | ID entitas |
| description | TEXT | NULL | | Uraian |
| properties | JSON | NULL | | Nilai lama/baru, konteks |
| ip_address | VARCHAR(45) | NULL | | IPv4/IPv6 |
| user_agent | TEXT | NULL | | |
| created_at | TIMESTAMP | NULL | IDX | Append-only |

**Index:** (`subject_type`,`subject_id`), `user_id`, `action`, `created_at`.
**Aturan:** tidak diubah/dihapus dari UI (NFR-SEC-012).

---

## 18. `settings` (konfigurasi aplikasi & publikasi)

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| key | VARCHAR(150) | NN | UK | mis. `public.show_license` |
| value | TEXT / JSON | NULL | | Nilai konfigurasi |
| created_at / updated_at | TIMESTAMP | NULL | | |

**Contoh key:** `public.show_license`, `public.show_health`, `public.show_officers`, `public.aggregate_visible`, `upload.max_size_mb`.
**Catatan:** kebijakan publikasi global di `settings`; keputusan per koperasi di `cooperatives.is_published`.

---

## Ringkasan relasi (teks)

- `users` **N–M** `roles` (via `role_user`).
- `users` **N–1** `cooperatives` (pengelola → satu koperasi).
- `users` **N–1** `regions` (opsional, pembatasan wilayah).
- `regions` **self** (parent–child).
- `cooperatives` **1–N** `cooperative_licenses`, `documents`, `health_assessments`, `governance_records`, `cooperative_assets`, `member_histories`, `officers`, `submissions`, `cooperative_contacts`.
- `submissions` **1–N** `submission_items`, `submission_reviews`.
- `users` **1–N** `submissions`, `activity_logs`.

---

## Pemetaan FR → tabel utama

| FR | Tabel utama |
| --- | --- |
| FR-001–004 | users, roles, role_user |
| FR-010–012 | role_user, users, regions |
| FR-040–043 | cooperatives, regions |
| FR-050/051, FR-060/061 | cooperative_licenses, documents |
| FR-070–072 | documents |
| FR-080–082 | health_assessments |
| FR-090–091 | governance_records |
| FR-100/101 | cooperative_assets |
| FR-110/111 | member_histories |
| FR-120/121 | officers |
| FR-130 | cooperatives (+ cooperative_contacts) |
| FR-140–143 | submissions, submission_items, submission_reviews |
| FR-150/151 | activity_logs (+ submission_items) |
| FR-160 | (query lintas tabel) |
| FR-170/171 | (agregasi lintas tabel) |
| FR-180–183 | cooperatives (is_published), settings |
| FR-190/191 | settings, cooperatives.published_* |
