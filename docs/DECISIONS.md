# Catatan Keputusan & Asumsi Sementara

Dokumen ini mencatat keputusan teknis **sementara** dan **pertanyaan terbuka** yang belum dikonfirmasi
dinas, agar fase berikutnya dapat menyesuaikan tanpa mengubah asumsi diam-diam. Rujukan utama tetap
`docs/PRD.md`; bila dokumen ini bertentangan dengan PRD, **PRD yang menang**.

Legend: **[NEEDS CONFIRMATION]** = menunggu jawaban dinas (lihat PRD §21), **[SEMENTARA]** = keputusan
teknis yang sengaja dibuat mudah diubah.

---

## 1. Pertanyaan dinas yang masih terbuka (P0)

| ID | Pertanyaan | Dampak pada F2 | Pendekatan sementara |
| --- | --- | --- | --- |
| Q-001 | Format & sumber resmi **nomor identitas koperasi** | Validasi `nomor_identitas` | Disimpan `VARCHAR(100)`. Divalidasi **wajib + unik** saja, **tanpa pola format** buatan. Menunggu format resmi untuk menambah `regex`. |
| Q-003 | Daftar resmi **jenis/bentuk koperasi** | Kolom `jenis_koperasi` | Disimpan `VARCHAR(100)` (bukan enum DB). Form memakai input teks; `config/koperasi.php` menyediakan daftar opsional (saat ini **kosong**) yang otomatis menjadi dropdown bila dinas menetapkan daftarnya. Validasi hanya `string`, tidak dibatasi daftar. |
| Q-012 | **Sumber & format referensi wilayah** | Tabel `regions`, `cooperatives.region_id` | Tabel `regions` dibuat tetapi **tidak di-seed** (tidak ada wilayah yang dikarang). `cooperatives.region_id` dibuat **`NULL` [SEMENTARA]**. Menjadi `NN` setelah wilayah resmi tersedia. |
| Q-006 | Field yang boleh diubah **pengelola** | FR-042 (pengajuan) | Di luar F2. Pengelola pada F2 hanya **membaca** profil (FR-041). |

---

## 2. Keputusan sementara pada implementasi F2

| # | Keputusan | Alasan | Cara menyesuaikan nanti |
| --- | --- | --- | --- |
| F2-1 | `cooperatives.region_id` **nullable** (dictionary menyebut `NN`) | Wilayah belum tersedia (Q-012) | Isi `RegionSeeder`, set `region_id` wajib, ubah kolom menjadi `NN` pada migrasi baru. |
| F2-2 | Kolom tambahan `cooperatives.archived_reason` (TEXT) | FR-043 mewajibkan alasan arsip, tetapi `activity_logs` baru ada di F5 | Pindahkan pencatatan alasan ke `activity_logs` (F5); kolom dapat dipertahankan atau dihapus. |
| F2-3 | `jenis_koperasi` berupa `VARCHAR`, bukan enum DB | Daftar resmi belum dikonfirmasi (Q-003) | Ganti ke enum/lookup setelah daftar resmi ditetapkan. |
| F2-4 | `status_verifikasi` dapat dipilih admin pada form (default `draft`), **kecuali `archived`** | FR-040 menyebut "status" sebagai data masukan; `archived` hanya lewat aksi arsip agar tidak ada data berstatus arsip yang masih aktif/terdaftar | F5 menambahkan **penjaga matriks transisi** (PRD §12.2). |
| F2-5 | Unik `nomor_identitas` berlaku termasuk data terarsip | BR-019 **[ASUMSI]** identitas unik lintas sistem | Sesuaikan bila dinas mengizinkan identitas dipakai ulang. |
| F2-6 | `slug` dibuat sekali saat pembuatan data, tidak ikut berubah saat nama diubah | Menjaga URL publik (F7) tetap stabil | Tambahkan aksi ubah slug manual bila diperlukan. |
| F2-7 | Pencatatan audit/riwayat perubahan (FR-150) **belum** diimplementasikan | Tabel `activity_logs` dijadwalkan F5 | FR-040 "perubahan tercatat di audit log" dipenuhi pada F5. |
| F2-8 | `users.koperasi_id` FK `ON DELETE SET NULL` (kolom nullable) | Hard delete jarang (operasional memakai soft delete); akun dilepas, bukan memblokir | Ganti ke `ON DELETE RESTRICT` bila kebijakan dinas menghendaki. |
| F2-9 | Isolasi akses memakai **Policy** `CooperativePolicy` (menggantikan Gate `access-koperasi` F1) | Policy lebih tepat untuk otorisasi berbasis model | — |

### 2.1 Batasan kontrol status pada F2 (belum ada matriks transisi)

- Perubahan status hanya melalui `POST/PUT /admin/koperasi*`, dilindungi middleware `role:admin_dinas` **dan** `CooperativePolicy` (`create`/`update` untuk admin). Pengelola dan tamu ditolak (403).
- `status_verifikasi` divalidasi `Rule::in(Cooperative::assignableStatuses())`; nilai `archived` **dikecualikan** dari form dan hanya ditetapkan oleh aksi arsip (nilai di-hardcode, bukan dari input pengguna).
- Tidak ada jalur lain yang menulis status: tidak ada endpoint ubah-status khusus dan tidak ada penulisan di luar controller di atas.
- **Belum** ada penjaga matriks transisi resmi (PRD §12.2) — dikecualikan dari lingkup F2 dan dijadwalkan pada F5. Karena aktor verifikasi resmi (§12.2) adalah **admin dinas**, kemampuan admin menetapkan status pada F2 **bukan** eskalasi hak akses.

### 2.2 Aturan pengarsipan (FR-043) pada F2

- Hanya admin berwenang (`role:admin_dinas` + `CooperativePolicy::delete`).
- Alasan wajib (`ArchiveCooperativeRequest`, `archived_reason` required, maks 1000 karakter).
- Arsip = set `status_verifikasi = 'archived'` + **soft delete** (`deleted_at`). Tidak ada alasan penghapusan permanen dan tidak ada endpoint hard delete.
- Data terarsip otomatis tersembunyi dari daftar dan **404** pada endpoint detail/edit/update (route model binding mengabaikan data ter-soft delete).
- FK `users.koperasi_id` `ON DELETE SET NULL` hanya bereaksi terhadap hard delete (yang tidak diekspos ke UI). Soft delete **tidak** melepas tautan pengelola di database, tetapi relasi `User::koperasi()` mengembalikan `null` (scope soft delete) sehingga pengelola melihat halaman "akun belum terhubung".

---

## 3. Penggantian kode F1

- Route placeholder F1 `GET /dashboard/koperasi/{koperasi}` beserta `Pengelola\KoperasiController` dan
  view `pengelola/koperasi.blade.php` **dihapus**, digantikan modul data induk F2:
  - Admin: `GET /admin/koperasi` (+ create/store/show/edit/update/destroy).
  - Pengelola: `GET /koperasi/profil` (read-only, halaman "akun belum terhubung" bila belum ditautkan).
- Gate `access-koperasi` digantikan `App\Policies\CooperativePolicy` (didaftarkan di `AppServiceProvider`).
- `php artisan user:create` kini memvalidasi `--koperasi`/ID koperasi harus ada (`exists:cooperatives,id`)
  mengikuti FK baru.

---

## 4. Referensi migrasi F2

| Migrasi | Isi |
| --- | --- |
| `2026_10_16_100001_create_regions_table.php` | Tabel `regions` (tanpa seeder). |
| `2026_10_16_100002_create_cooperatives_table.php` | Tabel `cooperatives` (+ `archived_reason` sementara). |
| `2026_10_16_100003_add_koperasi_foreign_key_to_users_table.php` | FK `users.koperasi_id` → `cooperatives.id` (`nullOnDelete`). |

---

## 5. Keputusan sementara pada implementasi F3 (perizinan & dokumen)

| # | Keputusan | Alasan | Cara menyesuaikan nanti |
| --- | --- | --- | --- |
| F3-1 | Perubahan status izin **hanya** lewat aksi verifikasi (`POST .../{license}/verifikasi`) yang menetapkan `status` ∈ {`active`,`expired`,`revoked`} + `verified_by`/`verified_at`. Status awal selalu `pending`; `status` tidak dapat diisi dari form tambah/ubah. | FR-051/061: status hanya berubah oleh admin berwenang; mencegah perubahan status lewat form biasa | Sesuaikan daftar status & matriks transisi setelah prosedur resmi dikonfirmasi (Q-004) |
| F3-2 | Izin **tidak** otomatis berubah menjadi `expired`; kadaluarsa ditandai indikator `isExpired()` dari `tanggal_berlaku_sampai` | FR-050 meminta kadaluarsa teridentifikasi dari tanggal; prosedur resmi belum dikonfirmasi (Q-004) | Tambahkan transisi otomatis bila dinas menetapkan aturannya |
| F3-3 | Unik (`cooperative_id`,`license_type`,`nomor`) berlaku **termasuk data terarsip** (soft delete), konsisten dengan indeks unik basis data | Asumsi **[ASUMSI]** analog BR-019 | Sesuaikan bila dinas mengizinkan nomor dipakai ulang setelah arsip |
| F3-4 | `documents.category` disimpan sebagai **teks bebas** (contoh: `akta_pendirian`,`izin_usaha`,`laporan_aset`) — bukan enum | Taksonomi kategori resmi belum ditetapkan | Ganti ke lookup/enum bila daftar resmi tersedia |
| F3-5 | Unggah dokumen (FR-070) pada F3 **hanya admin** (FLOW-ADM-05); pengelola mengunggah lewat pengajuan (FLOW-PGL-03) pada F5 | Konsisten dengan BR-014/§12.3 dan pola F2 (pengelola read-only) | Buka jalur unggah pengelola saat workflow pengajuan (F5) tersedia |
| F3-6 | Pengelola hanya **membaca** izin dan **mengunduh** dokumen koperasinya (FR-071, AC-DOC-03) | FR-050/060 menyebut pengelola "(usul)" yang butuh workflow F5 | Tambahkan pengajuan izin oleh pengelola pada F5 |
| F3-7 | Arsip izin & dokumen = **soft delete**; berkas fisik dokumen tetap disimpan (tidak dihapus) | BR-011 (pengarsipan, bukan penghapusan); kebijakan retensi belum dikonfirmasi (Q-013) | Tambahkan pembersihan berkas terjadwal bila kebijakan retensi ditetapkan |
| F3-8 | Dokumen disimpan di disk `local` (`storage/app/private/documents/{koperasi_id}/...`), tanpa symlink publik | NFR-SEC-009/010 (PRD §13.6) | — |
| F3-9 | Tabel `documents` polimorfik, tetapi F3 baru memakai `cooperative_licenses` sebagai `documentable` | Entitas lain (aset, kesehatan, dll.) menyusul pada fase berikutnya | Tambahkan jenis `documentable` per fase |
| F3-10 | Berkas dokumen **tidak** dihapus saat koperasi/izin diarsipkan | Menjaga bukti/jejak (BR-006, BR-011) | Sesuaikan dengan kebijakan retensi (Q-013) |

### 5.1 Batasan otorisasi F3

- Otorisasi **di sisi server**: middleware `role:admin_dinas`/`role:pengelola_koperasi` + Policy (`CooperativeLicensePolicy`, `DocumentPolicy`). Penyembunyian tombol/menu bukan kontrol akses.
- Route izin bersarang `admin/koperasi/{cooperative}/izin/{license}` memakai **scoped route model binding** → izin milik koperasi lain mengembalikan **404** (menyembunyikan keberadaan resource), bukan 403.
- Unduh dokumen memakai satu route `GET /dokumen/{document}/unduh` (middleware `auth`+`active`) dengan Policy: admin (semua) atau pengelola pemilik koperasi. Pengelola koperasi lain → **403** (AC-DOC-03).
- Data terarsip (soft delete) tidak muncul pada daftar dan mengembalikan **404** pada detail/unduh.
- Mass assignment dicegah: `cooperative_id`, `status`, `uploaded_by`, `file_path`, `documentable_*` ditetapkan di server, bukan dari input klien.

### 5.2 Seeder data demo (opt-in)

- `php artisan db:seed` **tetap** hanya meng-seed data referensi (`RoleSeeder`); seeder demo **tidak** terdaftar pada `DatabaseSeeder`.
- Data contoh untuk demonstrasi dibuat eksplisit: `php artisan db:seed --class=DemoSeeder` — menolak berjalan pada `APP_ENV=production`, tidak membuat akun, dan idempoten.
- Data contoh diberi penanda jelas (`nomor_identitas` berawalan `DEMO-`).

---

## 6. Referensi migrasi F3

| Migrasi | Isi |
| --- | --- |
| `2026_10_16_110001_create_cooperative_licenses_table.php` | Tabel `cooperative_licenses` (unik `cooperative_id`+`license_type`+`nomor`, soft delete). |
| `2026_10_16_110002_create_documents_table.php` | Tabel `documents` (polimorfik, FK ke koperasi/pengunggah, soft delete). |

---

## 7. Keputusan sementara pada implementasi F4 PARTIAL (pengurus koperasi)

| # | Keputusan | Alasan | Cara menyesuaikan nanti |
| --- | --- | --- | --- |
| F4-1 | **Hanya modul Officers diimplementasikan** pada F4 PARTIAL (FR-120/121) | Q-010 (periodisitas tahunan/semesteran/bulanan) belum terjawab — menunggu konfirmasi dinas untuk modul kesehatan, tata kelola, aset, dan anggota | Implementasi modul lain setelah Q-010 terjawab |
| F4-2 | Periode jabatan pengurus memakai date range (`period_start`, `period_end`) bukan `period_year` | Pengurus dapat menjabat lintas tahun dan multiple jabatan simultan; tidak tergantung Q-010 | — |
| F4-3 | Pengelola **read-only** untuk pengurus di F4 (hanya view/show) | Workflow pengajuan (F5) belum tersedia; konsisten dengan pola F2/F3 | Buka write access via workflow pengajuan pada F5 |
| F4-4 | `identity_number` pengurus dimasking dengan helper `maskedIdentityNumber()` | NFR-SEC-015: data pribadi tidak dipublikasikan; format: `3201****0123` | — |
| F4-5 | Soft delete untuk pengurus (riwayat jabatan tetap terjaga) | BR-011: pengarsipan bukan penghapusan permanen | — |
| F4-6 | Tidak ada unique constraint pada periode jabatan pengurus | Satu koperasi dapat memiliki multiple pengurus dengan jabatan dan periode yang berbeda atau tumpang tindih | — |

### 7.1 Batasan otorisasi F4

- Admin: akses penuh CRUD semua pengurus semua koperasi.
- Pengelola: read-only (view/show) pengurus koperasinya sendiri; tidak dapat create/update/delete (workflow F5 nanti).
- Route bersarang `admin/koperasi/{cooperative}/pengurus/{officer}` memakai **scoped binding** → pengurus koperasi lain → **404**.
- `OfficerPolicy` memastikan isolasi: pengelola koperasi A tidak dapat melihat pengurus koperasi B.
- `identity_number` tidak bocor: masked di UI admin, tidak ditampilkan ke pengelola/publik.

---

## 8. Referensi migrasi F4 PARTIAL

| Migrasi | Isi |
| --- | --- |
| `2026_10_16_022847_create_officers_table.php` | Tabel `officers` (nama, jabatan, `identity_number` nullable, `period_start`/`period_end`, `is_active`, soft delete). |

---

## 9. Status F4 & Modul yang Menunggu Konfirmasi Q-010

**Status implementasi F4:**
- ✅ **Officers (FR-120/121)**: Selesai 100% (tidak tergantung Q-010)
- ⏳ **Health Assessments (FR-080/081)**: Menunggu Q-010
- ⏳ **Governance Records (FR-090/091)**: Menunggu Q-010
- ⏳ **Cooperative Assets (FR-100/101)**: Menunggu Q-010
- ⏳ **Member Histories (FR-110/111)**: Menunggu Q-010

**Q-010 (P1 - Penting):** Periodisitas data (tahunan/semesteran/bulanan)?
- Mempengaruhi kolom `period` dan unique constraint pada 4 modul di atas.
- Tanpa jawaban Q-010, tidak dapat menentukan:
  - ENUM values untuk `period` (semester_1/semester_2/Q1/monthly_01/dll.)
  - Unique constraint: `(cooperative_id, period_year)` vs `(cooperative_id, period_year, period)`
  - Form input: dropdown semester/triwulan/bulan
  - UI label: "Tahun 2024" vs "Semester I 2024"

**Estimasi implementasi sisa F4 setelah Q-010 terjawab:** ~4-5 hari kerja.
