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

Akun internal (Admin Dinas, Admin Wilayah & Pengelola Koperasi).

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| name | VARCHAR(150) | NN | | Nama pengguna |
| email | VARCHAR(150) | NN | UK | Login identitas |
| password | VARCHAR(255) | NN | | **Hash**, bukan plaintext |
| phone | VARCHAR(30) | NULL | | Kontak |
| koperasi_id | BIGINT UNSIGNED | NULL | FK → cooperatives.id, `ON DELETE SET NULL` | Pengelola terhubung 1 koperasi (MVP). *Kolom dibuat F1; constraint FK ditambahkan pada migrasi F2 `2026_10_16_100003` (`nullOnDelete`).* |
| region_id | BIGINT UNSIGNED | NULL | FK → regions.id, `ON DELETE SET NULL`, IDX | Wilayah kewenangan `admin_wilayah` (FR-012, PROVISIONAL). Diabaikan untuk peran lain. Migrasi `2026_10_26_000001`; pembatasan **nonaktif** secara default (`ADMIN_REGION_SCOPE_ENABLED=false`, Q-005). |
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
| code | VARCHAR(20) | NN | UK | Kode wilayah resmi (**Kepmendagri 300.2.2-2138/2025**), disimpan sebagai **string** agar nol di depan tidak hilang. Contoh: `32`, `32.73`, `32.73.01`, `32.73.01.1001` |
| name | VARCHAR(150) | NN | | Nama resmi menurut sumber (bukan nama karangan) |
| type | ENUM('province','regency','city','district','village') | NN | IDX | `village` mencakup **desa dan kelurahan**; skema tidak membedakannya. Digit pertama segmen desa membedakan kelurahan (`1`) dan desa (`2`) |
| parent_id | BIGINT UNSIGNED | NULL | FK → regions.id, IDX | Hierarki; provinsi `NULL` |
| created_at / updated_at | TIMESTAMP | NULL | | |

**Index:** `code` (unique), `parent_id`, `type`.
**Catatan:** diisi lewat `php artisan regions:import` dari `database/data/regions/jawa_barat_2025.csv` (sumber resmi + checksum dicatat di `docs/REGION_DATA_SOURCES.md` dan `database/data/regions/jawa_barat_2025.report.json`). Dataset Jawa Barat: **1 provinsi, 27 kabupaten/kota (18 kabupaten + 9 kota), 627 kecamatan, 5.957 desa/kelurahan** (total 6.612 baris). Importer idempoten dan transaksional.
**Status 26 Oktober 2026:** tabel dibuat pada migrasi `2026_10_16_100001`; dataset resmi **sudah diimpor ke database lokal** MariaDB 10.4.32 (**6.612 baris**). Database produksi/staging belum diimpor. Tidak ada data wilayah yang dikarang. Kode mengikuti **Kemendagri**; kode BPS **tidak** dicampur (lihat `docs/DECISIONS.md` §30 dan §33).

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
| region_id | BIGINT UNSIGNED | NULL¹ | FK → regions.id, IDX | Wilayah koperasi. Data baru: **desa/kelurahan**; data lama boleh tetap menunjuk kabupaten/kota/kecamatan. Alamat publik ditampilkan dari hierarki |
| alamat | TEXT | NULL | | |
| telephone | VARCHAR(30) | NULL | | Kontak utama |
| email | VARCHAR(150) | NULL | | Kontak utama |
| website | VARCHAR(200) | NULL | | |
| status_verifikasi | ENUM('draft','submitted','under_review','needs_revision','approved','rejected','archived') | NN | IDX, default 'draft' | Status data resmi ⁴ |
| status_operasional | ENUM('aktif','tidak_aktif','bubar') | NULL | | Status operasional koperasi (keputusan pemilik, Tahap 106). `NULL` = belum ditetapkan/belum dikonfirmasi ⁵ |
| is_published | BOOLEAN | NN | IDX, default 0 | Opt-in publikasi (BR-016) |
| published_at | TIMESTAMP | NULL | | Waktu publikasi terakhir (D-6) |
| published_by | BIGINT UNSIGNED | NULL | FK → users.id | Aktor publikasi terakhir (D-6) |
| unpublished_at | TIMESTAMP | NULL | | Waktu penarikan publikasi terakhir (D-6, Tahap 105) |
| unpublished_by | BIGINT UNSIGNED | NULL | FK → users.id | Aktor penarikan publikasi terakhir (D-6, Tahap 105) |
| created_by | BIGINT UNSIGNED | NULL | FK → users.id | |
| updated_by | BIGINT UNSIGNED | NULL | FK → users.id | |
| archived_reason | TEXT | NULL | | ² Alasan pengarsipan (FR-043) |
| created_at / updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete (BR-011) |

¹ **Sementara** dibuat `NULL` pada F2 karena referensi wilayah belum tersedia (Q-012). Dataset resmi kini tersedia (Tahap 107), tetapi kolom **tetap `NULL`** sampai audit/backfill `region_id` tuntas; perubahan menjadi `NN` memerlukan migrasi baru dan persetujuan pemilik.
² **Sementara** ditambahkan agar alasan arsip tersimpan sebelum `activity_logs` dibuat pada F5.
³ Keunikan berlaku **termasuk data terarsip** (soft delete). Ini **asumsi sementara [ASUMSI]** berbasis BR-019, **belum** dikonfirmasi dinas; sejalan dengan indeks unik pada database dan validasi aplikasi.
⁴ Pada F2 nilai `archived` **tidak** dapat dipilih lewat form; hanya tercapai melalui aksi arsip (FR-043) yang sekaligus soft delete. Penjaga matriks transisi resmi (PRD §12.2) menyusul pada F5.
⁵ **Tahap 106.** `status_operasional` **nullable tanpa default**: data lama tidak diberi nilai karangan dan **tidak** dianggap aktif. Wajib diisi saat menambah koperasi baru; data lama dilengkapi lewat form edit oleh pihak berwenang. Hanya tiga nilai resmi yang diterima (validasi server-side + `enum` database). Berbeda dari `status_verifikasi` (alur verifikasi) dan `is_published` (tampil/tidak di direktori).

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

> **Catatan F3:** status awal `pending`; hanya aksi verifikasi admin (FR-051/061) yang mengubah `status` + `verified_by`/`verified_at` — `status` tidak dapat diisi dari form tambah/ubah. Keunikan (`cooperative_id`,`license_type`,`nomor`) berlaku **termasuk data terarsip** (soft delete), konsisten dengan indeks unik basis data — asumsi sementara **[ASUMSI]**. Kadaluarsa diidentifikasi dari `tanggal_berlaku_sampai` (belum ada transisi otomatis). Lihat `docs/DECISIONS.md` §5.

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
| version | INT UNSIGNED | NN | default 1 | Nomor versi dokumen (FR-072) |
| previous_version_id | BIGINT UNSIGNED | NULL | FK → documents.id, `ON DELETE SET NULL`, IDX | Versi sebelumnya dalam rantai versi (FR-072). Migrasi `2026_10_26_000004`. |
| superseded_at | TIMESTAMP | NULL | IDX | Waktu dokumen digantikan versi lebih baru. Versi lama **tidak dihapus**; berkas tetap disimpan (BR-011). |
| uploaded_by | BIGINT UNSIGNED | NN | FK → users.id | |
| created_at / updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete |

**Index:** (`documentable_type`, `documentable_id`), `cooperative_id`, `category`.
**Keamanan:** `file_path` **tidak** berada di `public/`; unduh via controller ber-Policy (NFR-SEC-009/010).
**Validasi unggah:** whitelist ekstensi/MIME (`pdf,jpg,jpeg,png`), ukuran maks **[PROPOSED: 5 MB]**.

> **Catatan F3:** `category` disimpan sebagai **teks bebas** (contoh `akta_pendirian`/`izin_usaha`/`laporan_aset`) karena taksonomi resmi belum ditetapkan. Pada F3 `documentable` baru menunjuk `cooperative_licenses`; entitas lain menyusul pada fase berikutnya. Arsip dokumen = soft delete dan berkas fisik dipertahankan (BR-011). Disk `local` → `storage/app/private/documents/{cooperative_id}/`. Lihat `docs/DECISIONS.md` §5.
>
> **Catatan F8 (FR-072):** unggah versi baru menautkan `previous_version_id` dan mengisi `superseded_at` pada versi lama; tidak ada penghapusan berkas. Unduhan versi lama tetap melalui controller ber-Policy. Lihat `docs/DECISIONS.md` §33.

---

## 9. `health_assessments` (hasil penilaian kesehatan)

> **Status implementasi F4/F8 (PROVISIONAL — MENUNGGU KONFIRMASI DINAS / Q-010, Q-007).**
> Periodisitas dibatasi **tahunan** untuk simulasi. Sejak F8, admin dapat mengisi lima komponen
> skor 0–100; server menghitung skor total berbobot dan predikat memakai aturan sementara
> `config('koperasi.health_assessment')` (bobot sama 20%, ambang 85/70/55). Input manual tanpa
> komponen tetap didukung. Ini **bukan** formula resmi dinas. Nilai `final` hanyalah penanda
> internal, **bukan** klasifikasi/pengesahan resmi.

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | |
| period_year | SMALLINT UNSIGNED | NN | IDX | Tahun penilaian |
| period | VARCHAR(20) | NN | default 'annual' | **PROVISIONAL** (Dictionary awal: ENUM semester/annual). Disimpan VARCHAR agar semester/triwulan dapat ditambah tanpa migrasi destruktif — nilai dari `config('koperasi.period_options')`. |
| assessment_date | DATE | NN | | Tanggal penilaian (≤ hari ini) |
| component_permodalan | DECIMAL(5,2) | NULL | | Komponen skor 0–100 (FR-082, PROVISIONAL). Migrasi `2026_10_26_000002`. |
| component_likuiditas | DECIMAL(5,2) | NULL | | Komponen skor 0–100 (FR-082, PROVISIONAL). |
| component_rentabilitas | DECIMAL(5,2) | NULL | | Komponen skor 0–100 (FR-082, PROVISIONAL). |
| component_kualitas_aset | DECIMAL(5,2) | NULL | | Komponen skor 0–100 (FR-082, PROVISIONAL). |
| component_efisiensi | DECIMAL(5,2) | NULL | | Komponen skor 0–100 (FR-082, PROVISIONAL). |
| score | DECIMAL(6,2) | NULL | | Skor total. Bila `auto_calculated = true`, dihitung server-side dari komponen berbobot; jika tidak, input manual. |
| predicate | VARCHAR(50) | NULL | | Predikat. Bila `auto_calculated = true`, dipetakan server-side dari ambang konfigurasi; jika tidak, teks manual. **Bukan** klasifikasi resmi. **Tidak** diindeks. |
| auto_calculated | BOOLEAN | NN | default 0 | Penanda skor/predikat dihitung dari komponen (FR-082, PROVISIONAL). |
| assessor_id | BIGINT UNSIGNED | NULL | FK → users.id (`nullOnDelete`) | Penilai (admin) — diisi server-side |
| notes | TEXT | NULL | | Catatan pendukung |
| status | VARCHAR(20) | NN | IDX, default 'draft' | `draft`\|`final` (VARCHAR, bukan ENUM). `final` = penanda internal. |
| created_at / updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete (BR-011: pengarsipan) — **ditambahkan** dari draft awal |

**Index:** `period_year`, `status` (didefinisikan eksplisit di migration). FK `cooperative_id`/`assessor_id` terindeks otomatis. `predicate` **tidak** diindeks (draf awal keliru menandainya `IDX`).
**Unique:** (`cooperative_id`, `period_year`, `period`) → AC-PER-01 (duplikat periode ditolak). **Keunikan tetap berlaku untuk baris terarsip** (unique constraint tidak membedakan `deleted_at`), sehingga periode yang diarsipkan **tidak** dapat dibuat ulang dan harus **dipulihkan** oleh admin (route `.../kesehatan/{healthAssessment}/pulihkan`, otorisasi Policy `restore`). Baris terarsip tidak dapat diakses endpoint biasa sebelum dipulihkan, tidak ditampilkan ke pengelola, dan `withTrashed()` hanya dipakai pada alur pemulihan/arsip. Lihat `docs/DECISIONS.md` §10.
**Otorisasi:** tulis hanya admin berwenang (BR-010); pengelola read-only. Lihat `docs/DECISIONS.md` §10.

---

## 10. `governance_records` (tata kelola)

> **Status implementasi F4/F8 (PROVISIONAL — Q-010, Q-007).** Periode tahunan; indikator resmi tata
> kelola belum ditetapkan. Sejak F8, checklist kepatuhan (RAT tepat waktu, minimal 2 rapat/tahun,
> laporan keuangan tersedia, dokumen dasar lengkap) dievaluasi server-side menjadi `patuh`/`deviasi`;
> catatan wajib bila ada deviasi. Checklist dan ambang dapat diubah lewat
> `config('koperasi.governance_compliance')` dan **bukan** aturan resmi dinas.

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | |
| period_year | SMALLINT UNSIGNED | NN | IDX | |
| period | VARCHAR(20) | NN | default 'annual' | **PROVISIONAL**. Dictionary awal menyebut `NULL`; diimplementasikan `NN` agar unique benar-benar berlaku (di MySQL, NULL ganda lolos unique). Lihat `docs/DECISIONS.md` §10. |
| meeting_count | INT UNSIGNED | NULL | | Jumlah rapat periode; dipakai checklist kepatuhan (PROVISIONAL) |
| rat_on_time | BOOLEAN | NULL | | Checklist: RAT tepat waktu (FR-091, PROVISIONAL). Migrasi `2026_10_26_000003`. |
| financial_report_available | BOOLEAN | NULL | | Checklist: laporan keuangan tersedia (FR-091, PROVISIONAL). |
| basic_documents_complete | BOOLEAN | NULL | | Checklist: dokumen dasar lengkap (FR-091, PROVISIONAL). |
| governance_score | DECIMAL(6,2) | NULL | | Ringkasan skor (input manual) |
| summary | TEXT | NULL | | Catatan evaluasi |
| status | VARCHAR(20) | NN | IDX, default 'draft' | `draft`\|`final` (VARCHAR) |
| compliance_status | VARCHAR(20) | NULL | IDX | `patuh`\|`deviasi`, dihitung server-side dari checklist (FR-091, PROVISIONAL). |
| compliance_notes | TEXT | NULL | | Wajib diisi bila `compliance_status = deviasi`. |
| recorded_by | BIGINT UNSIGNED | NN | FK → users.id | Diisi server-side |
| created_at / updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete (BR-011) — **ditambahkan** |

**Index:** `period_year`, `status`, `compliance_status` (eksplisit). FK `cooperative_id`/`recorded_by` terindeks otomatis.
**Unique:** (`cooperative_id`, `period_year`, `period`) → keunikan tetap berlaku untuk baris terarsip; pemulihan oleh admin melalui route `.../tata-kelola/{governanceRecord}/pulihkan` (Policy `restore`, hanya admin). Lihat `docs/DECISIONS.md` §10 dan §33. **[NEEDS CONFIRMATION]** indikator/checklist tata kelola resmi (Q-007).

---

## 11. `cooperative_assets` (aset & periode pencatatan)

> **Status implementasi F4 (PROVISIONAL — Q-010).** Snapshot **tahunan**. Kolom `quantity`,
> `acquisition_date`, `condition` **belum** ada pada draft awal — ditambahkan sebagai struktur minimum
> untuk simulasi. Tidak ada integrasi akuntansi/perhitungan otomatis.

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | |
| period_year | SMALLINT UNSIGNED | NN | IDX | |
| asset_type | VARCHAR(100) | NN | IDX | Jenis aset (teks bebas; taksonomi resmi belum ada — `config('koperasi.asset_type_options')` kosong = input teks) |
| asset_name | VARCHAR(200) | NULL | | Nama detail |
| quantity | INT UNSIGNED | NULL | | **PROVISIONAL** (tidak ada di draft awal) |
| value | DECIMAL(18,2) | NN | | Nilai (Rupiah, input manual) |
| acquisition_date | DATE | NULL | | **PROVISIONAL** (tidak ada di draft awal) |
| condition | VARCHAR(30) | NULL | | **PROVISIONAL** — nilai dari `config('koperasi.asset_condition_options')` |
| description | TEXT | NULL | | |
| recorded_by | BIGINT UNSIGNED | NN | FK → users.id | Diisi server-side |
| created_at / updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete (BR-011) — **ditambahkan** |

**Index:** (`cooperative_id`, `period_year`), `asset_type`.
**Aturan:** data periode berbeda **tidak menimpa** (BR-007, AC-PER-02). Beberapa baris per periode dimungkinkan.

---

## 12. `member_histories` (riwayat keanggotaan)

> **Status implementasi F4 (PROVISIONAL — Q-010) — PENYIMPANGAN dari draft awal.** Diimplementasikan
> **berbasis kejadian/perubahan** (bukan rekap tahunan `period_year` + `member_total`/`member_active`
> seperti draft awal). Alasan: periodisitas & definisi rekap belum dikonfirmasi; pencatatan per kejadian
> tidak mengunci desain dan tidak memerlukan data anggota individual. **TIDAK** menyimpan data anggota
> individual/NIK. Lihat `docs/DECISIONS.md` §10.

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| cooperative_id | BIGINT UNSIGNED | NN | FK → cooperatives.id, IDX | |
| event_date | DATE | NN | IDX | Tanggal kejadian/perubahan (≤ hari ini) |
| change_type | VARCHAR(30) | NN | IDX | **PROVISIONAL** — nilai dari `config('koperasi.member_change_type_options')` (`penambahan`/`pengurangan`/`lainnya`) |
| members_added | INT UNSIGNED | NULL | | Jumlah bertambah |
| members_removed | INT UNSIGNED | NULL | | Jumlah berkurang |
| notes | TEXT | NULL | | Catatan |
| recorded_by | BIGINT UNSIGNED | NN | FK → users.id | Diisi server-side |
| created_at / updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete (BR-011) — **ditambahkan** |

**Index:** (`cooperative_id`, `event_date`), `change_type`.
**Validasi (Store & Update disamakan):** `event_date` ≤ hari ini; **minimal salah satu** dari `members_added`/`members_removed` terisi; nilai ≥ 0; **keduanya tidak boleh nol** (pasangan `0,0` ditolak). Kedua nilai boleh terisi bersamaan. Pada Update, penilaian memakai nilai efektif (request ∪ nilai tersimpan) sehingga mengosongkan salah satu tetap mempertahankan nilai lainnya. Aturan ini PROVISIONAL (kualitas data) — lihat `docs/DECISIONS.md` §10.
**Riwayat (AC-PER-03):** daftar kejadian terurut terbaru-dulu + selisih bersih (bertambah − berkurang).
> **Cadangan:** struktur rekap tahunan draft awal tetap dipertahankan sebagai alternatif sampai Q-010 dikonfirmasi.

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
| **parent_submission_id** | BIGINT UNSIGNED | NULL | FK → submissions.id (self, `ON DELETE SET NULL`), IDX | **Versi induk** bila baris ini adalah versi perbaikan (FR-031/FR-142, keputusan D-1 opsi B — `DECISIONS.md` §17.1). NULL = pengajuan awal. Lihat catatan portabilitas di bawah. |
| module | VARCHAR(50) | NN | IDX | Modul data (mis. `koperasi_induk`, `license`, `asset`) |
| title | VARCHAR(200) | NULL | | Ringkasan pengajuan |
| status | ENUM('draft','submitted','under_review','needs_revision','approved','rejected','archived') | NN | IDX, default 'draft' | Lihat §12 PRD |
| reason | TEXT | NULL | | Alasan tolak/perbaikan terakhir |
| reviewed_by | BIGINT UNSIGNED | NULL | FK → users.id | Admin peninjau |
| reviewed_at | TIMESTAMP | NULL | | |
| applied_at | TIMESTAMP | NULL | | Saat diterapkan ke data resmi |
| created_at / updated_at | TIMESTAMP | NULL | | |
| deleted_at | TIMESTAMP | NULL | | Soft delete |

**Index:** `cooperative_id`, `status`, `module`, `submitted_by`, **`parent_submission_id`**.

> **`parent_submission_id` — semantik versi (D-1 opsi B, Tahap 63).** Revisi pengajuan `needs_revision`
> **tidak** menyunting baris yang sama; dibuat baris **baru** (`status = draft`) dengan
> `parent_submission_id` menunjuk versi induk. Baris induk tetap `needs_revision` (riwayat `submission_items`
> & `submission_reviews` utuh) dan **tidak** di-soft-delete; ia "digantikan" bila sudah punya anak. Aturan
> aplikasi: maksimum satu pengajuan aktif per `cooperative_id` + `module`.
>
> **Portabilitas:** kolom & indeks dibuat pada semua driver. **Self-FK** saat ini **hanya** dibuat pada
> MySQL/MariaDB karena migrasi memakai guard driver eksplisit (`supportsForeignKey()`). Ini **pilihan
> yang disengaja, bukan keterbatasan**: Laravel 13 sebenarnya **mendukung** penambahan FK pada SQLite lewat
> rebuild tabel (`SQLiteGrammar::getAlterCommands()` memuat `foreign`). Konsekuensinya self-FK **tidak**
> dilatih di suite tes SQLite — suite `ParentSubmissionForeignKeyTest` bersifat **SQLite-only** — dan belum
> diverifikasi di MariaDB; lihat `DECISIONS.md` §20.2, §21.3.
>
> **Idempotensi (Tahap 68, diperkuat Tahap 70):** `up()` memeriksa kolom, indeks, dan self-FK **terpisah** —
> eksekusi ulang setelah gagal sebagian akan melengkapi komponen yang hilang. `down()` melepas FK memakai
> nama constraint yang dilaporkan driver (bukan asumsi nama konvensional), lalu indeks, baru kolom.
>
> **Kepemilikan indeks (Tahap 70):** indeks dinilai dari **nama `submissions_parent_submission_id_index`
> + definisi** (kolom tunggal, non-unik, bukan primary key), bukan sekadar "ada indeks pada kolom".
> Indeks bernama lain — termasuk unik/komposit/primary key — **tidak pernah** dihapus oleh `down()`.
>
> **Rollback bersifat destruktif:** `down()` menghapus kolom `parent_submission_id`, sehingga **tautan
> rantai versi hilang permanen** (baris pengajuan tetap ada). Jalankan hanya dengan persetujuan eksplisit
> dan evaluasi dampak data. Pada MariaDB, `DROP INDEX` dapat gagal lebih awal (errno 1553) bila FK
> menyimpang masih bergantung pada indeks itu.
>
> **Catatan operasional:** `up()` memakai `->after('id')` — perilaku `ADD COLUMN ... AFTER` (instant vs
> rebuild) **perlu diverifikasi** terhadap versi & konfigurasi MariaDB target; belum ada pengukuran.
> Laravel tidak membungkus migrasi ini dalam transaksi skema (MySQL/MariaDB maupun SQLite) dan tidak
> menyediakan kunci migrasi → jalankan migrasi **terserialisasi**, bukan serentak.
>
> Migrasi baru ini **Pending** pada DB dev (belum dijalankan; isinya disempurnakan Tahap 68/70) — lihat
> `DECISIONS.md` §17.1, §21.

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

**Index:** UNIQUE (`submission_id`, `field`) — satu item per field per pengajuan (sekaligus memenuhi indeks `submission_id`).
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

> **Status implementasi 24 Oktober 2026:** tabel dibuat oleh migrasi aditif
> `2026_10_24_000003_create_settings_table`; migrasi **belum** dijalankan pada DB persisten.
> Saat ini key yang dipakai adalah `public.fields` (JSON pilihan field publik untuk FR-191).

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | BIGINT UNSIGNED | NN | PK | |
| key | VARCHAR(100) | NN | UK | Saat ini `public.fields` |
| value | TEXT / JSON | NULL | | JSON `{fields: string[], show_region: bool, contact: {email, telephone}}` |
| created_at / updated_at | TIMESTAMP | NULL | | |

**Contoh key:** `public.fields`.
**Catatan:** field yang disimpan tetap disaring terhadap allowlist Q-008 di
`App\Services\PublicFieldSettings`; tabel ini bukan mekanisme otorisasi. Keputusan publikasi per
koperasi tetap di `cooperatives.is_published`. Jangan menyimpan password/token/secret di sini.

---

## 19. `notifications` (notifikasi in-app — Laravel)

Struktur mengikuti migrasi standar Laravel (`php artisan notifications:table`), dipakai untuk FR-201
(kanal `database`; email/WA ditunda — Q-011).

| Kolom | Tipe | Null | Constraint | Keterangan |
| --- | --- | --- | --- | --- |
| id | CHAR(36)/UUID | NN | PK | |
| type | VARCHAR(255) | NN | | Kelas notifikasi |
| notifiable_type | VARCHAR(255) | NN | IDX (morph) | Kelas penerima (mis. `App\Models\User`) |
| notifiable_id | BIGINT UNSIGNED | NN | IDX (morph) | ID penerima |
| data | TEXT / JSON | NN | | `submission_id`, `status`, `status_label`, `title`, `message`, `reason`, `url` |
| read_at | TIMESTAMP | NULL | | Waktu dibaca |
| created_at / updated_at | TIMESTAMP | NULL | | |

**Index:** (`notifiable_type`,`notifiable_id`) — indeks morph bawaan migrasi Laravel. `read_at` **tidak** diindeks.
**Catatan:** hanya penerima dapat melihat & menandai dibaca notifikasinya (server-side).

---

## Ringkasan relasi (teks)

- `users` **N–M** `roles` (via `role_user`).
- `users` **N–1** `cooperatives` (pengelola → satu koperasi).
- `users` **N–1** `regions` (opsional, pembatasan wilayah).
- `regions` **self** (parent–child).
- `cooperatives` **1–N** `cooperative_licenses`, `documents`, `health_assessments`, `governance_records`, `cooperative_assets`, `member_histories`, `officers`, `submissions`, `cooperative_contacts`.
- `submissions` **1–N** `submission_items`, `submission_reviews`; **self 1–N** `submissions` (versi perbaikan lewat `parent_submission_id`, D-1 opsi B).
- `users` **1–N** `submissions`, `activity_logs`.

---

## Pemetaan FR → tabel utama

| FR | Tabel utama |
| --- | --- |
| FR-001–004 | users, roles, role_user; FR-003 = `sessions` (driver database), FR-004 = `password_reset_tokens` + mailer |
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
| FR-180-182 | cooperatives (`is_published`) + `regions` (label wilayah dari hierarki; dataset resmi tersedia, impor persisten belum dijalankan - Q-012) |
| FR-183 | Agregasi `cooperatives` + `regions` (hanya `is_published = true` dan `status_verifikasi = approved`); **tidak** memakai data pribadi. Statistik publik tersedia di `/statistik` (F8). |
| FR-190/191 | cooperatives (`is_published`, `published_at`, `published_by`, `unpublished_at`, `unpublished_by`, `updated_by`); field publik = subset `settings` (`public.fields`) yang dibatasi allowlist **Q-008** (`config('koperasi.public.allowed_fields')` + `PublicFieldSettings`). Sejak Tahap 106 allowlist mencakup `status_operasional` (label resmi saja) |
