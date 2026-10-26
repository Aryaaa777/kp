# Catatan Keputusan & Asumsi Sementara

Dokumen ini mencatat keputusan teknis **sementara** dan **pertanyaan terbuka** yang belum dikonfirmasi
dinas, agar fase berikutnya dapat menyesuaikan tanpa mengubah asumsi diam-diam. Rujukan utama tetap
`docs/PRD.md`; bila dokumen ini bertentangan dengan PRD, **PRD yang menang**.

Legend: **[NEEDS CONFIRMATION]** = menunggu jawaban dinas (lihat PRD §21), **[SEMENTARA]** = keputusan
teknis yang sengaja dibuat mudah diubah.

> **Status terbaru (26 Oktober 2026):** F0–F8 sudah diimplementasikan dan diuji secara lokal; lihat
> **§33** untuk penyelesaian F8, impor wilayah lokal, dan batas yang masih terbuka. Catatan status
> pada §1–§32 adalah **jejak historis** pada tanggal masing-masing dan tidak boleh dibaca sebagai
> status terkini bila bertentangan dengan §33.

---

## 1. Pertanyaan dinas yang masih terbuka (P0)

| ID | Pertanyaan | Dampak pada F2 | Pendekatan sementara |
| --- | --- | --- | --- |
| Q-001 | Format & sumber resmi **nomor identitas koperasi** | Validasi `nomor_identitas` | Disimpan `VARCHAR(100)`. Divalidasi **wajib + unik** saja, **tanpa pola format** buatan. Menunggu format resmi untuk menambah `regex`. |
| Q-003 | Daftar resmi **jenis/bentuk koperasi** | Kolom `jenis_koperasi` | Disimpan `VARCHAR(100)` (bukan enum DB). Form memakai input teks; `config/koperasi.php` menyediakan daftar opsional (saat ini **kosong**) yang otomatis menjadi dropdown bila dinas menetapkan daftarnya. Validasi hanya `string`, tidak dibatasi daftar. |
| Q-012 | **Sumber & format referensi wilayah** | Tabel `regions`, `cooperatives.region_id` | Tabel `regions` kini dapat diisi dari **Kepmendagri 300.2.2-2138/2025** melalui `php artisan regions:import` (§30). Tidak ada wilayah yang dikarang. `cooperatives.region_id` **tetap `NULL` [SEMENTARA]** sampai audit/backfill tuntas; perubahan ke `NN` memerlukan migrasi baru. |
| Q-006 | Field yang boleh diubah **pengelola** | FR-042 (pengajuan) | Di luar F2. Pengelola pada F2 hanya **membaca** profil (FR-041). |

### 1.1 Status keputusan P0 setelah Tahap 107

| ID | Keputusan kebijakan | Status implementasi |
| --- | --- | --- |
| Q-008 | **DITERIMA** — field publik: nama, nomor badan hukum, tanggal berdiri, alamat sampai desa/kecamatan, jenis, status operasional; dilarang: NIK/telepon pribadi pengurus, omzet nonpublik, data personal anggota | **SELESAI** untuk field yang datanya tersedia (§28.1, §29). `status_operasional` tersedia sebagai kolom nullable (Tahap 106); teks bebas `alamat` tidak dipublikasikan (alamat = hierarki `regions`, menunggu Q-012). |
| Q-012 | **DITERIMA** — master data resmi Permendagri/BPS, hierarki wajib | **SEBAGIAN SELESAI** (§30): dataset resmi Kepmendagri 2025 + CSV + importer idempoten + form berjenjang + audit read-only tersedia. `regions` **belum** diimpor ke DB persisten; `region_id` tetap `NULL` dan **belum** `NN` sampai audit/backfill tuntas. |
| Q-014 | **DITERIMA** — Ubuntu/AlmaLinux LTS, MariaDB 10.11, `.go.id`, HTTPS, backup harian/mingguan ke remote | **SEBAGIAN TERBLOKIR INFRA** (§28.4): tidak ada akses staging/kredensial; runtime MariaDB 10.11.19 sudah diuji lokal; staging/HTTPS/SMTP/backup belum. Checklist disiapkan. |
| D-6 | **DITERIMA** — jejak publikasi dipertahankan; catat waktu+aktor publikasi & penarikan | **SELESAI** (§28.2). |

---

## 2. Keputusan sementara pada implementasi F2

| # | Keputusan | Alasan | Cara menyesuaikan nanti |
| --- | --- | --- | --- |
| F2-1 | `cooperatives.region_id` **nullable** (dictionary menyebut `NN`) | Wilayah belum tersedia saat F2; kini dataset resmi tersedia (§30), tetapi audit/backfill belum tuntas | Impor wilayah, audit `region_id`, backfill hanya yang deterministik, lalu ubah kolom menjadi `NN` pada migrasi baru. |
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
| `2026_10_16_100004_add_cooperative_foreign_key_to_officers_table.php` | FK `officers.cooperative_id` → `cooperatives.id` (`ON DELETE RESTRICT`); dipisah dari `022847` agar urutan FK benar pada MariaDB dan dilengkapi guard idempoten (lihat §13). |

---

## 9. Status F4 — Data Periodik

**Status implementasi F4 (semua modul):**
- ✅ **Officers (FR-120/121)**: selesai, tidak tergantung Q-010.
- ✅ **Health (FR-080/081)**: selesai dengan **aturan provisional** (lihat §10).
- ✅ **Governance (FR-090/091)**: selesai dengan **aturan provisional** (§10). FR-091 (evaluasi kepatuhan otomatis) tetap FASE-2.
- ✅ **Assets (FR-100/101)**: selesai dengan **aturan provisional** (§10).
- ✅ **Members (FR-110/111)**: selesai dengan **aturan provisional**, berbasis kejadian (§10).

> **Q-010 belum dikonfirmasi.** Implementasi memakai aturan sementara agar pekerjaan tidak terhambat.
> Aturan provisional dicatat pada §10 dan **harus ditinjau ulang** setelah dinas menjawab.

---

## 10. Keputusan provisional F4 — modul data periodik

> **PROVISIONAL — MENUNGGU KONFIRMASI DINAS.** Semua butir di bawah dapat berubah setelah Q-010 (± periodisitas),
> Q-007 (formula penilaian kesehatan), dan definisi indikator resmi dikonfirmasi. **Tidak ada** nilai di sini
> yang boleh dianggap sebagai ketentuan resmi dinas.

| # | Keputusan provisional | Alasan | Cara menyesuaikan nanti |
| --- | --- | --- | --- |
| F4-7 | **Kesehatan**: periodisitas **tahunan** (`period` default `annual`) | Menunggu Q-010 | Tambah opsi semester/triwulan pada `config('koperasi.period_options')`; unique tetap (`cooperative_id`,`period_year`,`period`) |
| F4-8 | Kesehatan: `score`/`predicate` **input manual opsional**, tanpa formula | Q-007 (formula resmi) belum ada | Tambah perhitungan resmi pada fase lanjutan |
| F4-9 | Kesehatan: `status='final'` hanyalah **penanda internal**, bukan pengesahan resmi | Menghindari klaim klasifikasi resmi | Sesuaikan setelah prosedur resmi ditetapkan |
| F4-10 | **Tata kelola**: periodisitas **tahunan**; `period` dibuat **NN** (draft awal menyebut NULL) | Agar unique (`cooperative_id`,`period_year`,`period`) benar-benar berlaku | Sesuaikan jika dinas menetapkan periode nullable/pilihan lain |
| F4-11 | Tata kelola: indikator = `meeting_count` + `summary` (contoh), bukan indikator resmi | Indikator resmi belum ditetapkan | Ganti dengan indikator resmi (FR-091, FASE-2) |
| F4-12 | **Aset**: **snapshot tahunan**; kolom tambahan `quantity`, `acquisition_date`, `condition` | Perlu struktur minimum untuk simulasi; tidak ada di draft awal | Sesuaikan dengan struktur resmi; hapus kolom yang tidak diperlukan |
| F4-13 | Aset: taksonomi jenis belum ada → `asset_type` teks bebas (opsi saran `asset_type_options` kosong) | Menghindari taksonomi buatan | Isi `asset_type_options` bila daftar resmi tersedia |
| F4-14 | Aset: **tanpa** integrasi akuntansi/perhitungan otomatis | Kebutuhan belum ditetapkan | Tambah bila dinas memerlukan |
| F4-15 | **Anggota**: pencatatan **per kejadian/perubahan** (menyimpang dari draft rekap tahunan) | Periodisitas/definisi rekap belum pasti; pendekatan kejadian tidak mengunci desain | Beralih ke rekap tahunan bila dinas menetapkan; struktur draft awal dipertahankan sebagai alternatif |
| F4-16 | Anggota: **tidak** mengumpulkan data anggota individual/NIK | Kebutuhan belum ditetapkan (minimalkan data pribadi) | Tambah hanya bila ada dasar kebutuhan resmi |
| F4-17 | **Pengelola read-only** untuk keempat modul (hanya index/show) | Workflow pengajuan (F5) belum ada; konsisten dengan F2/F3/F4-3 | Buka write access pengelola via workflow pengajuan (F5) |
| F4-18 | `cooperative_id` & `assessor_id`/`recorded_by` **tidak fillable**; ditetapkan server-side | Anti mass assignment/IDOR | — |
| F4-19 | Soft delete (`deleted_at`) **ditambahkan** pada keempat tabel (draft awal tidak menyertakannya) | BR-011: data penting diarsipkan, bukan dihapus permanen; konsisten pola aplikasi | Sesuaikan dengan kebijakan retensi (Q-013) |
| F4-20 | `period` & `status` disimpan **VARCHAR**, bukan ENUM DB | Daftar nilai belum final; menghindari migrasi destruktif untuk menambah nilai | Ubah ke ENUM/lookup setelah daftar resmi ditetapkan |
| F4-21 | **Keunikan periode tetap berlaku untuk baris terarsip**; **tidak** memakai unique parsial | Unique parsial bergantung engine & berisiko; integritas AC-PER-01 lebih penting. Konsekuensi: periode terarsip tidak bisa dibuat ulang | Bila Q-010 menetapkan lain, sesuaikan bersama kebijakan arsip |
| F4-22 | **Pemulihan admin** untuk kesehatan & tata kelola: route `.../{resource}/pulihkan` (`withTrashed()`), otorisasi Policy `restore()`, hanya admin, tetap scoped (anti-IDOR) | Karena baris terarsip tidak bisa dibuat ulang, perlu jalan pemulihan; hanya modul ber-unique periode yang memerlukannya | Tambah pemulihan untuk modul lain bila diperlukan |
| F4-23 | Validasi jumlah keanggotaan disamakan Store & Update: minimal satu terisi, ≥ 0, **tidak keduanya nol**; Update memakai **nilai efektif** (request ∪ tersimpan) | Mencegah baris tanpa makna (0,0) tanpa aturan resmi baru | Sesuaikan setelah dinas menetapkan aturan resmi |

### 10.1 Batasan otorisasi F4 (modul periodik)

- Otorisasi **di sisi server**: middleware `role:admin_dinas`/`role:pengelola_koperasi` + Policy (`HealthAssessmentPolicy`, `GovernanceRecordPolicy`, `CooperativeAssetPolicy`, `MemberHistoryPolicy`).
- Route admin bersarang `admin/koperasi/{cooperative}/{modul}/{resource}` memakai **scoped route model binding** → resource milik koperasi lain → **404** (anti-IDOR). Nama parameter route diselaraskan dengan nama relasi (`{healthAssessment}`→`healthAssessments()`, `{governanceRecord}`→`governanceRecords()`, `{asset}`→`assets()`, `{memberHistory}`→`memberHistories()`).
- Pengelola hanya index/show koperasinya sendiri; mengakses data koperasi lain → **403**; menulis lewat route admin → **403** (middleware role). Penulisan hanya admin (BR-010 untuk kesehatan).
- Guest ditolak (redirect login). Mass assignment dicegah: `cooperative_id`, `assessor_id`, `recorded_by` ditetapkan di server.

### 10.2 Kebijakan arsip (soft delete) & pemulihan

- `destroy` = **arsipkan** (soft delete, BR-011); tidak ada penghapusan permanen dari UI.
- Baris terarsip **tidak** dapat diakses endpoint biasa (show/edit) maupun oleh pengelola; `withTrashed()`/`onlyTrashed()` hanya dipakai pada alur arsip/pemulihan.
- Karena keunikan (`cooperative_id`,`period_year`,`period`) **tidak membedakan** `deleted_at`, periode terarsip tidak dapat dibuat ulang. Solusinya **memulihkan** baris terarsip melalui aksi admin (F4-22).
- Halaman index admin menyediakan filter `?arsip=1` untuk melihat dan memulihkan baris terarsip (hanya muncul untuk aktor berwenang).
- Aset & riwayat keanggotaan tidak memiliki unique periode, sehingga tidak memerlukan alur pemulihan khusus (baris baru tetap dapat dibuat).

---

## 11. Referensi migrasi F4

| Migrasi | Isi |
| --- | --- |
| `2026_10_16_022847_create_officers_table.php` | Tabel `officers` (soft delete). |
| `2026_10_16_120001_create_health_assessments_table.php` | `health_assessments` — unique (`cooperative_id`,`period_year`,`period`); soft delete. |
| `2026_10_16_120002_create_governance_records_table.php` | `governance_records` — unique (`cooperative_id`,`period_year`,`period`); soft delete. |
| `2026_10_16_120003_create_cooperative_assets_table.php` | `cooperative_assets` — snapshot tahunan; soft delete. |
| `2026_10_16_120004_create_member_histories_table.php` | `member_histories` — berbasis kejadian; soft delete. |

> Migrasi **belum dijalankan** pada database pengembangan (hanya diuji pada SQLite in-memory). Dijalankan
> pada lingkungan pengembangan/produksi hanya setelah persetujuan.

### 11.1 Seeder demo (opt-in) — modul periodik

- `DemoSeeder` diperluas mengisi contoh keempat modul untuk koperasi `DEMO-KOP-0001`.
- Tetap **opt-in** (`php artisan db:seed --class=DemoSeeder`), **idempoten**, menolak `APP_ENV=production`, tidak membuat akun, dan tidak menyentuh data koperasi milik pengguna.
- Modul dengan FK `recorded_by` wajib (tata kelola/aset/anggota) dilewati bila belum ada akun; kesehatan tetap diisi (`assessor_id` nullable).
- Foreign key non-fillable (`cooperative_id`, `assessor_id`, `recorded_by`, `uploaded_by`) dan `deleted_at` diisi **eksplisit** — seeder **tidak** bergantung pada `Model::unguarded()` milik perintah Artisan (mass-assignment model tetap terjaga).
- Baris demo yang sebelumnya diarsipkan **dipulihkan** (pencarian memakai `withTrashed()`), bukan diduplikasi, sehingga seeder tetap idempoten setelah proses arsip/pemulihan.

---

## 12. Keputusan fase F5 — Workflow Pengajuan & Verifikasi + Audit

| # | Keputusan | Alasan | Tindak lanjut |
| --- | --- | --- | --- |
| F5-1 | Status pengajuan memakai himpunan PRD §12.2: `draft`, `submitted`, `under_review`, `needs_revision`, `approved`, `rejected`, `archived`. | PRD §12.2, DATA_DICTIONARY §14, dan USER_FLOWS §1 konsisten. | Bila dinas menambah status, perbarui `Submission::STATUSES`/`TRANSITIONS`. |
| F5-2 | Nama status **`needs_revision`** (bukan `revision_requested`). | Nama resmi mengikuti PRD §12.2 + DATA_DICTIONARY §14 (sumber kebenaran). Dokumen proyek sudah memakai `needs_revision`; `docs/PROMPT_PACK.md` masih memakai `revision_requested`. | Selaraskan `docs/PROMPT_PACK.md` bila perlu. |
| F5-3 | Transisi status dijaga **di lapisan service** (`Submission::TRANSITIONS`); transisi di luar matriks → `SubmissionTransitionException`. | PRD §12.2: "Transisi di luar tabel ditolak oleh lapisan service". | Tambah tahap bila Q-004 menetapkan verifikasi berjenjang. |
| F5-4 | Verifikasi **satu tingkat** oleh `admin_dinas` (MVP). | Q-004 belum dikonfirmasi; alur FLOW-ADM-03 tetap terpenuhi. | Tinjau ulang setelah Q-004. |
| F5-5 | Perbaikan (`needs_revision` → `submitted`) dilakukan pada **pengajuan yang sama**; riwayat terjaga via `submission_reviews` + audit `activity_logs`. | PRD §12.2 menetapkan transisi `needs_revision → submitted` (kontrak state), sementara FR-031/FR-142 menyebut "versi pengajuan baru" (ambigu). | Bila dinas menghendaki versioning pengajuan terpisah, tambahkan relasi `parent_submission_id`. |
| F5-6 | **Whitelist modul & field** (Q-006) bersifat sementara dan digerakkan konfigurasi: `config('koperasi.submission_modules')` + handler per modul. Saat ini hanya modul `koperasi_induk` (FR-042). | Q-006 belum dikonfirmasi; menghindari mengunci desain & menghindari klaim kewenangan pengelola yang belum resmi. | Tambah modul (izin, data periodik) setelah dinas menetapkan field yang boleh diajukan. |
| F5-7 | Field data induk yang **tidak** boleh diajukan pengelola: `nomor_identitas`, `region_id`, `status_verifikasi`, `is_published`, `slug`. | Field otoritatif/kewenangan admin; `region_id` belum tersedia (Q-012). | Sesuaikan setelah Q-006. |
| F5-8 | Pengajuan hanya menyimpan **field yang berubah** (`old_value` ≠ `new_value`); field yang tidak dikirim dianggap tidak diajukan. Pengajuan tanpa perubahan ditolak (`EmptySubmissionException`). | Menghindari item kosong/tak bermakna; memperjelas diff verifikasi. | — |
| F5-9 | Penerapan ke data resmi (`approved`) dijalankan dalam **satu DB transaction**; bila `handler->apply()` gagal → rollback, status tetap `under_review`. | FR-143: "dalam transaksi DB"; mencegah setengah-terapkan. | — |
| F5-10 | `destroy`/arsip = **status `archived` + soft delete (`deleted_at`)** dalam satu transaksi (bukan hapus permanen). Riwayat (item, review, audit) tetap tersimpan; pengajuan arsip tidak diakses melalui endpoint normal (404) dan tidak muncul di daftar default. | BR-011; diselaraskan dengan pola arsip modul lain (`cooperative`, F4). | Terminal (tidak ada restore; `SubmissionPolicy::restore` = false). Bila dinas menghendaki daftar/restore arsip, tambahkan endpoint `withTrashed`. |
| F5-11 | Notifikasi status pengajuan: **kanal `database` saja** (in-app); ditulis saat `approved`/`needs_revision`/`rejected`. | Q-011 (kanal notifikasi) belum dikonfirmasi; MVP in-app. | Tambah email/WA setelah Q-011. |
| F5-12 | `activity_logs` **append-only**: ditegakkan pada model (`updating`/`deleting` → `LogicException`) dan Policy (`create`/`update`/`delete` = false); tidak ada route mutasi. | NFR-SEC-012 & AC-AUD-02. | — |
| F5-13 | Cakupan audit saat ini: workflow pengajuan (create/update/submit/start-review/approve/needs_revision/rejected/archive), CRUD+arsip data induk koperasi (memenuhi F2-7/FR-040), login (FR-151), verifikasi izin (FR-051), unduh dokumen (FR-151). | Membatasi perubahan pada modul yang sudah ditinjau; fokus aksi sensitif. | Perluas ke CRUD sensitif modul lain pada fase lanjutan bila dinas meminta. |
| F5-14 | Admin **tidak** membuat pengajuan (hanya meninjau); pembuatan pengajuan = pengelola. | PRD §8.2 (baris "Workflow pengajuan": Admin = R/V; Pengelola = C/R miliknya). | Sesuaikan bila dinas menghendaki admin juga mengajukan. |
| F5-15 | **Konkurensi (M1):** setiap operasi tulis membuka transaksi, mengambil ulang record dengan `lockForUpdate()`, memeriksa transisi atas status terbaru di dalam transaksi, lalu menulis dengan **conditional update** (`where status = <lama>`) sebagai penjaga optimistik. Review, perubahan data resmi, audit, dan notifikasi tidak ganda. | Mencegah dua keputusan bersamaan sama-sama berhasil; `lockForUpdate()` no-op pada SQLite sehingga lapis conditional update tetap diperlukan. | `lockForUpdate()` efektif pada MySQL/MariaDB; belum dibuktikan lewat eksekusi langsung (dev DB belum dimigrasi). |
| F5-16 | **Defense-in-depth whitelist (M2):** `KoperasiIndukHandler::apply()` menyaring ulang nilai terhadap `fields()` tepat sebelum menulis; field tak berizin diabaikan dan dicatat sebagai `ignored_fields` pada audit. | Field sensitif (`nomor_identitas`, `region_id`, `status_verifikasi`, `is_published`, `slug`) tidak boleh diterapkan walau item disuntikkan di luar alur normal. | Jika handler baru ditambah, ikuti pola penyaringan yang sama. |
| F5-17 | **Kegagalan notifikasi (L2):** notifikasi dikirim setelah transaksi commit; kegagalan ditangkap, dilaporkan (`report()`), dan dicatat sebagai aksi `notification_failed` — tidak membatalkan keputusan. | Keputusan yang sudah commit tidak boleh tampak gagal; kegagalan tetap dapat didiagnosis. | Belum ada retry/outbox; jangan mengklaim jaminan pengiriman ulang. |
| F5-18 | **Target koperasi tidak tersedia (L3):** `approve` menolak (rollback) bila relasi `cooperative` tidak ada; halaman edit pengajuan mengembalikan **404** (bukan 500). | Mencegah penerapan ke target yang tidak valid dan error tak tertangani. | — |
| F5-19 | **Unique item (L4):** `submission_items` memiliki unique `(submission_id, field)` (indeks unik ini juga memenuhi kebutuhan indeks `submission_id`). | Satu item per field per pengajuan. | Migration F5 belum dijalankan pada DB aktif; perubahan aman karena belum diterapkan. |
| F5-20 | **Validasi alasan (L5):** `reason` di-trim sebelum validasi sehingga string hanya-spasi ditolak; nilai valid disimpan konsisten pada `submissions.reason` & `submission_reviews.note`. | AC-WF-03/BR-005. | — |

### 12.1 Otorisasi & keamanan F5

- Otorisasi **di sisi server**: middleware `role:admin_dinas`/`role:pengelola_koperasi` + Policy `SubmissionPolicy` (`view`, `create`, `update`, `submit`, `archive`, `review`) dan `ActivityLogPolicy` (hanya baca).
- Isolasi antar koperasi (anti-IDOR): pengelola hanya mengakses pengajuan `cooperative_id` miliknya → pengajuan koperasi lain **403**.
- Mass assignment: `cooperative_id`, `submitted_by` (`submissions`) dan `submission_id`, `user_id` (item/review) **tidak fillable** — ditetapkan server-side.
- Field penerapan diambil dari handler modul (whitelist), bukan dari input mentah; disaring ulang di handler (F5-16).
- Konkurensi (F5-15): transaksi + `lockForUpdate()` + pemeriksaan status terbaru di dalam transaksi + conditional update optimistik; notifikasi dikirim setelah commit (F5-17).

### 12.2 Referensi migrasi F5

| Migrasi | Isi |
| --- | --- |
| `2026_10_16_130001_create_submissions_table.php` | `submissions` (soft delete; status ENUM PRD §12.2). |
| `2026_10_16_130002_create_submission_items_table.php` | `submission_items` (field, old_value, new_value; unique `submission_id` + `field`). |
| `2026_10_16_130003_create_submission_reviews_table.php` | `submission_reviews` (append-only: hanya `created_at`). |
| `2026_10_16_130004_create_activity_logs_table.php` | `activity_logs` (append-only). |
| `2026_10_16_130005_create_notifications_table.php` | `notifications` (Laravel, in-app; FR-201). |

> Seluruh migrasi F5 **belum dijalankan** pada database pengembangan (hanya diuji pada SQLite in-memory).
>
> **Diperbarui Tahap 56:** kelima migrasi F5 **sudah dijalankan** pada `koperasi_jabar` (Tahap 53 →
> 25 tabel / 22 migrasi / 28 FK / batch 6). Keterangan di atas berlaku untuk keadaan **sebelum** Tahap 53.

---

## 13. Guard foreign key `officers.cooperative_id` & kebijakan rollback (perbaikan F4)

### 13.1 Mengapa FK officers dipisahkan ke migrasi baru

Migrasi pembuatan `officers` (`2026_10_16_022847_create_officers_table.php`) memiliki stempel waktu yang
berjalan **sebelum** `2026_10_16_100002_create_cooperatives_table.php`. FK inline `cooperative_id` →
`cooperatives.id` karenanya menunjuk tabel induk yang belum ada → **`errno 150`** saat instalasi dari nol
pada MariaDB/MySQL. SQLite **tidak** menegakkan keberadaan tabel induk saat `CREATE TABLE`, sehingga
masalah hanya muncul di MariaDB (terbukti Tahap 33).

Perbaikan (Tahap 34) memindahkan pembuatan constraint ke migrasi baru
`2026_10_16_100004_add_cooperative_foreign_key_to_officers_table.php` (berjalan setelah 100002), **tanpa**
mengubah nama/stempel waktu migrasi lama. Kolom `officers.cooperative_id` dan index-nya tetap di migrasi
`022847`; hanya constraint FK yang dipindah. Guard menjaga idempotensi agar DB yang FK-nya sudah ada
(mis. DB pengembangan) tidak gagal (Tahap 36/38).

> Perubahan pada berkas migrasi lama itu sendiri (penghapusan FK inline) dijalankan sebagai
> **pengecualian sempit yang tercatat** — lihat §14 (alasan, bukti, dan batasnya).

### 13.2 Mengapa guard memvalidasi definisi constraint, bukan sekadar keberadaan FK

Versi awal guard hanya memeriksa ada-tidaknya FK yang memuat kolom `cooperative_id`. Itu terlalu longgar:
FK pada kolom yang sama tetapi menunjuk tabel/kolom lain, atau memakai aturan `CASCADE`/`SET NULL`, akan
dianggap "sudah benar" sehingga skema yang salah lolos. Guard sekarang mengklasifikasikan keadaan FK
(`classify()`) sebagai berikut.

| Keadaan | Syarat | Tindakan `up()` |
| --- | --- | --- |
| `present` | Semua FK pada `cooperative_id` persis `→ cooperatives.id`, satu kolom, ON DELETE/ON UPDATE memblokir | Lewati penambahan (idempoten) |
| `missing` | Tidak ada FK pada `cooperative_id` | Tambahkan FK sesuai rancangan |
| `conflict` | Ada FK pada `cooperative_id` tetapi definisinya tidak sesuai/ambigu | **Hentikan** dengan pesan diagnostik |

- Pencocokan memakai **definisi** (kolom anak, tabel & kolom referensi, aturan referensial), **bukan**
  nama constraint — nama yang sama tidak membuktikan kepemilikan.
- Lebih dari satu FK pada kolom yang sama diperlakukan **konservatif**: `present` hanya bila semuanya
  sesuai; satu yang menyimpang → `conflict`.
- `RESTRICT` dan `NO ACTION` diterima **setara** (keduanya memblokir operasi induk). MariaDB/InnoDB
  melaporkan `RESTRICT`; SQLite melaporkan `NO ACTION` bila klausa `ON UPDATE` tidak ditulis eksplisit
  (ditemukan saat pengujian Tahap 39). Aturan `CASCADE`/`SET NULL`/`SET DEFAULT` tetap ditolak.

### 13.3 Kebijakan `down()` dan konsekuensi rollback parsial

`down()` **tidak menghapus FK** (no-op). Tidak ada penanda persisten yang membedakan FK yang dibuat
migrasi ini dari FK yang sudah ada sebelum migrasi dijalankan (mis. dibuat versi lama migrasi `officers`
pada DB pengembangan). Karena nama constraint yang sama bukan bukti kepemilikan, penghapusan berisiko
menghilangkan constraint lama yang bukan milik migrasi ini.

| Skenario | Akibat |
| --- | --- |
| Rollback penuh / instalasi dari nol | FK ikut hilang saat tabel `officers` (`022847`) di-rollback. |
| Rollback parsial (hanya migrasi ini) | FK yang benar tetap ada; `migrate` lagi → guard `present` → idempoten. |
| DB lama (FK sudah ada sebelum 100004) | FK **tidak** disentuh. |

Trade-off: `down()` tidak sepenuhnya membalik `up()`. Ini diterima demi aturan "jangan menghapus
constraint yang mungkin bukan milik migrasi ini" (keamanan > reversibilitas ketat). Bila FK benar-benar
perlu dilepas, lakukan manual (`ALTER TABLE officers DROP FOREIGN KEY ...`) setelah memverifikasi
kepemilikannya.

### 13.4 Batas pembuktian: tes SQLite vs pengujian MariaDB

- Tes otomatis proyek berjalan pada **SQLite in-memory** (`phpunit.xml`); metadata FK-nya berbeda
  (mis. `on_update` default `NO ACTION`, tidak menegakkan keberadaan tabel induk saat `CREATE TABLE`).
- Karena itu: urutan migrasi diuji lewat nama berkas, logika guard (`classify()`) diuji unit dengan
  metadata representatif, dan perilaku nyata pada MariaDB **tidak** disimpulkan dari SQLite.
- Bukti MariaDB 10.4.32 diperoleh terpisah: instalasi dari nol berhasil (Tahap 36), definisi FK aktual
  lewat `information_schema` (`ON DELETE/UPDATE RESTRICT`), jalur skip end-to-end dengan 0 DDL
  (Tahap 38), dan `classify()` atas metadata asli = `present` pada `koperasi_jabar` serta
  `koperasi_jabar_test` (Tahap 39, read-only).

### 13.5 Referensi migrasi F4 (pembaruan)

| Migrasi | Isi |
| --- | --- |
| `2026_10_16_100004_add_cooperative_foreign_key_to_officers_table.php` | FK `officers.cooperative_id` → `cooperatives.id` (`ON DELETE RESTRICT`), dipindah dari `022847`; guard idempoten + pesan konflik; `down()` no-op. |

---

## 14. Pengecualian terdokumentasi atas kebijakan migrasi lama — `022847` (Tahap 47)

> **KEPUTUSAN KHUSUS — BUKAN IZIN UMUM.** Default `AGENTS.md` §4.7 / `CLAUDE.md` §4.7 tetap berlaku:
> migrasi lama yang sudah dijalankan tidak boleh diubah/dihapus. Catatan ini adalah pengecualian
> **sesempit mungkin** untuk **satu** cacat pada **satu** berkas, dengan syarat bukti yang terpenuhi.

### 14.1 Masalah yang memicu pengecualian

Migrasi `2026_10_16_022847_create_officers_table.php` membuat FK inline `cooperative_id` →
`cooperatives(id)`, padahal tabel induk `cooperatives` baru dibuat oleh `2026_10_16_100002`
(stempel waktu lebih besar). Pada instalasi bersih **MariaDB/MySQL**, `CREATE TABLE officers`
gagal `errno 150` sehingga **instalasi dari nol tidak berfungsi**; SQLite (lingkungan tes) tidak
menegakkan keberadaan tabel induk, sehingga cacat ini tidak terdeteksi oleh suite.

### 14.2 Bukti (bukan dugaan)

- **Reproduksi kegagalan:** instalasi dari nol pada MariaDB gagal `errno 150` (Tahap 33).
- **Perbaikan sesempit mungkin:** FK dipindah ke migrasi korektif baru
  `2026_10_16_100004_add_cooperative_foreign_key_to_officers_table.php`; nama/stempel waktu
  `022847` dipertahankan, hanya FK inline yang dihapus (kolom & indeks tetap di `022847`).
- **Keberhasilan perbaikan:** `php artisan migrate` dari nol pada `koperasi_jabar_test` → **22/22
  migrasi sukses**, 25 tabel, FK `officers_cooperative_id_foreign` `ON DELETE/UPDATE RESTRICT`
  (Tahap 36); jalur skip guard end-to-end tanpa DDL (Tahap 38); `classify()` = `present` atas
  metadata MariaDB nyata (Tahap 39).
- **Bukti independen (Tahap 46, MariaDB 10.4.32 / Laravel 13.36.0):** database eksperimen
  `koperasi_jabar_dumpexp_20261019` dibangun dari nol → 22/22 migrasi, 25 tabel, 28 FK;
  `schema:dump` menghasilkan dump 30.154 byte (SHA-256 `ED9E035B…B483A`) berisi 25 `CREATE TABLE`,
  28 `FOREIGN KEY`, `SET FOREIGN_KEY_CHECKS=0` (tetap ada meski `--skip-comments`), dan 22 baris
  data `migrations`; DB dikosongkan lalu dimuat via `migrate --schema-path` → **"Loading stored
  database schemas."**, 25 tabel, 28 FK, DDL `officers`/`cooperatives` identik dengan hasil
  migrasi penuh, 22 migrasi `batch 1`, tanpa replay.
- **Tes regresi:** `tests/Feature/OfficerForeignKeyTest.php` (18 tes) mengunci urutan migrasi,
  ketiadaan FK inline di `022847`, dan kebijakan guard (`present`/`missing`/`conflict`, `down()`
  no-op).

### 14.3 Keputusan

Kebijakan §4.7 diperjelas dengan **klausa pengecualian sempit** (dicatat pada `AGENTS.md`/
`CLAUDE.md` §4.7 dan berlaku sejak Tahap 47). Perubahan pada `022847` dinyatakan **patuh** sebagai
pengecualian terdokumentasi, dengan batas berikut.

### 14.4 Batas pengecualian

1. Hanya untuk cacat terverifikasi yang membuat **instalasi bersih gagal** pada database/versi yang
   didukung proyek (saat ini MariaDB/MySQL).
2. Wajib ada bukti reproduksi **dan** bukti keberhasilan perbaikan.
3. Perbaikan harus sesempit mungkin: nama/stempel waktu dipertahankan; objek/constraint baru dibuat
   lewat **migrasi korektif baru**, bukan ditambahkan ke migrasi lama.
4. Wajib disertai **tes regresi** dan catatan dampak (dokumen ini).
5. **Tidak** mengizinkan penghapusan data, rollback massal, `migrate:fresh` pada DB berisi data,
   atau perubahan database produksi otomatis.
6. Tidak boleh dipakai untuk merapikan kode, mengubah konvensi, atau menghindari migrasi korektif.
7. Setiap pengecualian baru harus dicatat sebagai keputusan khusus tersendiri — **tidak** menjadi
   preseden otomatis.

### 14.5 Dampak

| Lingkungan | Dampak |
| --- | --- |
| Database lama yang FK-nya sudah ada (`koperasi_jabar`, `koperasi_jabar_test`) | Migrasi `100004` **no-op** (guard `present`); tidak ada DDL. `koperasi_jabar` belum mencatat `100004` (Tahap 40) → saat dijalankan hanya menambah baris migrasi, 0 DDL. |
| Instalasi baru (MariaDB dari nol) | Berhasil 22/22; constraint FK dibuat oleh `100004` setelah `cooperatives` ada. |
| SQLite (tes) | Perilaku tetap; tes mengunci urutan & guard, bukan perilaku MariaDB. |

### 14.6 Mengapa schema dump bukan solusi utama

Schema dump (diuji Tahap 46) **lebih bergantung** pada baseline yang sudah benar, kehadiran binary
client (`mysqldump`/`mysql`) di `PATH`, dan disiplin regenerasi dump; riwayat migrasi juga tidak lagi
membangun skema MariaDB dari nol, sehingga drift tidak terdeteksi oleh tes SQLite. Karena itu
perbaikan migrasi (dengan pengecualian tercatat) dipilih sebagai solusi, dan schema dump **belum**
diadopsi.

### 14.7 Risiko / pekerjaan yang masih tersisa

- `022847` + `100004` + tes regresi + §13/§14 **dikomit** dalam satu commit lokal
  (`fix: resolve officers cooperative foreign key migration order`); **belum di-push** ke remote.
- 10 migrasi pending pada `koperasi_jabar` (termasuk `100004`) belum dijalankan; keputusan menjalankan
  ada pada pemilik.
- Guard `conflict` **sengaja** menghentikan migrasi (bukan memperbaiki otomatis) bila menemukan FK
  dengan definisi berbeda; skema seperti itu harus diperiksa manual.
- Perilaku MariaDB pada instalasi dari nol dibuktikan terpisah (Tahap 36/46), bukan dari tes SQLite.
- Pengujian konkurensi/`lockForUpdate()` pada MariaDB masih terpisah.
- **Diperbarui Tahap 56:** 10 migrasi pending pada `koperasi_jabar` (termasuk `100004`) **sudah
  dijalankan** (Tahap 53) → 25 tabel, 22 migrasi tercatat, 28 FK, batch 6. Butir di atas hanya berlaku
  untuk keadaan sebelum Tahap 53.

---

## 15. Keputusan fase F6 — Dashboard, Pencarian, Laporan Dasar (Tahap 55/56)

> Enam pertanyaan *decision gate* (Tahap 55) telah diputuskan **pemilik proyek** pada Tahap 56. Catatan ini
> menetapkan cakupan, definisi, dan batasan F6. **F6 belum diimplementasikan** — tidak ada satu pun
> deliverable kode F6 yang selesai; status ini bukan pernyataan bahwa F6 selesai.

| # | Keputusan | Alasan | Tindak lanjut |
| --- | --- | --- | --- |
| F6-1 | **F4 & F5 diterima** → Definition of Ready F6 terpenuhi. **Penerimaan ini tidak mencakup push.** Menurut referensi lokal, `origin/main` terakhir diketahui pada `009a29f` sedangkan HEAD lokal pada `2694618`, sehingga **satu** commit lokal (`2694618`, `fix: resolve officers cooperative foreign key migration order`) belum ada pada referensi lokal `origin/main`; pekerjaan F6 yang belum dikomit juga belum dipublikasikan. | `AGENTS.md` §7 / `IMPLEMENTATION_PLAN.md` §5: fase sebelumnya harus diuji lalu ditinjau sebelum fase berikutnya dimulai. Push adalah keputusan terpisah yang belum pernah disetujui. | Push hanya setelah persetujuan eksplisit pemilik. **Koreksi Tahap 77:** klaim lama bahwa keempat commit (`07109b9`, `a159e63`, `009a29f`, `2694618`) seluruhnya tetap lokal tanpa push tidak lagi dipakai — lihat catatan di bawah tabel. |
| F6-2 | **"Data terverifikasi" = `cooperatives.status_verifikasi = 'approved'`.** Laporan/rekapitulasi (FR-170) **hanya** mengagregasi status ini; `draft`/`submitted`/`under_review`/`needs_revision`/`rejected`/`archived` tidak boleh masuk agregasi. | AC-REP-01 (`PRD.md` §10.7): "Rekap hanya menghitung data terverifikasi"; daftar status resmi `PRD.md` §12.1. Data terarsip otomatis tersingkir karena `SoftDeletes` pada `Cooperative`. | Bila kelak laporan memuat **data periodik** (aset/anggota/kesehatan), definisikan "terverifikasi" secara terpisah — jangan pinjam definisi ini tanpa keputusan baru. |
| F6-3 | **Periode laporan = tahun `cooperatives.created_at`** — **[SEMENTARA]/PROVISIONAL**, dan **tidak** dicampur dengan `period_year` data periodik (kesehatan/tata kelola). | `PRD.md` FR-020 & FR-170 menyebut "periode" **tanpa definisi**; Q-010 belum dijawab; `cooperatives` tidak memiliki kolom periode. Ini asumsi kerja, **bukan** keputusan bisnis final. | Tinjau ulang setelah Q-010 terjawab. Catatan teknis: `created_at` **belum berindeks**; bila kelak menjadi masalah kinerja, tambahkan indeks lewat **migrasi baru** pada fase lain (F6 sendiri tidak menambah migrasi). |
| F6-4 | **Dimensi wilayah masuk F6** (statistik + filter). `region_id` NULL ditampilkan sebagai **"Belum ditetapkan"**. **Tidak ada seeding** ke database dev; pengujian memakai **factory** (`RegionFactory` sudah tersedia). | Q-012 belum dijawab sehingga `regions` sengaja kosong (0 baris) dan `cooperatives.region_id` NULL (`DECISIONS` §1, §2 F2-1); `AGENTS.md` §4.6 melarang data dummy. | Isi `RegionSeeder` + jadikan `region_id` wajib **setelah** Q-012; jangan mengarang kode/nama wilayah. |
| F6-5 | **Cakupan "pencarian" F6 = melengkapi filter wilayah** pada daftar koperasi yang sudah ada (`/admin/koperasi`). **Tidak** membangun halaman pencarian global baru. Pencarian/filter eksisting (`q` nama/nomor identitas, `status_verifikasi`, `jenis_koperasi`, whitelist `sort`, pagination) **dipertahankan**. | FR-160 sudah terpenuhi sebagian pada F2 (`Admin/CooperativeController::index`); menghindari duplikasi halaman & dua sumber kebenaran query. | — |
| F6-6 | **Tidak ada widget peringatan izin ≤ 30 hari** ke depan. Yang ditampilkan hanya **izin yang sudah kedaluwarsa**, berdasarkan FR-050 ("izin kadaluarsa dapat diidentifikasi berdasarkan tanggal"), memakai `cooperative_licenses.tanggal_berlaku_sampai` + indikator `CooperativeLicense::isExpired()`. | Keputusan pemilik Tahap 56 (opsi iii). FR-022 ("Ringkasan perizinan & kesehatan", termasuk "izin mendekati kadaluarsa") ditandai **FASE-2** pada `PRD.md` §7.2 dan **bukan** bagian himpunan FR F6. Ambang intervalnya pun masih `[NEEDS CONFIRMATION]` (`PRD.md` §7.3 FR-022). | Bila dinas menetapkan aturan & interval resmi, tambahkan widget FR-022 pada fase lanjutan — **bukan** F6. |

> **Koreksi Tahap 77 — status publikasi (fakta referensi lokal, bukan keadaan remote).** Bukti Tahap 76:
> `git rev-list --left-right --count origin/main...HEAD` = `0 1` (behind **0**, ahead **1**); satu-satunya commit
> yang ada di lokal tetapi tidak pada referensi lokal `origin/main` adalah **`2694618`**; tidak ada commit yang
> hanya ada pada referensi lokal tersebut. Reflog lokal mencatat `refs/remotes/origin/main` terakhir diperbarui
> lewat `update by push` pada **2026-10-18 23:49:18 +0700** (menjadi `009a29f`), sehingga klaim lama bahwa
> keempat commit seluruhnya belum dipush **bertentangan** dengan reflog itu.
>
> **Keterbatasan (dinyatakan, bukan dikarang):** tidak ada `git fetch` pada tahap ini, sehingga **kondisi remote
> terkini belum diverifikasi** — semua hitungan di atas hanya berlaku terhadap referensi lokal yang tersedia dan
> **tidak** menyatakan bahwa `origin/main` di remote pasti masih `009a29f`. Konteks historis §F6-1 (kapan dan
> atas persetujuan siapa push itu terjadi) **tidak dapat direkonstruksi sepenuhnya** dari bukti yang ada; yang
> dicatat di sini hanya apa yang terbaca dari reflog. **Commit/push** perubahan F6 yang belum dikomit **belum
> diotorisasi**; F6 tetap **MENUNGGU PENINJAUAN** dan **F7 belum dimulai**.

### 15.1 Cakupan resmi F6 (himpunan FR)

Himpunan FR F6 = **FR-020, FR-021, FR-030, FR-031, FR-170** (`IMPLEMENTATION_PLAN.md` §3 F6; konsisten
dengan `PRD.md` §13.2). Pemetaan label mengikuti **PRD** (sumber kebenaran) — ini juga mengoreksi pelabelan
yang keliru pada `ROADMAP_REMAINING.md` dan `PROMPT_PACK.md` (selaras dengan F5-2):

| FR | Label resmi (`PRD.md` §7.2) | Porsi F6 |
| --- | --- | --- |
| FR-020 | Dashboard Admin — statistik koperasi per status/wilayah/periode | Dashboard admin: kartu statistik |
| FR-021 | Dashboard Admin — antrian pengajuan yang perlu ditindak | Dashboard admin: widget ringkas antrian (`submitted`/`under_review`); halaman antrian penuh sudah ada di F5 |
| FR-030 | Dashboard Pengelola — status kelengkapan & pengajuan koperasi | Dashboard pengelola: indikator kelengkapan + status pengajuan terkini |
| FR-031 | Dashboard Pengelola — daftar permintaan perbaikan | Dashboard pengelola: daftar `needs_revision`/`rejected` + alasan + tautan perbaikan |
| FR-170 | Laporan & rekapitulasi koperasi (hanya data terverifikasi) | Halaman laporan: filter wilayah/jenis/status/periode + agregat |
| (FR-160) | Pencarian, filter, pagination, pengurutan | **Dasar** filter wilayah pada daftar koperasi (F6-5); bukan FR baru |

**Di luar cakupan F6:** **FR-022** (FASE-2 — ringkasan izin mendekati kadaluarsa & distribusi kesehatan),
**FR-171** (FASE-2 — ekspor laporan; ditunda ke F8), **FR-180–183/190/191** (F7 — publik), dan
**FR-012, FR-072, FR-082, FR-091, FR-183** (F8). Istilah status yang dipakai **`needs_revision`**
(bukan `revision_requested`) — lihat F5-2.

### 15.2 Otorisasi & isolasi F6

- Dashboard & laporan admin berada di bawah `role:admin_dinas`; dashboard pengelola di bawah
  `role:pengelola_koperasi` — keduanya di dalam grup `auth` + `active` (`routes/web.php`).
- Seluruh agregasi sisi pengelola **wajib** difilter `cooperative_id` milik akun (anti-IDOR,
  `AGENTS.md` §4.5). Akun pengelola tanpa koperasi → halaman informatif, bukan error 500.
- F6 sepenuhnya **read-only**: tidak ada route mutasi baru, tidak ada penulisan ke database.

### 15.3 Referensi migrasi F6

**Tidak ada migrasi baru pada F6.** Seluruh objek yang dibutuhkan sudah ada dan terverifikasi pada
`koperasi_jabar` (Tahap 53: 25 tabel / 22 migrasi / 28 FK / batch 6):

| Kebutuhan F6 | Objek & indeks pendukung (sudah ada) |
| --- | --- |
| Statistik per status/jenis | `cooperatives.status_verifikasi` (`cooperatives_status_verifikasi_index`), `cooperatives.jenis_koperasi` (`cooperatives_jenis_koperasi_index`) |
| Statistik & rekap per wilayah (F6-4) | `cooperatives.region_id` (`cooperatives_region_id_foreign`), `regions.type`, `regions.parent_id` |
| Izin sudah kedaluwarsa (F6-6) | `cooperative_licenses.tanggal_berlaku_sampai` (`..._tanggal_berlaku_sampai_index`), `..._status_index` |
| Antrian verifikasi (F6-2/FR-021) | `submissions.status` (`submissions_status_index`), `submissions.cooperative_id`, `submissions.module` |
| Kelengkapan data per modul (FR-030) | `cooperatives` + `health_assessments`, `governance_records`, `cooperative_assets`, `member_histories`, `officers`, `cooperative_licenses`, `documents` |

> Bila kelak dibutuhkan indeks untuk filter `created_at` (F6-3), itu **migrasi baru** pada fase lain —
> bukan bagian dari pekerjaan F6.

> **Perubahan (Tahap 63).** Keputusan pemilik **D-1 opsi B** menambahkan **satu migrasi baru** di luar
> tabel di atas: `2026_10_19_000001_add_parent_submission_id_to_submissions_table.php` (kolom
> `parent_submission_id` nullable + indeks + self-FK). Migrasi lama tidak diubah/dihapus. Lihat **§17.1**.

---

## 16. Tindak lanjut audit F6 — status AC, asumsi, dan keputusan tertunda (Tahap 58/59)

> Ditulis pada **Tahap 59** sebagai tindak lanjut **audit read-only Tahap 58**. Tujuan: memisahkan
> **fakta terverifikasi**, **asumsi implementasi**, **rekomendasi**, dan **pertanyaan yang masih menunggu
> keputusan pemilik**. Catatan ini **tidak** menyatakan AC F6 lulus, **tidak** mengubah kode/tes/skema/data,
> dan **tidak** mengubah workflow. **F6 tetap menunggu peninjauan.**

### 16.1 FR-031 / FR-142 — "revisi membuat pengajuan/versi terpisah" **BELUM terpenuhi sepenuhnya** `[FAKTA]`

**Keputusan sementara (Tahap 59):** perilaku saat ini **dipertahankan** — revisi `needs_revision → submitted`
memakai **record pengajuan yang sama** (selaras keputusan F5-5). Ini **pilihan sementara yang disengaja**,
diambil untuk **menghindari perubahan perilaku yang sudah diterima pemilik** (F6-1), dan **bukan** bukti
bahwa AC FR-031/FR-142 sudah lulus.

| Sumber | Bunyi |
| --- | --- |
| `PRD.md` FR-031 (`:419`) | AC: "…**revisi membuat pengajuan terpisah**." |
| `PRD.md` FR-142 (`:555`) | "…**revisi membuat versi pengajuan baru** (history terjaga)." |
| `PRD.md` §12.2 (`:802`) & §12.5 (`:832`) | transisi legal `needs_revision → submitted` pada record yang sama ("Pengelola kirim ulang"). |
| `PRD.md` BR-014 (`:766`) | "Perubahan pada data yang **sudah disetujui** disimpan sebagai pengajuan terpisah…" — aturan ini **sudah** terpenuhi (data resmi hanya berubah saat `approved`). |
| `USER_FLOWS.md` FLOW-PGL-04 (`:146`, `:150`) | "kirim ulang **pada pengajuan yang sama**"; catatan: perilaku versi baru **"belum diimplementasikan"**. |
| `DECISIONS.md` §12 F5-5 | memilih record yang sama, menyebut bunyi FR-031/FR-142 **"ambigu"**, dan mencatat mitigasi opsional `parent_submission_id`. |

- **Terpenuhi:** alasan perbaikan tampil + histori transisi terjaga (`submission_reviews` + `activity_logs`).
- **Belum terpenuhi:** pengajuan/versi **terpisah**.
- **Bukan keputusan dinas:** tidak ada bukti tertulis bahwa dinas/pemilik mengesampingkan AC ini. Penerimaan
  F5 (F6-1) **tidak** berarti AC FR-031/FR-142 sudah lulus.
- **Risiko bila dibiarkan:** `submission_items` dihapus-lalu-dibuat-ulang saat revisi, sehingga **isi versi
  sebelumnya** hanya tersisa di `activity_logs` (audit), bukan sebagai data terstruktur.

> **Diselesaikan Tahap 63 — pemilik memilih D-1 opsi B.** Keputusan sementara (record yang sama) **diganti**
> dengan **versioning**: revisi membuat pengajuan baru bertaut `parent_submission_id`, dan `submission_items`
> versi lama tetap utuh. Uraian lengkap ada di **§17.1**. Butir di atas tetap dicatat sebagai jejak audit.

### 16.2 FR-030 — rumus kelengkapan tetap **asumsi implementasi** `[ASUMSI]`

- Rumus **bobot setara, 7 modul** = **asumsi implementasi Tahap 57**, **bukan** ketetapan PRD. PRD FR-030
  (`:399-408`) tidak mendefinisikan modul wajib maupun bobotnya; tidak ada definisi resmi di dokumen lain.
- Implementasi: `app/Services/Reporting/CompletenessReport.php` (7 modul; modul dianggap lengkap bila
  `count > 0`; hanya **Data Induk** dinilai per-field wajib, sejalan `StoreCooperativeRequest::rules()`).
- **Catatan risiko (temuan audit Tahap 58):** 6 dari 7 modul (perizinan, pengurus, kesehatan, tata kelola,
  aset, anggota) **tidak dapat diisi pengelola** — halaman modul pengelola hanya `GET` dan kesehatan adalah
  kewenangan admin (PRD FR-080/FR-081). Indikator ini berpotensi menimbulkan **persepsi tanggung jawab**
  yang keliru pada pengelola (bertentangan dengan maksud FR-030 "belum ada data → panduan pengisian").
- **Perubahan rumus, pengecualian modul, atau penilaian per field wajib memerlukan keputusan pemilik**
  (belum ada keputusan; **jangan** diubah sepihak).

> **Diselesaikan Tahap 63 — pemilik memilih D-2 opsi B.** Rumus "7 modul bobot setara" **diganti** dengan
> kelengkapan **per field wewenang pengelola** (whitelist Q-006); modul kewenangan admin dikeluarkan dari
> persentase dan hanya ditampilkan sebagai informasi. Uraian di **§17.2**.
>
> **Catatan interpretasi (ditambahkan Tahap 65, R-3 audit).** FR-030 (`PRD.md` :399-408) menyebut
> "hitung kelengkapan **per modul**" pada baris *Proses*, sedangkan **acceptance criteria**-nya berbunyi
> "indikator konsisten dengan kelengkapan **field wajib**". Implementasi mengikuti **AC**: kelengkapan
> **per field wewenang pengelola**. Perbedaan kata "per modul" vs "per field" dicatat sebagai interpretasi
> **`[ASUMSI]`** — bunyi requirement **tidak** diubah, dan **Q-006 tetap belum disetujui** (§18.4).

### 16.3 Status filter F6 — yang sudah diimplementasikan & yang masih terbuka `[NEEDS CONFIRMATION]`

| FR | Bunyi persyaratan (sumber) | Perilaku aktual (terverifikasi) | Status |
| --- | --- | --- | --- |
| FR-020 | "Data Masukan: filter wilayah/periode/status" (`PRD.md` `:367`) | Dashboard admin memiliki **kontrol filter** wilayah/jenis/status/periode (Tahap 63) yang memakai `CooperativeFilters` — komponen yang sama dengan laporan; daftar koperasi memakai `CooperativeFilters::normalizeStatus()` untuk status (Tahap 65) | **Terpenuhi** — lihat §17.3, §18.3 |
| FR-021 | "Data Masukan: filter status/umur pengajuan" (`PRD.md` `:378`) | Halaman antrian: filter `status` + pencarian `q` + **filter umur** (`umur_min`/`umur_max`, Tahap 60) | **Terpenuhi** — ambang umur `[ASUMSI]`, lihat §16.5 |
| FR-160 | "filter per kolom (status, wilayah, jenis, periode)…" (`PRD.md` `:565`) | Daftar koperasi: `q`, `status_verifikasi` (dinormalisasi via `CooperativeFilters::normalizeStatus()` sejak Tahap 65), `jenis_koperasi`, `region_id`, **`tahun`** (finalisasi 24 Okt 2026), sort | **Terpenuhi secara fungsional** — filter periode memakai definisi **PROVISIONAL** F6-3; Q-010 tetap terbuka (§31.1) |

Catatan: AC eksplisit **FR-020**, **FR-021**, dan **FR-160** kini lulus secara fungsional. Filter
periode daftar koperasi ditambahkan pada finalisasi 24 Oktober 2026 dengan definisi **PROVISIONAL**
F6-3 (tahun `cooperatives.created_at`), bukan definisi resmi baru; Q-010 tetap terbuka (§31.1).
Untuk FR-021, "Data Masukan" sudah **lengkap** sejak Tahap 60 (§16.5). Untuk FR-020, kontrol filter
sudah ditambahkan Tahap 63 (§17.3). Normalisasi status daftar koperasi diselaraskan Tahap 65 (§18.3).
Bunyi persyaratan **tidak** diubah agar cocok dengan implementasi.

### 16.4 Pertanyaan yang menunggu keputusan pemilik

| # | Pertanyaan | Opsi | Catatan |
| --- | --- | --- | --- |
| D-1 | FR-031/FR-142: terima deviasi (record sama) atau minta pengajuan/versi terpisah? | (A) terima deviasi *(rekomendasi audit)*; (B) versi terpisah + `parent_submission_id` (**migrasi baru** + harmonisasi PRD §12.2/§12.5, FR-031, FR-142, FLOW-PGL-04) | Opsi "versi tanpa kolom baru" ditolak audit — isi versi lama tetap hilang dari `submission_items` |
| D-2 | FR-030: terima rumus bobot setara 7 modul sebagai asumsi sementara? | ya (tetap `[ASUMSI]`) / ubah definisi (per field wajib, kecualikan modul kewenangan admin, atau data induk saja) | Perlu keputusan sebelum AC FR-030 diklaim final |
| D-3 | FR-020: dashboard admin butuh kontrol filter wilayah/periode/status? | cukup tampilkan dimensi (status quo) / tambah filter | — |
| D-4 | FR-021: tambah **filter umur pengajuan** pada halaman antrian? | ya / tidak | **Diimplementasikan Tahap 60** (lihat §16.5) — dasar `updated_at`, ambang angka hari `[ASUMSI]` |
| D-5 | FR-160: tambah **filter periode** pada daftar koperasi? | ya / tidak | Periode masih `[PROVISIONAL]` (F6-3); **dieksekusi provisional pada finalisasi 24 Okt 2026** (§31.1) |

> **Diputuskan pemilik pada Tahap 63 (semua opsi B):** **D-1** (versi pengajuan terpisah), **D-2**
> (kelengkapan per field wewenang pengelola), **D-3** (kontrol filter dashboard admin) — ketiganya
> **sudah diimplementasikan** dan diuji; **D-5** awalnya **ditunda** karena Q-010 belum menjelaskan
> definisi periode, lalu **dieksekusi secara provisional** pada finalisasi 24 Oktober 2026 memakai
> F6-3 (§31.1). Uraian lengkap & bukti: **§17**, **§31**. Penerimaan F4/F5 (F6-1) tetap bukan bukti
> AC FR-031/FR-142 lulus; yang menjadi bukti adalah tes Tahap 63.

**D-1 opsi B — sudah dieksekusi (Tahap 63).** Migrasi korektif **baru** (kolom
`parent_submission_id` nullable + indeks + self-FK; migrasi lama tidak diubah — `AGENTS.md` §4.7), perubahan
`SubmissionService`/Policy/route/UI/tes telah dikerjakan, kebijakan record lama = **dibiarkan `needs_revision`
dan ditandai digantikan** (bukan diarsipkan, agar tautan notifikasi tidak 404), dan aturan "satu pengajuan
aktif per koperasi + modul" ditambahkan. Uraian lengkap: **§17.1**.

### 16.5 Catatan implementasi filter umur pengajuan (Tahap 60) `[ASUMSI]`

Filter **umur pengajuan** FR-021 pada antrian admin (`Admin/SubmissionController::index`) telah
**diimplementasikan** pada Tahap 60 — menjawab **D-4**. **D-1, D-2, D-3, D-5 tidak berubah.**

- **Dasar waktu = `updated_at`** — kolom waktu yang sudah dipakai antrian (pengurutan & kolom "Diperbarui");
  ini "umur sejak aktivitas terakhir", **bukan** `created_at` (pengajuan dapat lama berstatus `draft`).
- **Parameter** `umur_min`/`umur_max` (hari, bilangan bulat `>= 0`, **inklusif** pada batas), diisi admin.
  **Tidak** ada kategori/bucket umur tetap karena FR-021 tidak menetapkannya — ambangnya `[ASUMSI]`.
- Nilai tidak sah (non-digit, negatif, desimal) **diabaikan** (pola sama `normalizeRegionFilter`).
- Filter diterapkan **server-side**, dapat dikombinasikan dengan `status`/`q`, dan `withQueryString()`
  mempertahankan parameter saat berpindah halaman.
- **Belum keputusan bisnis resmi:** bila dinas menetapkan definisi/kategori umur (mis. SLA antrian),
  sesuaikan definisi & UI mengikuti keputusan itu. Uji: `tests/Feature/Admin/SubmissionQueueAgeFilterTest.php`.

---

## 17. Keputusan & implementasi Tahap 63 — eksekusi D-1/D-2/D-3/D-5

> Pemilik proyek memutuskan **opsi B** untuk **D-1**, **D-2**, **D-3** dan **opsi B bersyarat** untuk
> **D-5** (decision gate §16.4). Catatan ini menutup *decision gate* §16 dan mendokumentasikan
> implementasi beserta batasnya. **F6 tetap MENUNGGU PENINJAUAN** sampai seluruh AC relevan dinilai
> lulus oleh peninjau; catatan ini **bukan** pernyataan "F6 selesai".
>
> **Status verifikasi Tahap 63 (SQLite `:memory:`, lingkungan pengujian):** `php artisan test` →
> **341 tes / 1.233 asersi PASS** (sebelum Tahap 63: 323 tes / 1.140 asersi). **Migrasi baru belum
> dijalankan** pada DB dev (status `Pending`); pengujian tidak membuktikan kompatibilitas MariaDB.
> Angka test suite hanya berlaku untuk SQLite. (Angka asersi Tahap 63 yang sebelumnya tertulis
> 1.231 dikoreksi pada Tahap 65 — lihat §18.4.)

### 17.1 D-1 — versioning pengajuan (FR-031/FR-142) `[DIPUTUSKAN: opsi B]`

**Keputusan.** Revisi atas pengajuan `needs_revision` membuat **record pengajuan baru** yang tertaut ke
versi induk melalui `submissions.parent_submission_id`; record lama **tidak** diubah dan tetap menyimpan
`submission_items` + `submission_reviews` sebagai riwayat. Ini menggantikan keputusan F5-5 ("record yang
sama") dan memenuhi bunyi FR-031 ("revisi membuat pengajuan terpisah") serta FR-142 ("revisi membuat versi
pengajuan baru (history terjaga)").

**Migrasi (baru).** `database/migrations/2026_10_19_000001_add_parent_submission_id_to_submissions_table.php`:
kolom `parent_submission_id` (`BIGINT UNSIGNED NULL`), indeks `submissions_parent_submission_id_index`,
dan self-FK `nullOnDelete` → `submissions.id`. **Tidak ada migrasi lama yang diubah/dihapus**
(`AGENTS.md` §4.7). Catatan portabilitas: self-FK **hanya** dibentuk pada MySQL/MariaDB karena migrasi memakai
guard driver eksplisit; **koreksi Tahap 67:** Laravel 13 **mendukung** FK pada SQLite lewat rebuild tabel,
jadi ini pilihan bukan keterbatasan (lihat §20.2). Kolom & indeks selalu dibuat, dan `down()` aman di kedua
driver. **Migrasi ini `Pending` pada DB dev `koperasi_jabar`**;
menjalankannya menunggu izin terpisah.

**Perilaku.**
- `needs_revision` **bukan lagi** status editable: `Submission::EDITABLE_STATUSES = ['draft']` (dulu
  `['draft', 'needs_revision']`). Perbaikan wajib lewat **versi baru**.
- `Submission::TRANSITIONS['needs_revision'] = ['archived']` — transisi `needs_revision → submitted` pada
  record yang sama **dihapus** (diselaraskan dengan PRD §12.2).
- Pengajuan `needs_revision` yang sudah punya versi turunan = **digantikan** (`isSuperseded()`), tidak lagi
  aktif (`isActive()`), tidak dapat diperbaiki (`isRevisable()`), dan tidak lagi muncul di daftar "permintaan
  perbaikan" dashboard pengelola.
- **Cegah pengajuan aktif ganda:** maksimum **satu** pengajuan aktif (`draft`/`submitted`/`under_review`,
  atau `needs_revision` yang belum digantikan) per **koperasi + modul**. Pelanggaran → `SubmissionVersionException`
  (turunan `SubmissionTransitionException` agar `catch` lama tetap menangkapnya), ditampilkan sebagai error
  formulir — bukan HTTP 500.
- **Alur pengelola:** detail `needs_revision` menampilkan tombol "Buat Versi Perbaikan" →
  `GET pengelola.pengajuan.create?revision={id}` (terisi dari usulan versi induk + alasan admin) →
  `POST pengelola.pengajuan.store` dengan `parent_submission_id` → `SubmissionService::createRevision()`
  membuat draft baru.
- **Audit:** aksi `create_submission_revision` dicatat ke `activity_logs`.
- **Anti-IDOR:** `SubmissionPolicy::revise()` = pemilik koperasi **dan** `isRevisable()`, ditegakkan di server.
- **Tautan notifikasi lama tidak 404:** record versi lama **tidak** di-soft-delete; halaman detail lama tetap
  dapat dibuka (diuji).
- **Kompatibilitas data lama:** record tanpa induk tetap valid (`parent_submission_id` NULL, `isRevision()` false).
- **BR-014 tidak diubah** — data resmi tetap hanya berubah saat `approved`.

**Perimeter & batas.** Cakupan modul mengikuti `config('koperasi.submission_modules')` (saat ini
`koperasi_induk`); verifikasi tetap satu tingkat (Q-004). Tidak ada aturan bisnis baru: ini implementasi
bunyi AC yang sebelumnya ditandai belum dipenuhi (§16.1). Harmonisasi dokumen: §17.5.

**Uji:** `tests/Feature/SubmissionVersioningTest.php` (12 tes) + penyesuaian
`tests/Feature/PengelolaSubmissionTest.php`, `tests/Feature/Reporting/PengelolaDashboardTest.php`.

### 17.2 D-2 — rumus kelengkapan FR-030 `[DIPUTUSKAN: opsi B]`

**Keputusan.** Kelengkapan dihitung **per field** atas **field wewenang pengelola** (whitelist modul
pengajuan = `KoperasiIndukHandler::fields()`, 8 field), **bukan** "7 modul berbobot setara".

- Persentase = jumlah field wewenang pengelola yang terisi ÷ seluruh field wewenang pengelola.
- **Field wajib** pada wewenang pengelola dilaporkan terpisah (`required_filled`/`required_total`) = irisan
  field wajib form koperasi (`StoreCooperativeRequest`) dengan whitelist pengelola = {`nama`,
  `jenis_koperasi`}. `nomor_identitas` & `status_verifikasi` wajib bagi **admin**, bukan wewenang pengelola.
- Modul kewenangan **admin** (perizinan, pengurus, kesehatan, tata kelola, aset, anggota) **dikeluarkan**
  dari persentase dan hanya ditampilkan sebagai **informasi** (read-only) — menghilangkan persepsi tanggung
  jawab yang keliru (§16.2).
- Sumber daftar field = **kode handler** (bukan daftar terpisah), sehingga rumus tidak menyimpang dari aturan
  validasi pengajuan. Konstanta lama `CompletenessReport::DATA_INDUK_REQUIRED_FIELDS` **dihapus**; pengguna
  baru memakai `pengelolaFields()`/`pengelolaRequiredFields()`.

**[ASUMSI] tetap berlaku:** Q-006 belum dikonfirmasi dan PRD FR-030 tidak menetapkan rumus pembobotan.

**Uji:** `tests/Feature/Reporting/PengelolaDashboardTest.php` (irisan field wajib, rumus persentase, modul
admin tidak mengubah persentase, render UI + tautan).

### 17.3 D-3 — kontrol filter dashboard admin (FR-020) `[DIPUTUSKAN: opsi B]`

**Keputusan.** Dashboard admin mendapat kontrol filter **wilayah/jenis/status/periode** yang memakai
komponen bersama baru `app/Services/Reporting/CooperativeFilters.php` (normalisasi + penerapan query).
`CooperativeReport` (laporan) **mendelegasikan** ke komponen yang sama, sehingga **angka kartu = query daftar
yang setara** (AC FR-020) tanpa duplikasi logika; konstanta `CooperativeReport::REGION_NONE` /
`REGION_UNASSIGNED_LABEL` dipertahankan sebagai alias.

- Filter berlaku untuk **statistik koperasi** (kartu + tabel per status/wilayah/jenis/periode). Antrian
  pengajuan (FR-021) & izin kedaluwarsa (FR-050) **tidak** difilter (cakupan berbeda) — dinyatakan di UI.
- Nilai filter tidak valid **diabaikan** (konvensi yang sama dengan daftar koperasi/laporan).
- Baris tabel agregat menyediakan tautan "Daftar" ke `admin.koperasi.index` dengan filter setara
  (status/jenis/wilayah) untuk verifikasi silang.
- Kolom periode harian/tabel memakai tahun `cooperatives.created_at` — definisi **[SEMENTARA]** F6-3.

**Uji:** `tests/Feature/Reporting/AdminDashboardTest.php` (+6 tes: status, wilayah, `none`, periode,
gabungan filter, nilai tidak sah, render kontrol & tautan/reset).

### 17.4 D-5 — filter periode pada daftar koperasi **DITUNDA** `[BERSYARAT]` — *dieksekusi secara provisional pada §31.1 (finalisasi 24 Oktober 2026)*

**Keputusan pemilik:** tambahkan filter periode pada daftar koperasi **hanya bila** Q-010 & definisi periode
memadai.

**Hambatan (fakta terverifikasi).**
1. **Q-010 belum dijawab** (`docs/PRD.md` §21) — belum ada definisi resmi periodisasi data.
2. Satu-satunya definisi periode yang ada adalah **F6-3** (`DECISIONS.md` §15): tahun
   `cooperatives.created_at`, yang **secara eksplisit `[SEMENTARA]/PROVISIONAL`** dan **bukan** keputusan bisnis.
3. Periode data **periodik** (kesehatan/tata kelola/aset/anggota) belum punya kolom `period` (F4 PARTIAL),
   sehingga "periode" pada daftar koperasi tetap ambigu.

**Tindakan.** Filter periode pada daftar koperasi **tidak** diimplementasikan — **tidak mengarang** aturan
bisnis periode. Bagian F6 lain yang independen (D-1/D-2/D-3) tetap dikerjakan. Baris tabel periode pada
dashboard admin diberi catatan eksplisit. Ditinjau ulang setelah Q-010 dijawab; saat itu filter dapat memakai
`CooperativeFilters` (sudah mendukung parameter `tahun`).

### 17.5 Harmonisasi dokumen (Tahap 63)

| Dokumen | Perubahan |
| --- | --- |
| `PRD.md` §12.2 | Baris transisi `needs_revision → submitted` **diganti** dengan semantik versi baru; `needs_revision` pada record ini hanya dapat diarsipkan. |
| `PRD.md` §12.5 | Diagram alur diperbarui: perbaikan = **versi pengajuan baru** (catatan pada state `needs_revision`). |
| `PRD.md` FR-031/FR-142 | Bunyi **tidak diubah** (sudah "pengajuan/versi terpisah") — kini terpenuhi oleh implementasi. |
| `USER_FLOWS.md` FLOW-PGL-04 | Langkah "kirim ulang pada pengajuan yang sama" diganti menjadi pembuatan versi baru; catatan "belum diimplementasikan" dihapus. |
| `DATA_DICTIONARY.md` §14 | Kolom `parent_submission_id` + indeks + self-FK ditambahkan. |
| `IMPLEMENTATION_PLAN.md`, `ROADMAP_REMAINING.md`, `PROMPT_PACK.md` | Daftar keputusan tertunda → sudah diputuskan (Tahap 63); D-5 ditunda dengan hambatan. |

> **Batas.** Perubahan PRD di atas **hanya** menyelaraskan uraian alur (§12.2/§12.5) dengan keputusan pemilik
> D-1 opsi B; **tidak ada** acceptance criteria yang dilonggarkan, dan bunyi FR-031/FR-142 tetap apa adanya.

### 17.6 Yang **tidak** diklaim

- F6 **belum** dinyatakan selesai/tertutup; seluruh AC relevan masih menunggu peninjauan pemilik.
- Kompatibilitas **MariaDB** dari migrasi baru & perubahan query filter **belum diuji** (hanya SQLite).
- Migrasi `2026_10_19_000001_*` **belum dijalankan** pada DB dev/produksi (menunggu izin terpisah).
- Rumus FR-030 (§17.2) tetap `[ASUMSI]` sampai Q-006 dikonfirmasi; definisi periode tetap `[SEMENTARA]`
  sampai Q-010 dijawab.

---

## 18. Tindak lanjut audit Tahap 64 — perbaikan integritas & konsistensi (Tahap 65)

> Ditulis pada **Tahap 65** sebagai tindak lanjut audit read-only Tahap 64 (temuan **T-1**, **S-1**,
> **S-2**, koreksi **R-1…R-3**). **F6 tetap MENUNGGU PENINJAUAN** — catatan ini **bukan** pernyataan
> "F6 selesai", dan **D-5 tidak** dinyatakan selesai.

### 18.1 T-1 — serialisasi pembuatan pengajuan (tingkat aplikasi) `[FAKTA]`

**Masalah (temuan audit Tahap 64).** `SubmissionService::createDraft()` & `createRevision()` memeriksa
konflik (`assertNoConflictingActive`) dan status induk **di luar** transaksi, sementara **tidak ada**
constraint basis data yang menegakkan "satu pengajuan aktif per koperasi+modul" → rentan **TOCTOU**
(double-submit / dua permintaan bersamaan dapat membuat dua draft aktif).

**Perbaikan.** Kedua jalur pembuatan kini:
1. berjalan dalam **satu** `DB::transaction`;
2. mengunci **baris koperasi** lebih dulu — `lockCooperativeAnchor()`:
   `Cooperative::withTrashed()->whereKey($id)->lockForUpdate()->first()`. Baris koperasi dipakai sebagai
   *anchor* stabil yang **selalu ada**, berbeda dari baris submission yang pada pembuatan pertama belum ada;
3. **baru** memeriksa konflik + status induk, lalu menyimpan.
`createRevision` juga **membaca ulang** record induk di dalam transaksi (mengabaikan model binding yang
basi) dan menolak dengan `SubmissionVersionException::parentUnavailable()` bila induk sudah tidak ada.

**Urutan lock.** Semua jalur pembuatan mengunci `cooperatives` lebih dulu dengan urutan yang sama, sehingga
dua permintaan untuk koperasi yang sama berjalan **berurutan**; pembuatan **tidak** mengunci baris
submission, sehingga tidak ada urutan lock silang (mis. dengan `approve()` yang mengunci submission lalu
memperbarui koperasi) yang dapat menimbulkan deadlock.

**Batas (penting).** Ini pengamanan **tingkat aplikasi**. SQLite mengabaikan `FOR UPDATE` (no-op), dan
**tidak** ditambahkan unique/partial index baru (menghindari constraint yang belum terbukti kompatibel
dengan MariaDB proyek). Serialisasi aplikasi **mengurangi** balapan tetapi **tidak** menggantikan
constraint basis data bila kelak dinilai perlu (butuh keputusan pemilik + migrasi baru + uji MariaDB).

**Uji.** `tests/Feature/SubmissionVersioningTest.php` (+7): dua permintaan draft pada koperasi/modul sama
(permintaan kedua ditolak), dua modul berbeda tidak saling menghambat, koperasi lain tidak menghambat,
induk dapat direvisi lagi setelah draft turunan diarsipkan, rollback penuh bila langkah tengah transaksi
gagal, riwayat versi induk tetap tampil, dan akses versi turunan lintas koperasi → 403. **Tes pada SQLite
tidak membuktikan ketiadaan balapan di MariaDB.**

### 18.2 S-1 — angka kartu "Butuh Perbaikan" dashboard pengelola `[FAKTA]`

Kartu "Butuh Perbaikan" (FR-031) kini memakai **definisi actionable yang sama** dengan daftar:
`needs_revision` yang **belum** digantikan versi turunan + `rejected`, lewat helper bersama
`Pengelola\DashboardController::actionableRevisions()`. Sebelumnya kartu menghitung `needs_revision`
mentah (termasuk induk yang **sudah** digantikan), sehingga angkanya bisa melebihi isi daftar.
**Uji:** `tests/Feature/Reporting/PengelolaDashboardTest.php` (+1) membandingkan angka kartu dengan daftar
pada kondisi: revisi diminta, versi baru dibuat, versi masih `draft`, dan draft turunan diarsipkan.

### 18.3 S-2 — normalisasi status pada daftar koperasi `[FAKTA]`

`Admin\CooperativeController::index()` kini memakai `CooperativeFilters::normalizeStatus()` untuk
`status_verifikasi` — **satu sumber kebenaran** dengan dashboard/laporan. Nilai tidak valid **diabaikan**
(`''`), bukan menghasilkan daftar kosong secara diam-diam (sebelumnya nilai diteruskan mentah ke
`where('status_verifikasi', …)`). Normalisasi wilayah tetap seperti sebelumnya.
**Uji:** `tests/Feature/Reporting/CooperativeRegionFilterTest.php` (+3): status tidak valid diabaikan,
status valid diterapkan & terpelihara di pagination, dan kombinasi status+wilayah+jenis.

### 18.4 Koreksi dokumentasi (R-1…R-3) `[FAKTA]`

- **R-1:** angka asersi diselaraskan dengan hasil suite aktual. Rekaman Tahap 63 dikoreksi dari
  `1.231` → `1.233` asersi (nilai yang benar untuk Tahap 63), dan Tahap 65 mencatat angka terbarunya.
- **R-2:** kontradiksi §16.3 ("AC eksplisit FR-020/FR-021/FR-160 lulus") **diperbaiki** — pada Tahap 65
  **FR-160 tetap Sebagian** karena filter periode daftar koperasi belum diimplementasikan (D-5, §17.4);
  **finalisasi 24 Okt 2026** mengeksekusi D-5 secara provisional (§31.1).
- **R-3:** ditambahkan catatan eksplisit bahwa FR-030 ditafsirkan **per field** mengikuti **acceptance
  criteria**, sedangkan baris *Proses* menyebut **per modul** — **tanpa** mengubah bunyi requirement
  (§16.2, §17.2). **Q-006 tetap belum disetujui.**

### 18.5 Yang **tidak** diklaim (Tahap 65)

- F6 **belum** selesai/tertutup; seluruh AC relevan masih menunggu peninjauan pemilik.
- Serialisasi T-1 **bukan** jaminan tingkat basis data dan **belum** diuji pada MariaDB
  (SQLite tidak menjalankan `FOR UPDATE`).
- Migrasi `2026_10_19_000001_*` **tetap `Pending`** pada DB dev — belum dijalankan.
- **D-5** (filter periode daftar koperasi) **ditunda pada Tahap 65**; **dieksekusi secara provisional**
  pada finalisasi 24 Oktober 2026 (§31.1); **Q-010 belum final**.
- Rumus FR-030 tetap `[ASUMSI]` sampai Q-006 dikonfirmasi.
- **R-4** (tautan wilayah dashboard berbasis nama wilayah → dapat menyimpang dari bucket bila ada nama
  duplikat) audit Tahap 64 **belum** dikerjakan pada Tahap 65 — **diselesaikan pada Tahap 66 (§19.1)**.

### 18.6 Verifikasi Tahap 65 `[FAKTA]`

`php artisan test` → **352 tes / 1.287 asersi PASS** (SQLite `:memory:` saja — **bukan** bukti
kompatibilitas MariaDB; baseline Tahap 63: 341 tes / 1.233 asersi). `pint --test` PASS,
`npm run build` PASS, `git diff --check` bersih. Migrasi **tetap `Pending`** (read-only; tidak ada migrasi
yang dijalankan).

---

## 19. Perbaikan R-4 (bucket wilayah per `region_id`) & kesiapan verifikasi MariaDB (Tahap 66)

> Ditulis pada **Tahap 66**. **F6 tetap MENUNGGU PENINJAUAN.** Tahap ini **tidak** menjalankan
> migrasi/DDL/DML apa pun, tidak menutup F6, dan **tidak** mengubah status Q-006/Q-010/Q-012.

### 19.1 R-4 — agregasi & tautan wilayah memakai `region_id` stabil `[FAKTA]`

**Masalah (temuan audit Tahap 64).** Statistik "Koperasi per Wilayah" dikelompokkan dengan
`GROUP BY regions.name`, sedangkan tautan "Daftar" di dashboard di-resolve lewat
`$regionOptions->pluck('id','name')`. Karena **`regions.name` tidak unik** — hanya `regions.code`
yang `unique` (`database/migrations/2026_10_16_100001_create_regions_table.php`) — dua wilayah
bernama sama tergabung menjadi satu baris, dan tautannya menunjuk hanya salah satu wilayah sehingga
**angka bucket ≠ hasil daftar** (melanggar FR-020 AC).

**Perbaikan (tanpa asumsi baru).** Agregasi dikelompokkan per **`cooperatives.region_id`** (kunci
stabil); nama wilayah hanya dipakai sebagai **label**:
- `DashboardStatistics::countByRegion()` mengembalikan **daftar bucket** `[{region_id, label, total}]`
  (bukan lagi peta `nama => jumlah`); `region_id = NULL` menjadi bucket tersendiri berlabel
  `REGION_UNASSIGNED_LABEL` ("Belum ditetapkan", F6-4).
- `resources/views/admin/dashboard.blade.php` menautkan "Daftar" memakai `region_id` bucket
  (`NULL` → `CooperativeFilters::REGION_NONE`); `pluck('id','name')` dihapus.
- `CooperativeReport::byRegion()` (laporan FR-170) juga dikelompokkan per `cooperatives.region_id`
  agar konsisten; keluarannya tetap `{wilayah, total}` sehingga view laporan **tidak** berubah.
- Label diambil dengan `MAX(regions.name)` — aman terhadap `ONLY_FULL_GROUP_BY` dan tersedia di
  MariaDB 10.4 (berbeda dari `ANY_VALUE()` yang baru ada di 10.5+).

Perubahan ini **tidak** memerlukan Q-012 dan **tidak** mengubah sumber data wilayah (tabel `regions`
tetap **kosong** di DB dev). Konsekuensi yang dicatat: bila ada dua wilayah bernama sama, keduanya
tampil sebagai **dua baris berlabel sama** (masing-masing tautannya benar). Disambiguasi label (mis.
menampilkan `code`/`type`) **tidak** dikarang dan menunggu Q-012.

**Uji.** `tests/Feature/Reporting/AdminDashboardTest.php` (+2: nama wilayah duplikat → bucket
terpisah & angka bucket = angka daftar; tautan bucket memakai `region_id`/`none`) dan
`tests/Feature/Reporting/AdminReportTest.php` (+1: nama duplikat tidak digabung pada laporan).

### 19.2 Audit source migrasi `2026_10_19_000001` (read-only) `[FAKTA SOURCE — BUKAN RUNTIME]`

Diperiksa dari source (migrasi **tidak** dijalankan):
- Tipe kolom `unsignedBigInteger` cocok dengan PK `submissions.id` (`$table->id()` → BIGINT UNSIGNED),
  **nullable**, tanpa default → baris lama tetap `NULL` ("versi awal").
- Indeks `submissions_parent_submission_id_index` **tidak** bentrok dengan indeks lama
  (`cooperative_id`, `status`, `module`, `submitted_by`).
- FK self-reference `submissions.parent_submission_id → submissions.id` dengan `nullOnDelete()`;
  nama default `submissions_parent_submission_id_foreign` belum dipakai. FK hanya dibuat pada driver
  non-SQLite (SQLite: `ALTER TABLE` FK diabaikan Laravel → guard `driverName === 'sqlite'`).
- `up()`: guard `hasTable` + `hasColumn` → tambah kolom + indeks → tambah FK bila belum ada
  (idempoten lewat `Schema::getForeignKeys()`). `down()`: drop FK (non-SQLite, bila ada) → drop kolom;
  **tidak** menghapus kolom atau data bisnis lain.
- Migrasi lama **tidak** diubah; tidak ada migrasi baru selain ini.

**Batas bukti.** Ini pemeriksaan **source**, **bukan** bukti runtime MariaDB. Migrasi masih
`Pending` pada DB dev `koperasi_jabar` (lihat rencana verifikasi di laporan Tahap 66 §4).

**Koreksi (Tahap 67).** Kalimat pada §19.2 "SQLite: `ALTER TABLE` FK diabaikan Laravel" **tidak akurat**
untuk versi Laravel yang terpasang (`SQLiteGrammar::getAlterCommands()` memuat `'foreign'` dan
`compileAlter()` melakukan rebuild tabel termasuk FK). **Perilaku** migrasi (FK tidak dibuat di SQLite)
tetap seperti didokumentasikan, tetapi **alasannya** adalah guard eksplisit `driverName === 'sqlite'`
yang disengaja, bukan keterbatasan driver. Rincian: §20.2.

### 19.3 Yang **tidak** diklaim (Tahap 66)

- F6 **belum** selesai; perbaikan R-4 **bukan** penutup seluruh AC.
- Serialisasi T-1 (Tahap 65) **belum** diuji pada MariaDB; `lockForUpdate` no-op di SQLite.
- Migrasi `2026_10_19_000001_*` **tetap `Pending`**.
- **Q-006** (rumus FR-030) tetap `[ASUMSI]`; **Q-010** (definisi periode) belum final sehingga
  **pada Tahap 66 FR-160 masih Sebagian** (filter periode daftar koperasi belum ada; **dieksekusi
  provisional pada §31.1**); **Q-012** belum dijawab → `regions` tetap kosong dan label wilayah tetap
  tidak disambiguasi.

### 19.4 Verifikasi Tahap 66 `[FAKTA]`

`php artisan test` → **355 tes / 1.305 asersi PASS** (SQLite `:memory:`; baseline Tahap 65:
352 tes / 1.287 asersi). `pint --test` PASS, `npm run build` PASS, `git diff --check` bersih.
Migrasi **tetap `Pending`** (read-only; tidak ada migrasi dijalankan).

---

## 20. Audit kesiapan verifikasi MariaDB & matriks blocker F6 (Tahap 67)

> Ditulis pada **Tahap 67**. **F6 tetap MENUNGGU PENINJAUAN.** Tahap ini **read-only** terhadap basis data:
> **tidak** menjalankan migrasi/seeder/DDL/DML/rollback, tidak menyentuh DB dev/produksi, tidak
> men-`commit`/`push`, tidak memulai F7, dan **tidak** mengubah status Q-006/Q-010/Q-012. Seluruh
> perubahan lokal Tahap 56–66 dipertahankan.

### 20.1 Audit regresi R-4 (read-only) `[FAKTA SOURCE]`

Konsumen tunggal diverifikasi lewat pencarian kode:
- `DashboardStatistics::countByRegion()` **hanya** dipakai `Admin\DashboardController` → variabel
  `regionCounts` → `resources/views/admin/dashboard.blade.php`. Tidak ada konsumen lain.
- `CooperativeReport::byRegion()` **hanya** dipakai `Admin\ReportController` → `byRegion` →
  `resources/views/admin/laporan/index.blade.php` (keluaran tetap `{wilayah, total}`).

Titik yang diminta diperiksa:
- **Pengelompokan memakai `region_id`**, bukan nama wilayah — ✔ (`GROUP BY cooperatives.region_id`,
  label lewat `MAX(regions.name)`).
- **`region_id = NULL` sebagai bucket tersendiri** — ✔ (`REGION_UNASSIGNED_LABEL`, tautan → `REGION_NONE`).
- **Tautan tiap bucket mengarah ke filter yang sesuai** — ✔ (`region_id` numerik → `?region_id=<id>`;
  `NULL` → `?region_id=none`).
- **Wilayah bernama sama tidak digabung** — ✔ (dua bucket terpisah, masing-masing tautannya benar);
  `regions.id` adalah PK sehingga `leftJoin('regions')` **tidak** menggandakan baris.
- **`cooperatives.region_id` menggantung tidak mungkin** — ✔ FK `→ regions.id` `restrictOnDelete()`
  (`2026_10_16_100002_create_cooperatives_table.php`), jadi `LEFT JOIN` tak pernah "kehilangan" nama.
- **Kontrak data view berubah dengan alasan terdokumentasi** — ✔ bentuk `regionCounts` berubah dari peta
  `nama => jumlah` menjadi daftar bucket `[{region_id, label, total}]`; didokumentasikan §19.1 dan
  docblock `countByRegion()`.
- **Tidak ada regresi pada filter/jumlah total/FR-170** — ✔ diuji ulang (§20.6).

**Tes regresi tambahan:** `AdminDashboardTest::test_region_bucket_totals_sum_to_total_cooperatives`
(Σ total seluruh bucket **=** `totalCooperatives`, baik tanpa filter maupun saat filter `status_verifikasi`
aktif; menjaring regresi penggandaan baris akibat JOIN). Laporan FR-170 sudah menegakkan invarian serupa
lewat `AdminReportTest` (`byRegion()->sum('total')`).

**Status R-4:** tidak ditemukan cacat baru. Perbaikan §19.1 tetap **"menunggu verifikasi"** — perilaku
`GROUP BY`/`ONLY_FULL_GROUP_BY` pada MariaDB **belum** dijalankan pada Tahap 67.

### 20.2 Koreksi klaim SQLite pada §19.2 `[FAKTA SOURCE]`

Diperiksa langsung pada source vendor:
- `Illuminate\Database\Schema\Grammars\SQLiteGrammar::getAlterCommands()` memuat `'foreign'`
  (dan `'dropForeign'`), sehingga perintah FK pada `Schema::table()` **memicu rebuild tabel**.
- `SQLiteGrammar::compileAlter()` membangun ulang tabel dan menyertakan
  `addForeignKeys($blueprint->getState()->getForeignKeys())`.

**Kesimpulan.** Klaim §19.2 ("SQLite mengabaikan perintah `foreign` pada `ALTER TABLE`") **tidak akurat**
untuk versi Laravel yang terpasang: SQLite **mendukung** penambahan FK lewat `ALTER TABLE`. **Namun
perilaku migrasi tetap sesuai dokumentasi** karena guard eksplisit
`Schema::getConnection()->getDriverName() === 'sqlite'` (migrasi baris 51–53) **kembali lebih awal
sebelum** `foreign()` dipanggil. Jadi FK memang **tidak** dibuat di SQLite — alasannya guard disengaja,
**bukan** keterbatasan driver.

**Konsekuensi yang dicatat:** self-FK `submissions.parent_submission_id` **tidak pernah dilatih** di suite
tes SQLite, dan **belum pernah** diverifikasi di MariaDB. Guard dapat dipertahankan, tetapi
**rasional di komentar migrasi perlu dikoreksi** dan/atau guard dipertimbangkan ulang agar cakupan tes
FK bertambah. Perubahan kode migrasi **tidak** dilakukan pada tahap ini (butuh keputusan pemilik, §20.9).

### 20.3 Idempotensi `up()` — celah pasca-gagal sebagian `[FAKTA SOURCE]` — **BLOCKER (rendah)**

`up()` melakukan **early-return** bila kolom sudah ada (`Schema::hasColumn(...)`, baris 42) **sebelum**
FK dibuat. Alur: (1) satu `Schema::table()` menambah kolom **+** indeks (satu statement `ALTER`, atomik
di MariaDB → kolom & indeks selalu bersama); (2) statement **kedua** menambah FK. Bila statement (2)
gagal (mis. lock/timeout), Laravel **tidak** mencatat migrasi sebagai `Ran`; menjalankannya ulang akan
early-return pada `hasColumn` → migrasi tercatat `Ran` **tanpa FK** — constraint hilang **secara senyap**.

**Rekomendasi (belum diterapkan).** Pakai pola yang sudah terbukti di
`2026_10_16_100004_add_cooperative_foreign_key_to_officers_table.php`: klasifikasikan keadaan FK
**independen** dari keberadaan kolom (`Schema::getForeignKeys()`) dan `throw` bila definisi ambigu.
Diterapkan **hanya setelah** keputusan pemilik (aturan migrasi, §20.9).

### 20.4 `down()` — catatan risiko `[FAKTA SOURCE]`

`down()` memanggil `dropForeign([COLUMN])` yang mengandalkan **nama konvensional**
`submissions_parent_submission_id_foreign`. Bila FK pada kolom itu dibuat manual dengan nama berbeda,
`down()` gagal (`errno 1091`). Pola `2026_10_16_100004` menghindari risiko ini dengan `down()` kosong.
Tidak ada perubahan diterapkan.

### 20.5 Audit serialisasi T-1 (read-only) `[FAKTA SOURCE]`

- `createDraft()`: `lockCooperativeAnchor()` → `assertNoConflictingActive()` → buat record, **dalam satu
  `DB::transaction`** (baris 60–64).
- `createRevision()`: `lockCooperativeAnchor($parent->cooperative_id)` **lebih dulu** (baris 107) → baca
  ulang induk **di dalam** transaksi (baris 111) → validasi ketersediaan/status/`isSuperseded()` →
  `assertNoConflictingActive(ignoreIds: [induk])` → buat record anak.
- **Urutan lock konsisten** (`cooperatives` dikunci lebih dulu di semua jalur) → tidak ada urutan lock
  terbalik (risiko deadlock).
- **Jalur pembuatan pengajuan: hanya dua** (`Pengelola\SubmissionController` baris 120 & 139).
  `new Submission` di `SubmissionService::transientSubmission()` dan `CompletenessReport` adalah instance
  **tidak disimpan** (probe), bukan jalur pembuatan.
- **Tidak ada jalur alternatif** yang melewati mekanisme: `updateDraft()`/`submit()` tidak membuat baris
  baru; **tidak ada** aksi/route `restore` untuk `Submission` (`SubmissionPolicy::restore()` → `false`).
- **Penanganan kegagalan**: pengecualian di dalam transaksi → rollback; tidak meninggalkan pengajuan aktif
  ganda.
- **Batas bukti:** `lockForUpdate()` **no-op** di SQLite → efektivitas konkurensi **belum** terbukti pada
  driver apa pun. Tes SQLite hanya membuktikan **logika** (urutan pemanggilan & penolakan konflik).
- **Catatan (bukan blocker):** kepemilikan aktor↔koperasi divalidasi **Policy di controller**
  (server-side, teruji 403). Service tidak memeriksa ulang kepemilikan (defense-in-depth opsional); tidak
  ada jalur produksi yang memanggil service tanpa otorisasi controller.

### 20.6 Verifikasi Tahap 67 `[FAKTA]`

| Perintah | Hasil |
| --- | --- |
| `php artisan test` | **356 tes / 1.312 asersi PASS** (SQLite `:memory:`; baseline Tahap 66: 355/1.305) |
| `vendor/bin/pint --test` | **PASS** |
| `npm run build` | **PASS** |
| `git diff --check` | **bersih** (OK) |
| `php artisan migrate:status` | **22 `Ran` + 1 `Pending`** (`2026_10_19_000001_*`) |

Angka **bukan** bukti kompatibilitas MariaDB. Tes per kelas terkait: `AdminDashboardTest` 18/92.

### 20.7 Matriks blocker F6 (status per 2026-10-19, Tahap 67)

| # | Item | Status | Bukti / alasan | Yang dibutuhkan untuk menutup |
| --- | --- | --- | --- | --- |
| 1 | **T-1** serialisasi pembuatan pengajuan | **Terbuka** — pengamanan tingkat aplikasi; logika teruji, efektivitas lock **belum** terbukti | §20.5; `lockForUpdate` no-op di SQLite | Uji konkurensi **dua koneksi MariaDB** (bukti runtime) |
| 2 | **Migrasi** `2026_10_19_000001_*` | **Pending** di DB dev; audit source belum cukup | `migrate:status` (22 Ran + 1 Pending); §19.2, §20.2–20.4 | **Izin eksplisit** pemilik + **backup terverifikasi**; jalankan migrasi pada DB dev lalu verifikasi FK/indeks |
| 3 | **Q-006** definisi kelengkapan FR-030 | **Terbuka** — `[ASUMSI]` | §16/§17.2 | Jawaban dinas (tidak dikarang) |
| 4 | **Q-010** definisi periode | **Terbuka** — pada Tahap 67 **FR-160 masih Sebagian** (filter periode daftar koperasi belum ada; **dieksekusi provisional pada §31.1**) | §17.4 (D-5 ditunda saat itu) | Jawaban dinas |
| 5 | **Q-012** referensi & semantik wilayah | **Terbuka** — `regions` kosong; label wilayah **tidak** didisambiguasi | §19.1, §20.1 (F6-4) | Jawaban dinas |
| 6 | **R-4** pengelompokan wilayah per `region_id` | **Diperbaiki & teruji**; **menunggu verifikasi** runtime MariaDB | §19.1, §20.1 (+1 tes regresi) | Verifikasi `GROUP BY` pada MariaDB (tanpa `ONLY_FULL_GROUP_BY` error) |
| 7 | **Commit/push** Tahap 56–67 | **Belum diotorisasi** | §20.8 | Izin eksplisit pemilik |
| 8 | **F7** Direktori Publik | **Belum boleh dimulai** (pada Tahap 67) | urutan fase (F6 belum ditutup) | Penutupan F6 oleh pemilik; **F7 kemudian selesai pada §31** |

**Tidak ada** baris di atas yang ditandai "selesai" pada Tahap 67. Requirement **tidak** diubah agar
tampak sesuai implementasi.

> **Pembaruan Tahap 68.** Item **2** (migrasi) — celah idempotensi `up()` **sudah diperbaiki** (kode +
> 20 tes baru; §21.1/§21.6). Status migrasi **tetap `Pending`** (belum dijalankan pada DB apa pun).
> Item lain **tidak berubah**. Matriks terkini: §21.5.
>
> **Pembaruan Tahap 70.** Berdasarkan audit Tahap 69, ditambahkan **kebijakan kepemilikan indeks** dan
> dokumentasi konsekuensi destruktif `down()` (§21.2), penguatan tes, serta koreksi dokumentasi —
> ringkasan: §21.7; verifikasi: §21.8. Status migrasi **tetap `Pending`**; item lain **tidak berubah**.

### 20.8 Status Git & perlindungan perubahan lokal `[FAKTA]`

- Branch `main`; `HEAD` **`269461897ef51364f9b6ffc3ebf40515427c3897`**; `origin/main`
  `009a29fc30f34ef5c52e86e6761584b7aad6f89a` (ahead **1**); stash **kosong**.
- Perubahan lokal Tahap 56–67 **dipertahankan** — tidak ada `commit`/`push`/`reset`/`checkout`/`restore`/
  `clean`/`stash` pada Tahap 67. `git diff --check` bersih.
- Titik pulih: backup terverifikasi `C:\Users\mocha\Documents\koperasi_jabar_20261019_040534.sql`.

### 20.9 Keputusan yang masih membutuhkan izin eksplisit (Tahap 67)

1. **Menjalankan migrasi** `2026_10_19_000001_*` pada DB dev (setelah backup terverifikasi) + verifikasi
   FK/indeks, lalu **uji konkurensi MariaDB** untuk T-1 (rencana ada di laporan Tahap 66 §4).
2. **Commit/push** perubahan Tahap 56–67.
3. **Koreksi/penataan migrasi** untuk menutup celah idempotensi §20.3 (dan/atau rasional SQLite §20.2) —
   menyentuh berkas migrasi sehingga butuh keputusan khusus sesuai aturan migrasi.
4. **Constraint DB** (mis. unique index bersyarat) sebagai pengganti serialisasi aplikasi T-1, bila
   dinilai perlu setelah uji konkurensi.
5. **Lingkup R-4** — bila perubahan `CooperativeReport::byRegion()` dinilai di luar lingkup, mudah
   dikembalikan.
6. **D-5** (filter periode daftar koperasi) pada Tahap 67 hanya setelah **Q-010** dijawab;
   **dieksekusi secara provisional pada finalisasi 24 Oktober 2026 (§31.1)**.

---

## 21. Idempotensi migrasi, kebijakan rollback & koreksi dokumentasi (Tahap 68 & 70)

> Ditulis pada **Tahap 68**. Mengikuti instruksi pemilik untuk menutup dua temuan source-level Tahap 67
> (§20.2 & §20.3). **F6 tetap MENUNGGU PENINJAUAN.** Tidak ada migrasi/DDL/DML yang dijalankan pada
> database persisten, tidak ada `commit`/`push`, F7 tidak dimulai.
>
> **Dilanjutkan pada Tahap 70** — menindaklanjuti audit independen Tahap 69 (temuan F-69-01…F-69-11).
> Ringkasan tindakan: §21.7. Verifikasi: §21.8. **Tidak ada** migrasi/DDL/DML pada database persisten,
> tidak ada `commit`/`push`, F7 tidak dimulai, F6 tetap **MENUNGGU PENINJAUAN**.

### 21.1 Perbaikan idempotensi `up()` `[DIPERBAIKI — kode + tes]`

**Masalah (§20.3).** `up()` lama melakukan `return` begitu `Schema::hasColumn()` benar, **sebelum**
memeriksa/membuat FK. Bila statement FK gagal pada eksekusi pertama, Laravel tidak mencatat migrasi
sebagai `Ran`; eksekusi ulang akan berhenti di kolom → migrasi tercatat `Ran` **tanpa FK**.

**Perbaikan.** `up()` kini memeriksa **tiga komponen secara terpisah**:

| Langkah | Perilaku |
| --- | --- |
| Kolom | dibuat hanya bila `Schema::hasColumn()` = false |
| Indeks | dinilai lewat **kepemilikan** (`classifyIndex()`, Tahap 70): `missing` → dibuat; `present` → dilewati; `conflict` → `RuntimeException` diagnostik |
| Foreign key | `classify()` → `missing` dibuat; `present` dilewati; `conflict` → `RuntimeException` diagnostik |

- **Tidak menghapus/menimpa** kolom, indeks, FK, atau data yang sudah ada.
- **Nama constraint tidak dipakai** untuk menilai kesesuaian FK: FK ekuivalen dengan nama berbeda tetap
  `present` (tidak dibuat ulang).
- **Indeks justru dinilai dari identitas** (Tahap 70, §21.2): nama `self::INDEX` **dan** definisi
  (kolom tunggal `parent_submission_id`, non-unik, bukan primary key). Indeks bernama lain **tidak**
  diadopsi sebagai pengganti — migrasi membuat indeksnya sendiri.
- `parent_submission_id` tetap **nullable**; FK tetap `ON DELETE SET NULL`; `ON UPDATE` menerima
  `restrict`/`no action` (default MySQL/MariaDB).
- Bila statement FK gagal, eksekusi ulang kini **melengkapi** FK (bukan melewatinya).
- **Penjagaan urutan (F-69-05):** bila tabel `submissions` belum ada, `up()` kembali tanpa efek —
  pola sama dengan `2026_10_16_100004_add_cooperative_foreign_key_to_officers_table.php`. Tabel itu
  dibuat lebih dulu (`2026_10_16_130001_create_submissions_table`), jadi kondisi tersebut praktis hanya
  mungkin bila migrasi dijalankan terpisah (`migrate --path`). **Perilaku tidak diubah** pada Tahap 70.
- **Serialisasi (F-69-06):** Laravel versi ini **tidak** membungkus migrasi dalam transaksi skema pada
  MySQL/MariaDB **maupun** SQLite (`Grammar::$transactions = false`) dan tidak menyediakan kunci migrasi.
  Karena itu **dua proses `migrate` tidak boleh dijalankan serentak**; idempotensi mengurangi akibat
  kegagalan parsial, **bukan** menggantikan serialisasi.

### 21.2 Kebijakan `down()` & kepemilikan indeks `[DIPERBAIKI — kode + tes]`

- FK dilepas **hanya** bila `classify()` = `present`, memakai **nama constraint yang dilaporkan driver**
  (`foreignKeysToDrop()`), bukan asumsi nama konvensional.
- FK **menyimpang** atau **tanpa nama** **tidak** dihapus.
- **Kepemilikan indeks (Tahap 70, F-69-01/F-69-04).** `down()` melepas indeks **hanya** bila namanya
  `self::INDEX` **dan** definisinya sesuai (kolom tunggal, non-unik, bukan primary key). Indeks bernama
  lain — termasuk **unik**, **komposit**, dan **primary key** — **tidak pernah** dihapus; bila nama
  `self::INDEX` ternyata dipakai untuk definisi berbeda, `down()` **berhenti** dengan diagnostik
  (`RuntimeException`) alih-alih menghapus objek yang kepemilikannya tidak terbukti. Ini menutup
  penghapusan spekulatif yang sebelumnya mungkin terjadi.
- **Urutan & titik gagal (F-69-03).** Urutan pelepasan: FK → indeks milik migrasi → kolom. Pelepasan
  indeks sebelum kolom diperlukan karena SQLite (≥ 3.35) menolak `ALTER TABLE … DROP COLUMN` bila kolom
  masih terindeks. **Di MariaDB ini bukan jaminan satu titik gagal:** `DROP INDEX` dapat **gagal lebih
  awal** (`errno 1553` — index needed in a foreign key constraint) apabila FK yang menyimpang masih
  bergantung pada indeks tersebut; kegagalan karenanya dapat muncul pada pelepasan indeks **atau** kolom.
- **Konsekuensi destruktif (F-69-02).** `down()` menghapus kolom `parent_submission_id` → **relasi
  rantai versi hilang permanen**; baris pengajuan tetap ada, tetapi versi anak kehilangan tautan ke
  induknya dan tidak ada pemulihan otomatis. `down()` hanya boleh dijalankan dengan **persetujuan
  eksplisit** pemilik setelah mengevaluasi dampaknya terhadap data relasi. *Keselamatan teknis ≠
  kelayakan bisnis.* Tidak ada mekanisme yang menyamarkan/menutupi konsekuensi ini.
- **Efek pada indeks lain.** Menghapus kolom juga memengaruhi indeks lain yang memuat kolom itu sesuai
  perilaku driver (mis. MariaDB mengeluarkan kolom dari indeks komposit). Itu konsekuensi `dropColumn`,
  bukan penghapusan eksplisit oleh migrasi.
- `down()` **idempoten**: pemanggilan ulang setelah kolom hilang langsung kembali.
- **Batasan (terdokumentasi):** bila FK/indeks menyimpang masih ada setelah pelepasan, `dropColumn`
  (atau `DROP INDEX`) **gagal keras** — bukan diam-diam meninggalkan skema setengah jadi.

### 21.3 Batas bukti `[FAKTA]`

- Seluruh tes berjalan pada **SQLite `:memory:`**. Migrasi **sengaja** melewati pembuatan self-FK pada
  driver SQLite (`supportsForeignKey()`). Suite `ParentSubmissionForeignKeyTest` karena itu
  **SQLite-only** (F-69-10) — tes yang bergantung pada keadaan driver diberi nama berawalan
  `test_sqlite_only_`.
- Tes skema membuktikan: kolom, indeks, idempotensi `up()`, kebersihan `down()`, tidak hilangnya
  data/FK lain, serta baris tetap ada setelah rollback (kolom tautan versi dihapus).
- Logika keputusan FK (`classify()`, `foreignKeysToDrop()`) dan kepemilikan indeks (`classifyIndex()`)
  diuji **unit** dengan metadata berbentuk kembalian `Schema::getForeignKey()`/`getIndexes()`; cabang
  driver diuji dengan **mock facade** (tanpa DDL).
- **Definisi FK yang diminta** (kolom, `references`, `on`, `onDelete = set null`, nama constraint)
  dibuktikan dua cara tanpa MariaDB: (a) **assertion source** pada berkas migrasi, dan (b) **menangkap
  perintah Blueprint** pada jalur `up()`. Keduanya membuktikan **niat/perintah**, **bukan** DDL nyata.
- **Yang belum terbukti saat itu:** DDL pembuatan FK pada MariaDB (termasuk perilaku
  `ON DELETE SET NULL` **pada runtime**), eksekusi nyata `dropForeign`/`dropIndex` pada MariaDB,
  efektivitas `lockForUpdate()` T-1, dan idempotensi pada kondisi skema MariaDB yang belum pernah
  dijalankan. DDL/FK kemudian diuji pada MariaDB 10.11.19 lokal (34.3); efektivitas konkurensi
  `lockForUpdate()` tetap belum diuji.
- Catatan teknis: `SQLiteProcessor::processForeignKeys()` mengembalikan `name => null`, sehingga
  penghapusan FK berdasarkan nama memang tidak mungkin pada SQLite — konsisten dengan guard driver.

### 21.4 Koreksi dokumentasi SQLite `[DIKOREKSI]`

Klaim yang **tidak akurat** dan sudah dikoreksi (Tahap 67/68):
- §17.1 & §19.2 (`DECISIONS.md`) — "SQLite mengabaikan penambahan FK via `ALTER TABLE`" → **salah** untuk
  Laravel 13 (`SQLiteGrammar::getAlterCommands()` memuat `foreign`; `compileAlter()` melakukan rebuild
  tabel termasuk FK).
- `docs/DATA_DICTIONARY.md` §14 — catatan portabilitas diperbarui.

**Fakta implementasi yang berlaku sekarang:** migrasi **secara eksplisit melewati** self-FK pada driver
SQLite (pilihan, bukan keterbatasan). Konsekuensinya self-FK dari migrasi ini **tidak diverifikasi** oleh
suite SQLite; validasinya **memerlukan MariaDB**. Keberhasilan tes SQLite **tidak** disimpulkan sebagai
kompatibilitas MariaDB.

### 21.5 Matriks blocker F6 — pembaruan Tahap 68 & Tahap 70

| # | Item | Status | Perubahan |
| --- | --- | --- | --- |
| 1 | **T-1** konkurensi | **Terbuka** | tidak berubah (belum ada bukti MariaDB) |
| 2 | **Migrasi** `2026_10_19_000001_*` | **`Pending`**; idempotensi **diperbaiki**; DDL MariaDB **belum** diverifikasi | §20.3 → **tertutup di level source+tes** (tidak berubah Tahap 70) |
| 3 | **Q-006** FR-030 | **Terbuka** (`[ASUMSI]`) | tidak berubah |
| 4 | **Q-010** periode / FR-160 | **Terbuka** (FR-160 Sebagian) | tidak berubah |
| 5 | **Q-012** wilayah | **Terbuka** | tidak berubah |
| 6 | **R-4** `region_id` | **Menunggu verifikasi** MariaDB | tidak berubah |
| 7 | **Commit/push** | **Belum diotorisasi** | tidak berubah |
| 8 | **F7** | **Belum boleh dimulai** | tidak berubah |
| 9 | **F-69-01** kepemilikan indeks | **Diputuskan & diterapkan** (kode + tes) | kebijakan baru: nama + definisi; indeks pihak lain tak pernah dihapus |
| 10 | **F-69-02** `down()` destruktif | **Didokumentasikan** (docblock + §21.2) | tanpa mekanisme yang menyamarkan konsekuensi |
| 11 | **F-69-08** cakupan tes | **Sebagian** | assertion source + perintah Blueprint + `down()` bermock + baris pasca-rollback; **sisa** menuntut MariaDB |

**F6 tetap MENUNGGU PENINJAUAN.** Tidak ada requirement yang diubah.

> **Pembaruan Tahap 84.** Baris **8 (F7)** diperbarui oleh **keputusan pemilik**: F7 **diizinkan
> dimulai** melalui **pengecualian urutan fase** (§22), sementara **F6 tetap MENUNGGU PENINJAUAN**.
> Baris **1–7 dan 9–11 tidak berubah** — verifikasi MariaDB, migrasi `2026_10_19_000001_*` yang masih
> `Pending`, Q-006/Q-010/Q-012, R-4, dan otorisasi `commit`/`push` semuanya **masih terbuka**.
> Matriks §20.7 baris 8 (arsip historis) **tidak** diubah agar jejak audit tetap utuh.

### 21.6 Verifikasi Tahap 68 `[FAKTA HISTORIS]`

| Perintah | Hasil |
| --- | --- |
| `php artisan test` | **376 tes / 1.390 asersi PASS** (SQLite `:memory:`) |
| `vendor/bin/pint --test` | **PASS** |
| `npm run build` | **PASS** |
| `git diff --check` | **bersih** |
| `php artisan migrate:status` | **22 `Ran` + 1 `Pending`** (tidak ada migrasi dijalankan) |

Tes baru: `tests/Feature/ParentSubmissionForeignKeyTest.php` — **20 tes / 78 asersi**, mencakup:
(1) kolom+indeks belum ada → dibuat; (2) kolom ada tetapi FK/indeks belum ada → dilengkapi;
(3) FK ekuivalen sudah ada → tidak ada DDL; (4) pengulangan `up()` tidak menghapus data/merusak skema;
serta klasifikasi `missing`/`present`/`conflict` dan kebijakan `down()`.

**F-69-09 — klaim regresi A/B ([TIDAK DAPAT DIVERIFIKASI]).** Laporan Tahap 68 menyatakan bahwa dengan
mengembalikan logika `up()` pra-Tahap-68 (early-`return` pada `hasColumn` sebelum pembuatan FK),
**6 dari 20 tes gagal**; contoh yang disebut adalah
`test_up_does_not_stop_at_the_column_when_the_foreign_key_is_missing`. **Daftar lengkap keenam nama tes
tidak tercatat di artefak mana pun** (tidak ada di `docs/`, berkas migrasi dipulihkan byte-identik
SHA-256 `6978867DBF8D647C3720EDC069DC076D0D8044C3B856D9B95A3D1336859B80D1`, cadangan sementara dihapus).
Karena itu **kelima nama lainnya dinyatakan belum dapat diverifikasi** — tidak dikarang. Prosedur
reproduksi yang tercatat: salin sementara badan `up()` pra-Tahap-68 → jalankan
`php artisan test tests/Feature/ParentSubmissionForeignKeyTest.php` → pulihkan berkas. Perlu dicatat
bahwa revisi Tahap 70 mengubah berkas migrasi sehingga hash tersebut kini menunjuk **revisi Tahap 68**,
bukan versi berjalan.

### 21.7 Tindak lanjut audit Tahap 69 (Tahap 70) `[FAKTA]`

| Temuan | Tingkat | Tindakan Tahap 70 |
| --- | --- | --- |
| **F-69-01** | Sedang | `classifyIndex()` berbasis **nama + definisi**; `down()` hanya melepas `self::INDEX`; indeks pihak lain (unik/komposit/primary) **tidak** dihapus; nama dipakai ulang untuk definisi lain → diagnostik. Tes: unit `classifyIndex()` (present/missing/conflict) + mock `up()`/`down()` |
| **F-69-02** | Sedang | Docblock migrasi + §21.2 menyatakan rollback **destruktif** terhadap tautan versi dan menuntut persetujuan eksplisit; tes `test_down_keeps_rows_but_drops_the_version_link_column` |
| **F-69-03** | Rendah | §21.2 dikoreksi: **tidak** menjanjikan satu titik gagal (`DROP INDEX` dapat gagal lebih awal, errno 1553) |
| **F-69-04** | Rendah | Kepemilikan indeks memeriksa definisi (kolom tunggal, non-unik, bukan primary); unik/komposit/primary tidak dianggap ekuivalen |
| **F-69-05** | Rendah | **Perilaku dipertahankan** (kembali tanpa efek) karena sejalan dengan pola `2026_10_16_100004` dan urutan migrasi (tabel dibuat di `2026_10_16_130001`); didokumentasikan + tes `test_up_returns_without_effect_when_the_referenced_table_is_absent` |
| **F-69-06** | Rendah | Persyaratan **serialisasi eksekusi migrasi** dicatat (§21.1, §21.2 docblock); tidak diklaim bahwa idempotensi menghapus risiko konkurensi |
| **F-69-07** | Rendah | Tes urutan tidak lagi hanya menghitung pemanggilan: perintah Blueprint ditangkap → urutan `['index','foreign']` diperiksa |
| **F-69-08** | Rendah | Ditambah: assertion source FK (a), tes `down()` bermock untuk FK menyimpang/tanpa nama (b), tes baris tetap ada pasca-rollback (c), skenario indeks unik/komposit/primary (d). **Sisa** yang menuntut MariaDB tetap terbuka (§21.3) |
| **F-69-09** | Rendah | Dicatat sebagai **[TIDAK DAPAT DIVERIFIKASI]** di §21.6 (hanya satu nama tes yang terdokumentasi) |
| **F-69-10** | Rendah | Docblock suite menyatakan **SQLite-only**; tes driver dinamai `test_sqlite_only_*` |
| **F-69-11a** | Rendah | Angka tes historis di `docs/VERSIONS.md` ditandai **historis** |
| **F-69-11b** | Rendah | Dicatat bahwa berkas migrasi **Pending** disempurnakan pada Tahap 68 (dan Tahap 70) **tanpa** menyiratkan migrasi dijalankan |
| **F-69-11c** | Rendah | Risiko operasional `->after('id')` didokumentasikan sebagai **perlu diverifikasi** pada versi/konfigurasi MariaDB target — tanpa klaim rebuild/locking |

**Tidak ada** perubahan DDL/DML pada database persisten; migrasi tetap **`Pending`**. Tidak ada item F6
yang ditutup pada Tahap 70.

### 21.8 Verifikasi Tahap 70 `[FAKTA]`

| Perintah | Hasil |
| --- | --- |
| `php artisan test tests/Feature/ParentSubmissionForeignKeyTest.php` | **36 tes / 169 asersi PASS** (SQLite `:memory:`) |
| `php artisan test` | **392 tes / 1.481 asersi PASS** (SQLite `:memory:`; baseline Tahap 68: 376/1.390) |
| `vendor/bin/pint --test` | **PASS** |
| `npm run build` | **PASS** |
| `git diff --check` | **bersih** |

Angka **bukan** bukti kompatibilitas MariaDB. `php artisan migrate:status` (read-only) dijalankan ulang:
**22 `Ran` + 1 `Pending`** — `2026_10_19_000001_*` **masih `Pending`** (tidak ada migrasi yang dijalankan).

Integritas berkas (SHA-256, revisi Tahap 70):
`database/migrations/2026_10_19_000001_add_parent_submission_id_to_submissions_table.php`
`639056E56642B5E7FDE51E1590050EEF1C381369D40BD7D4919F8C085B8F6FCC`;
`tests/Feature/ParentSubmissionForeignKeyTest.php`
`02573B51A68AAFE438D42328507F8A99313A9341A0E69CF13B954FA8C1C99ABC`.
(Revisi Tahap 68 dari berkas migrasi: `6978867DBF8D647C3720EDC069DC076D0D8044C3B856D9B95A3D1336859B80D1`.)

---

## 22. Pengecualian urutan fase — F7 dimulai sebelum F6 ditutup (Tahap 84)

> **Keputusan pemilik.** F7 (Direktori Publik) **diizinkan dimulai** melalui **pengecualian
> urutan fase**, meskipun F6 belum ditutup. Pengecualian ini **hanya** mengubah gerbang
> *dimulainya* F7 — **bukan** status F6, bukan persetujuan deployment, dan bukan otorisasi
> `commit`/`push`.

### 22.1 Alasan (berdasarkan pemetaan Tahap 83)

F7 **tidak** bergantung pada migrasi atau perubahan database F6:

- F7 hanya membaca kolom `cooperatives` yang sudah ada sejak **F2** (`slug`, `is_published`,
  `published_at`, `published_by`, `status_verifikasi`) —
  `database/migrations/2026_10_16_100002_create_cooperatives_table.php`.
- F7 **tidak** memakai `submissions.parent_submission_id` (D-1 F6) maupun alur/agregasi laporan F6
  (`CooperativeReport`, `DashboardStatistics`, dsb.).
- Karena itu migrasi F6 `2026_10_19_000001_*` yang masih **`Pending`** **bukan** prasyarat F7.

Rincian pemetaan & matriks dependensi: laporan Tahap 83 (README tahap, bukan dokumen permanen).

### 22.2 Batas pengecualian (yang **tidak** berubah)

| Hal | Status |
| --- | --- |
| **F6** | tetap **MENUNGGU PENINJAUAN** — tidak ditutup, tidak dinyatakan selesai/aman |
| **Verifikasi MariaDB F6** (DDL FK, `ON DELETE SET NULL`, T-1, R-4, errno 1553/3780) | **masih tertunda** — tidak ada bukti runtime baru |
| **Migrasi** `2026_10_19_000001_*` | tetap **`Pending`** — tidak dijalankan |
| **Otorisasi `commit`/`push`** | tetap **belum diberikan** |
| **F8** | **belum dimulai** |
| **Q-008 / Q-012 / Q-014** | tetap **terbuka** — tidak dikarang |
| **Kebutuhan tabel `settings` (FR-191)** | **belum** dibuat; butuh persetujuan eksplisit tersendiri |

### 22.3 Batas implementasi F7 pada tahap ini

- F7 **wajib** mengikuti PRD & kriteria penerimaan (`AC-PUB-01/02/03`) sebagai sumber kebenaran.
- **Tidak ada** migrasi/seeder/DDL/DML/rollback pada DB persisten.
- Tabel `settings` **tidak** dibuat; kebijakan field publik memakai **allowlist lokal** di
  `config('koperasi.public.*')` — fallback yang diizinkan `docs/PROMPT_PACK.md` §F7 (Q-008 terbuka).
- Kontrol publikasi admin (FR-190) **belum** diimplementasikan — dicatat sebagai pekerjaan sisa F7.

### 22.4 Konflik dokumentasi path publik (isu terpisah)

`PRD.md` §9 (L894) menetapkan path publik `/koperasi` & `/koperasi/{slug}`; `PROMPT_PACK.md` §F7
(L194) dan `ROADMAP_REMAINING.md` §F7 (L237–238) memakai `/public/direktori` &
`/public/koperasi/{slug}`. **Implementasi mengikuti PRD** (satu path, tanpa route duplikat).
Penyamaan penamaan di PROMPT_PACK/ROADMAP dicatat sebagai **isu dokumentasi terpisah** yang belum
dikerjakan.

### 22.5 Ruang lingkup yang diimplementasikan Tahap 84 `[FAKTA]`

| Komponen | Berkas |
| --- | --- |
| Rute publik (`web` saja, tanpa auth) | `routes/web.php` — `GET /koperasi` (`publik.direktori`), `GET /koperasi/{slug}` (`publik.koperasi`) |
| Controller (query ketat + allowlist + masking) | `app/Http/Controllers/Publik/KoperasiPublikController.php` |
| Masking data pribadi (NFR-SEC-015) | `app/Support/PiiMask.php` |
| Allowlist & status publik (fallback Q-008) | `config/koperasi.php` → `koperasi.public.*` |
| Tampilan | `resources/views/layouts/public.blade.php`, `resources/views/publik/direktori.blade.php`, `resources/views/publik/profil.blade.php` |
| Tes | `tests/Feature/Publik/DirektoriPublikTest.php` (13 tes / 80 asersi) |

**Penjagaan inti:** satu query dasar (`visibleQuery()`) dipakai direktori, pencarian, filter, **dan**
profil → koperasi dengan `is_published ≠ true` atau status di luar `koperasi.public.visible_statuses`
tidak dapat dijangkau lewat URL mana pun (`404`, tanpa membocorkan keberadaan/isi). Pilihan filter
jenis & wilayah **diturunkan dari data terpublikasi** (bukan daftar resmi karangan) sehingga Q-003/Q-012
tetap tidak dijawab secara sepihak.

**Urutan registrasi rute (regresi dijaga tes):** blok publik diletakkan **setelah** grup internal
`/koperasi` agar `/koperasi/{slug}` tidak menutupi `/koperasi/dashboard`, `/koperasi/profil`, dst.
Dibuktikan `test_internal_routes_remain_protected_and_are_not_shadowed_by_the_public_profile_route`.

**Belum dikerjakan (sisa F7):** kontrol publikasi admin (FR-190), tabel `settings` + relokasi allowlist
(Q-008), referensi wilayah resmi (Q-012), `published_by`/`published_at` belum diisi siapa pun, dan
`/statistik` (FR-183) yang berada di **FASE-2**.

*(Catatan Tahap 85: FR-190 **sudah dikerjakan** — lihat §23. Sisa F7 menjadi: tabel `settings` +
Q-008, Q-012, dan FR-183/FASE-2.)*

---

## 23. Kontrol publikasi admin FR-190 (Tahap 85)

> Ditulis pada **Tahap 85**. **F6 tetap MENUNGGU PENINJAUAN**, verifikasi MariaDB F6 tetap tertunda,
> **F7 masih berjalan** (belum selesai), **F8 belum dimulai**. Tidak ada migrasi/seeder/DDL/DML pada
> database persisten dan tidak ada `commit`/`push`.

### 23.1 Implementasi & otorisasi `[FAKTA]`

| Aspek | Keputusan |
| --- | --- |
| Endpoint | `POST /admin/koperasi/{cooperative}/publikasikan` (`admin.koperasi.publish`) dan `POST /admin/koperasi/{cooperative}/tarik-publikasi` (`admin.koperasi.unpublish`) |
| Middleware | `web`, `auth`, `active`, `role:admin_dinas` (pola admin yang sudah ada — **tidak** ada mekanisme baru) |
| Kewenangan | `CooperativePolicy::publish()` — admin dinas; dipakai **baik** untuk menerbitkan maupun menarik. Policy menjawab "siapa"; kelayakan diperiksa di controller |
| Metode HTTP | `POST` (bukan GET) + CSRF; GET ke endpoint → **405** |
| Kelayakan terbitkan | `config('koperasi.public.visible_statuses')` = `['approved']` — **konfigurasi yang sama** dengan direktori publik (`Publik\KoperasiPublikController::visibleQuery()`), sehingga koperasi yang lolos pasti tampil di direktori dan sebaliknya |
| `published_at` | Diisi **sistem** (`now()`) saat menerbitkan |
| `published_by` | Diisi **sistem** dengan ID admin yang benar-benar menekan aksi |
| `is_published` | `true` saat menerbitkan; `false` saat menarik |
| Status verifikasi | **Tidak diubah** oleh kedua aksi (tidak menjadi `rejected`/`archived`; tidak di-soft delete) |
| Audit | `publish_cooperative` / `unpublish_cooperative` lewat `ActivityLogger` (FR-150) |

**Nilai publikasi tidak pernah berasal dari input pengguna.** `published_at`/`published_by`/`is_published`
memang ada di `$fillable`, tetapi `StoreCooperativeRequest`/`UpdateCooperativeRequest` tidak memiliki aturan
untuk field tersebut sehingga `validated()` menyaringnya — dibuktikan tes
`test_forged_publication_fields_in_the_publish_request_are_ignored` dan
`test_admin_update_form_cannot_forge_publication_fields`.

**Penolakan yang terbukti:** tamu → redirect login; pengelola koperasi (bahkan pemilik data) dan akun
terautentikasi tanpa peran → **403**; koperasi `archived`/soft-deleted → **404** (route binding);
status verifikasi non-`approved` → pesan `error` (bukan diterbitkan); terbitkan saat sudah terbit /
tarik saat belum terbit → pesan `error` tanpa menstempel ulang.

### 23.2 Keputusan bisnis yang MASIH diperlukan (tidak dikarang) `[STATUS DIPERBARUI TAHAP 105 → §28]`

- **D-6 — pengosongan `published_at`/`published_by` saat publikasi ditarik.** **DIPUTUSKAN (Tahap 105):**
  jejak dipertahankan, dan penarikan dicatat pada `unpublished_at`/`unpublished_by`. Lihat §28.2.
- **Q-008** **DIPUTUSKAN (Tahap 105)** → allowlist `config('koperasi.public.*')` dipertahankan sebagai
  kebijakan resmi; tabel `settings` **tidak** dibuat. Lihat §28.1.
- **Q-012/Q-014** **DIPUTUSKAN secara kebijakan (Tahap 105)**, tetapi **implementasi masih terblokir**
  oleh data wilayah resmi dan akses staging MariaDB. Lihat §28.3/§28.4.

### 23.3 Verifikasi Tahap 85 `[FAKTA]`

| Perintah | Hasil |
| --- | --- |
| `php artisan test tests/Feature/Admin/CooperativePublicationTest.php` | **17 tes / 111 asersi PASS** (SQLite `:memory:`) |
| `php artisan test` | **436 tes / 1.794 asersi PASS** (baseline Tahap 84: 419/1.683) |
| `php -l` (4 berkas) | **No syntax errors** |
| `vendor/bin/pint` + `pint --test` | **PASS** |
| `git diff --check` | **bersih** |
| `php artisan route:list --name=koperasi.publish(/unpublish)` | `POST`, middleware `web, auth, active, role:admin_dinas` |

Angka **bukan** bukti kompatibilitas MariaDB.

### 23.4 Sisa F7 setelah Tahap 85

- **FR-191 penuh** — tabel `settings` + jawaban **Q-008** (butuh persetujuan eksplisit).
- **Q-012** (referensi wilayah) & **D-6** (§23.2).
- **FR-183 `/statistik`** — FASE-2, belum diimplementasikan.
- Isu dokumentasi: penamaan `/public/...` vs `/koperasi` dan label **AC-PUB-02** pada
  `IMPLEMENTATION_PLAN.md`/`PROMPT_PACK.md` (PRD: "filter publik … hasil relevan").

---

## 24. Tahap 86 — Audit integrasi F7, regresi keamanan, rekonsiliasi dokumentasi `[FAKTA]`

**Bukan penutupan F7.** Audit ini memeriksa **implementasi aktual** (bukan laporan tahap sebelumnya),
menambah tes regresi hanya untuk celah nyata, dan menyelaraskan dokumentasi dengan **PRD** sebagai
sumber kebenaran. F6 **tetap MENUNGGU PENINJAUAN**; F7 **masih berjalan** (FR-191 sebagian, Q-008/Q-012
terbuka); F8 **belum dimulai**. Tidak ada migrasi/tabel `settings`, tidak ada operasi DB persisten,
tidak ada commit/push.

### 24.1 Hasil audit integrasi `[FAKTA]`

| Area | Verifikasi | Hasil |
| --- | --- | --- |
| **A. Konsistensi kelayakan publikasi** | Direktori/profil & kontrol admin membaca **konfigurasi yang sama** (`config('koperasi.public.visible_statuses')`) lewat `KoperasiPublikController::visibleQuery()` dan `CooperativeController::publicationBlocker()` | **Konsisten** (satu sumber aturan; diuji baru: `test_admin_eligibility_and_public_directory_share_one_configuration_source`) |
| | Belum dipublikasikan/tidak layak → tidak muncul di direktori, pencarian, maupun profil (404 tanpa membocorkan nama) | **Terbukti** (tes Tahap 84/85) |
| | Tarik publikasi → langsung hilang dari direktori, pencarian, profil; `status_verifikasi` **tidak** berubah | **Terbukti** |
| | Soft delete dihormati; pagination + query string filter berfungsi; filter wilayah tanpa data tidak menyesatkan | **Terbukti** (2 tes baru Tahap 86) |
| **B. Pembatasan field & PII** | View menerima **array hasil allowlist** (`present()`), bukan model/relasi | **Terbukti** — tidak ada `$cooperative` model di view publik |
| | Field sensitif tidak bocor via atribut HTML/tautan/metadata | **Tidak ada temuan** (hanya nama/jenis/wilayah/kontak termasking/website; tidak ada `region_id`, id, `alamat`, `nomor_identitas`, `status_verifikasi`) |
| | Masking email/telepon konsisten aturan PROMPT_PACK §F7 | **Terbukti** (`PiiMask` + tes unit) |
| **C. Kontrol publikasi admin** | Otorisasi berlapis: middleware `web, auth, active, role:admin_dinas` + `CooperativePolicy::publish` (Gate) | **Terbukti** |
| | GET tidak mengubah status (405); tamu → `login`; pengguna tanpa peran → 403; soft-deleted → 404 | **Terbukti** |
| | `published_at`/`published_by` dari sistem; forgery lewat request form diabaikan | **Terbukti** |
| | Audit `publish_cooperative`/`unpublish_cooperative` tercatat | **Terbukti** |
| | Aksi gagal **tidak** mengubah data apa pun | **Terbukti** (baru: `updated_at`/`updated_by` tetap + tidak ada baris `activity_logs`) |
| | **D-6** (jejak `published_at`/`published_by` setelah tarik) dipertahankan sesuai §23.2 | **Tidak diubah** (menunggu keputusan) |
| **Permukaan publik alternatif** | `route:list` (99 route) → hanya `/`, `koperasi`, `koperasi/{slug}`, `login` yang tanpa `auth` (di luar `/up`). `GET /storage/{path}` (bawaan framework) menolak tanpa *signed URL* karena disk `local` bersifat privat (`Illuminate\Filesystem\ServeFile` + disk `public` tidak di-`serve`); dokumen disimpan di `config('koperasi.document_disk') = 'local'` | **Tidak ada endpoint publik alternatif** (diuji: `PublicSurfaceExposureTest`) |
| **Halaman publik** | Tidak memuat tautan/atribut ke `/admin`, `/dokumen/`, atau `storage/` | **Terbukti** (tes baru) |

### 24.2 Temuan menurut keparahan

**Kritis: tidak ada. Tinggi: tidak ada.**

- **Sedang — slug publik dapat tertutup segmen path internal (FR-182).** `GET /koperasi/{slug}`
  didaftarkan **setelah** grup internal `/koperasi` (keputusan Tahap 84, disengaja agar
  `/koperasi/dashboard|profil|…` tidak tertutup). Akibat: koperasi terpublikasi yang slug-nya
  **persis** sama dengan segmen internal pertama (`dashboard`, `profil`, `izin`, `pengurus`,
  `kesehatan`, `tata-kelola`, `aset`, `anggota`, `pengajuan`, `notifikasi`) profil publiknya
  **tidak dapat dijangkau** — `/koperasi/{slug}` diarahkan ke route internal (tamu → `login`, non-pengelola → 403).
  `Cooperative::generateUniqueSlug()` memakai `Str::slug($nama)`, jadi koperasi bernama "Profil"/"Aset"
  menghasilkan slug tersebut. **Bukti:** `tests/Feature/Publik/DirektoriPublikTest.php`
  `test_internal_routes_remain_protected_and_are_not_shadowed_by_the_public_profile_route`
  (`GET /koperasi/profil` → redirect `login`) + `app/Models/Cooperative.php` L120–134.
  **Tanpa kebocoran data; kemungkinan kejadian sangat kecil.** Perbaikan (butuh keputusan pemilik,
  **belum** diterapkan pada tahap audit): batasi route slug dengan pola yang mengecualikan segmen
  internal terpakai, dan/atau hindari kata tercadang saat membangkitkan slug.
  → **Analisis opsi + rekomendasi: §25.4.**
  → **Sebagian diperbaiki Tahap 88 (Opsi 1 + 1b, §26):** slug **baru** tidak lagi memakai segmen
  internal dan route publik **menolak** segmen tersebut secara deterministik. Slug **lama** yang
  bertabrakan **tidak** diubah datanya sehingga path-nya tetap ditangani route internal
  (tamu → `login`, non-pengelola → 403) — perbaikan tuntasnya = Opsi 2/3 (butuh izin pemilik).
- **Rendah — duplikasi query pada direktori.** `filters()` memanggil `jenisOptions()` &
  `regionOptions()`, lalu `index()` memanggil keduanya **lagi** → 2 query tambahan per permintaan
  (`app/Http/Controllers/Publik/KoperasiPublikController.php` L56–57 & L114–119). Bukan masalah
  keamanan. Usul: hitung sekali lalu teruskan ke `filters()`.
- **Rendah — escaping `LIKE` mengandalkan karakter escape bawaan.** `escapeLike()` memakai `\`,
  yang tidak berlaku bila `sql_mode` MariaDB memuat `NO_BACKSLASH_ESCAPES`; dampaknya hanya
  relevansi pencarian (`%`/`_` diperlakukan wildcard), **bukan** kebocoran (semua hasil tetap
  koperasi terpublikasi). **Belum diverifikasi di MariaDB** (Q-014/izin MariaDB belum ada).
  → **Diperbaiki Tahap 87** (klausa `ESCAPE '!'` eksplisit + tes literal `%`/`_`/`!`; §25.1).
- **Rendah — `website` dirender sebagai `href`.** Nilai divalidasi rule `url` (`Store/UpdateCooperativeRequest`)
  dan diberi `rel="nofollow noopener" target="_blank"`, tetapi tidak ada allowlist skema eksplisit
  (`http`/`https`). Tidak ada bukti eksploitasi; dicatat sebagai pengerasan lanjutan.
  → **Diperbaiki Tahap 87** (rule `url:http,https` di 3 titik validasi + tes; §25.2).
- **Tanpa temuan:** konsistensi aturan publikasi, allowlist field, masking PII, otorisasi admin,
  POST-only, stempel sistem, anti-forgery, soft delete, 404 tanpa bocor, pagination/query string,
  filter tanpa data wilayah, serta tidak adanya endpoint publik alternatif.

### 24.3 Rekonsiliasi dokumentasi (minimal, berbasis PRD)

**Diperbaiki (PRD sebagai acuan):**
- `PROMPT_PACK.md` §F7 — label **AC-PUB-01/02/03** diselaraskan dengan **PRD §10.6** (sebelumnya
  bergeser: masking dilabeli AC-PUB-02); path `/public/direktori` & `/public/koperasi/{slug}` →
  `/koperasi` & `/koperasi/{slug}` (PRD §9, `TEST-08-01`); nama helper → `App\Support\PiiMask`;
  nama view → `publik/direktori` & `publik/profil`; `settings` ditandai **belum** dibuat; catatan footer
  "Data per tanggal" ditandai belum diterapkan; ditambah blok **catatan rekonsiliasi**.
- `PROMPT_PACK.md` §F8 — dicatat bahwa **FR-183** (`/statistik`, FASE-2) belum punya opsi prompt dan
  **di luar** MVP F7.
- `IMPLEMENTATION_PLAN.md` §F7 — status **BERJALAN (bukan selesai)**; FR-180/181/182 & FR-190 ditandai
  selesai, kontrol `is_published` selesai, **FR-191 sebagian**; §F8 diberi catatan FR-183 = FASE-2.
- `ROADMAP_REMAINING.md` §F7 — Cakupan dipecah sesuai PRD (FR-180 direktori, FR-181 pencarian/filter,
  FR-182 profil, FR-190 kontrol, FR-191 sebagian) & path `/koperasi`; Deliverables diberi tanda status.
- `DATA_DICTIONARY.md` §pemetaan FR — baris FR-180–183/190–191 diperbaiki: `settings` **belum dibuat**.
- `USER_FLOWS.md` — FLOW-PUB-01/02/03 & FLOW-ADM-08 diberi status implementasi aktual (FLOW-PUB-03
  = FASE-2, belum ada; langkah "pilih field publik" pada FLOW-ADM-08 belum ada).

**Masih terbuka (tidak diputuskan diam-diam):** isi kebijakan field publik (**Q-008**) dan referensi
wilayah resmi (**Q-012**) — keduanya menentukan dokumentasi final F7; **D-6**; **Q-014** (versi
MariaDB). Tidak ada keputusan bisnis baru yang diambil pada tahap ini.

### 24.4 Berkas yang benar-benar berubah (Tahap 86)

| Berkas | Perubahan |
| --- | --- |
| `tests/Feature/Publik/DirektoriPublikTest.php` | +2 tes: pagination & query string filter; filter wilayah tanpa data wilayah |
| `tests/Feature/Admin/CooperativePublicationTest.php` | +1 tes (satu sumber konfigurasi kelayakan) & perluasan tes status tak layak (aksi gagal tidak menyentuh `updated_at`/`updated_by`, tanpa baris audit) |
| `tests/Feature/Publik/PublicSurfaceExposureTest.php` | **baru** — halaman publik tidak menautkan area internal; `/storage/{path}` privat wajib *signed URL* |
| `docs/PROMPT_PACK.md`, `docs/IMPLEMENTATION_PLAN.md`, `docs/ROADMAP_REMAINING.md`, `docs/DATA_DICTIONARY.md`, `docs/USER_FLOWS.md` | rekonsiliasi dokumentasi (§24.3) |
| `docs/DECISIONS.md` | §24 (bagian ini) |

**Tidak ada** perubahan kode produksi, migrasi, `config`, `.env`, `phpunit.xml`, atau koneksi DB.

### 24.5 Verifikasi Tahap 86 `[FAKTA]`

| Perintah | Hasil |
| --- | --- |
| `php artisan test tests/Feature/Publik/DirektoriPublikTest.php tests/Feature/Publik/PublicSurfaceExposureTest.php tests/Feature/Admin/CooperativePublicationTest.php` | **35 tes / 238 asersi PASS** |
| `php artisan test` | **441 tes / 1.841 asersi PASS** (baseline Tahap 85: 436/1.794) |
| `vendor/bin/pint` + `pint --test` | **PASS** |
| `php -l` (3 berkas tes) | **No syntax errors** |
| `git diff --check` | **bersih** |
| `php artisan route:list` | 99 route; 4 route aplikasi tanpa `auth` (kandidat publik) sesuai rancangan |

Angka di atas **bukan** bukti kompatibilitas MariaDB (SQLite `:memory:`).

### 24.6 Keputusan yang masih dibutuhkan pemilik proyek

1. **Q-008** — field publik mana yang boleh tampil (menentukan FR-191 penuh + tabel `settings`).
2. **Q-012** — referensi wilayah resmi (saat ini `regions` kosong → filter wilayah praktis nonaktif).
3. **Q-014** & izin MariaDB — verifikasi runtime MariaDB (termasuk `NO_BACKSLASH_ESCAPES`, §24.2).
4. **D-6** — pengosongan `published_at`/`published_by` saat publikasi ditarik.
5. **Temuan sedang §24.2** — perbaikan slug yang tertutup segmen internal (butuh izin karena menyentuh
   routing/pembangkit slug).
6. Peninjauan Tahap 85 + keputusan penutupan **F6** (F6 tetap MENUNGGU PENINJAUAN).

---

## 25. Tahap 87 — Pengerasan pencarian publik, validasi URL, analisis benturan slug `[FAKTA]`

Menindaklanjuti audit Tahap 86: **dua temuan rendah diperbaiki** (terisolasi, tanpa perubahan
routing/skema DB), sementara **temuan sedang (benturan slug) hanya dianalisis** — **belum**
diimplementasikan dan masih menunggu persetujuan pemilik. F6 tetap MENUNGGU PENINJAUAN; MariaDB
belum terverifikasi; F7 masih berjalan (FR-191 sebagian); F8 kemudian selesai secara lokal (33).

### 25.1 Temuan rendah A — escaping pencarian `LIKE` (DIPERBAIKI)

**Masalah.** `KoperasiPublikController::escapeLike()` memakai backslash (`\%`, `\_`) dan menyerahkan
escape char ke default mesin SQL. Bila `sql_mode` MariaDB memuat `NO_BACKSLASH_ESCAPES`, backslash
tidak lagi menjadi escape char → `%`/`_` yang diketik pengguna kembali menjadi wildcard.

**Perbaikan.** Klausa `ESCAPE` kini **eksplisit** dan escape char tidak lagi bergantung default mesin:

```php
->whereRaw("cooperatives.nama LIKE ? ESCAPE '!'", ['%'.$this->escapeLike($filters['q']).'%'])

// escapeLike(): '!' → '!!', '%' → '!%', '_' → '!_'
```

**Bukti (probe read-only, tanpa eksekusi query):**

```
SQL direktori: select * from `cooperatives` where cooperatives.nama LIKE ? ESCAPE '!' and `cooperatives`.`deleted_at` is null
Bindings: ["%100!%%"]
```

**Perilaku yang ditetapkan:** input kosong/berisi spasi saja → pencarian dilewati (semua hasil tampil);
`%`, `_`, dan `!` diperlakukan literal; `ESCAPE '!'` adalah SQL standar yang **didukung SQLite dan
MySQL/MariaDB** - verifikasi runtime kemudian dilakukan pada MariaDB 10.11.19 lokal (34.3),
tetapi staging/produksi tetap belum diuji.

**Tes:** `DirektoriPublikTest::test_search_treats_like_wildcards_and_the_escape_character_as_literal`
(`100%`, `%` tunggal → tepat 1 hasil, `A_B` vs "a B", `Satu!Dua` vs `SatuXDua`, pencarian normal,
input spasi).

### 25.2 Temuan rendah B — validasi skema URL website (DIPERBAIKI)

**Masalah.** Rule `url` bawaan Laravel 13.36 (`Str::isUrl($value, [])`) menerima seluruh daftar skema
IANA, termasuk skema non-web. **Bukti (probe read-only, `Str::isUrl`):**

| URL | rule `url` | rule `url:http,https` |
| --- | --- | --- |
| `https://contoh.id` | LOLOS | **LOLOS** |
| `http://contoh.id/profil` | LOLOS | **LOLOS** |
| `ftp://contoh.id/berkas` | **LOLOS** | tolak |
| `data://contoh.id/teks` | **LOLOS** | tolak |
| `file://contoh.id/berkas` | **LOLOS** | tolak |
| `javascript:alert(1)` | tolak | tolak |
| `javascript://contoh.id/%0aalert(1)` | tolak | tolak |
| `bukan-url` | tolak | tolak |

Karena `website` **dipublikasikan** (allowlist F7) dan dirender sebagai `href`, skema non-web yang
lolos validasi layak ditutup walaupun `javascript:` sudah ditolak sebelumnya.

**Perbaikan (3 titik validasi, tanpa memperluas field yang boleh tampil):**
`StoreCooperativeRequest` (`url:http,https` + pesan `website.url` diperjelas), `UpdateCooperativeRequest`,
dan handler pengajuan `KoperasiIndukHandler` (nilai pengajuan bisa menjadi data resmi → ikut dibatasi).
Escaping Blade & atribut `rel="nofollow noopener"` pada view publik **tidak diubah**.

**Tes:** `CooperativeCrudTest::test_store_rejects_website_with_non_web_scheme`,
`::test_store_accepts_http_and_https_website`, `::test_update_rejects_website_with_non_web_scheme`
(data lama tidak berubah saat validasi gagal), `PengelolaSubmissionTest::test_pengajuan_rejects_website_with_non_web_scheme`.

**Catatan batasan:** data lama yang (sebelumnya lolos) memuat skema non-web **tidak** diubah pada tahap
ini — tidak ada migrasi/DML; bila dinas menghendaki pembersihan data, itu keputusan terpisah.

### 25.3 Verifikasi Tahap 87 `[FAKTA]`

| Perintah | Hasil |
| --- | --- |
| `php artisan test tests/Feature/Publik/DirektoriPublikTest.php tests/Feature/Admin/CooperativeCrudTest.php tests/Feature/PengelolaSubmissionTest.php` | **53 tes / 253 asersi PASS** |
| `php artisan test` | **446 tes / 1.884 asersi PASS** (baseline Tahap 86: 441/1.841) |
| `vendor/bin/pint` + `pint --test` | **PASS** |
| `php -l` (7 berkas) | **No syntax errors** |
| `git diff --check` | **bersih** |

Angka di atas **bukan** bukti kompatibilitas MariaDB (SQLite `:memory:`).

### 25.4 Analisis benturan slug dengan route internal — MENUNGGU PERSETUJUAN (belum diimplementasikan)

**Keadaan sekarang (tidak diubah pada tahap ini).** Route publik `/koperasi/{slug}` didaftarkan
**setelah** grup internal `/koperasi` (pengelola) di `routes/web.php`; segmen pertama internal yang
memakai prefix itu: `dashboard`, `profil`, `izin`, `pengurus`, `kesehatan`, `tata-kelola`, `aset`,
`anggota`, `pengajuan`, `notifikasi`. Karena `generateUniqueSlug()` = `Str::slug($nama)` dan slug
**tidak pernah digenerate ulang** setelah dibuat, koperasi bernama "Profil"/"Aset"/dst. akan punya
slug yang menutup dirinya sendiri (tamu → `login`, non-pengelola → 403).

| Opsi | Komponen terdampak | Risiko kompatibilitas | Dampak URL publik | Data/migrasi/keputusan | Tes yang harus dibuat |
| --- | --- | --- | --- | --- | --- |
| **1. Larang slug baru memakai segmen internal** | `app/Models/Cooperative.php` (`generateUniqueSlug()`) + daftar tercadang (konstanta config) | Rendah. Daftar harus dijaga sinkron dengan segmen route; tidak menyentuh routing | **Tidak ada** (slug lama tak berubah) | Tidak ada data/migrasi; keputusan teknis (nama suffix, mis. `profil-koperasi`) | Unit: nama "Profil" → slug bukan `profil` & unik; feature: profil publik slug itu `200` + `/koperasi/profil` internal tetap terproteksi |
| **1b. Komplemen:** batasi route publik dengan `whereNotIn('slug', $tercadang)` | `routes/web.php` (constraint saja, urutan tetap) | Rendah; **membuat route publik menolak slug tercadang secara deterministik** | Slug bentrok lama tetap ditangani route internal karena urutan registrasi tidak diubah (tamu → `login`, bukan 404) — **dikoreksi Tahap 88 setelah verifikasi, §26.2** | Tidak ada data/migrasi | Feature: route publik **tidak cocok** dengan slug tercadang; slug normal (termasuk `anggota-sejahtera`) tetap 200 |
| **2. Benahi slug lama yang bentrok** | Data `cooperatives.slug` + perintah sekali jalan (command) | Rendah, tapi menyentuh data produksi (butuh backup + izin) | URL yang **sudah** bekerja tidak berubah; URL bentrok berubah dari "tak terjangkau" menjadi **berfungsi** (tidak ada tautan publik yang rusak) | **Ya** — DML (UPDATE) + backup + pemetaan slug lama→baru; tanpa migrasi skema | Command: hanya baris bentrok diubah, idempoten, tetap unik; feature: profil baru dapat diakses |
| **3. Namespace URL profil tersendiri** (mis. `/direktori/{slug}`) | `routes/web.php`, view (`route('publik.koperasi', …)`), tes, **PRD §9 + `TEST-08-01`** | Menghilangkan kelas benturan sepenuhnya, tetapi **melanggar PRD §9 saat ini** → butuh amendemen PRD | **Ya** — semua URL profil berubah → butuh redirect/alias bila ingin menjaga tautan lama | Tidak ada data/migrasi; **keputusan bisnis/dokumentasi** (dinas) | Feature: path baru melayani profil, `/koperasi/{slug}` tidak lagi; tes alias/redirect bila dipertahankan |

**Rekomendasi (risiko terendah):** jalankan **Opsi 1** sebagai perbaikan utama (tanpa perubahan data,
tanpa perubahan URL) dan sertakan **Opsi 1b** agar perilaku slug bentrok menjadi 404 yang deterministik.
Terapkan **Opsi 2** hanya bila pemeriksaan data menemukan slug bentrok nyata (kemungkinan sangat kecil)
— butuh izin DML + backup terverifikasi. **Opsi 3** hanya jika dinas mengubah desain URL publik (butuh
amendemen PRD §9 + `TEST-08-01`).

> **Pembaruan Tahap 88.** Opsi 1 + 1b **sudah diimplementasikan** (lihat §26). Catatan penting atas
> rekomendasi di atas: klaim "slug bentrok lama menjadi **404** (bukan `login`)" **tidak tercapai
> dengan constraint saja** — route internal tetap menang lebih dulu (§26.2, fakta terverifikasi).
> Mewujudkan 404 untuk slug lama memerlukan **Opsi 2** (perbaikan data, butuh izin + backup) atau
> **Opsi 3** (namespace URL). Constraint 1b tetap bernilai sebagai kontrak route publik + pertahanan
> berlapis (mis. bila segmen internal dihapus/diubah di masa depan).

Selama belum ada persetujuan, **tidak ada** perubahan pada urutan route, `generateUniqueSlug()`,
migration, maupun data slug — sesuai batas Tahap 87.

### 25.5 Temuan lanjutan (di luar F7 — tidak diubah)

Pencarian internal lain memakai input pengguna **tanpa** escaping wildcard:
`Admin\CooperativeController` (nama/nomor identitas), `Admin\SubmissionController` (judul/nama),
`Admin\ActivityLogController` (deskripsi). Akibatnya `%`/`_` bertindak sebagai wildcard pada
pencarian admin — **bukan kebocoran** (semua baris memang terlihat oleh admin dinas), hanya relevansi
pencarian. Tidak diubah pada tahap ini karena di luar lingkup F7 dan berisiko memengaruhi tes F2/F5/F6.
Dicatat sebagai kandidat tahap terpisah.

### 25.6 Keputusan yang masih dibutuhkan pemilik proyek (Tahap 87)

1. **Temuan sedang §24.2/§25.4** — pilih opsi perbaikan benturan slug (rekomendasi: Opsi 1 + 1b).
   → **DIJAWAB:** Opsi 1 + 1b diimplementasikan pada Tahap 88 (§26). Tersisa keputusan bila pemilik
   menghendaki perbaikan slug **lama** (Opsi 2 — butuh izin DML + backup) atau namespace baru (Opsi 3).
2. **Q-008**, **Q-012**, **Q-014** (+ izin MariaDB & `NO_BACKSLASH_ESCAPES`), **D-6** — tidak berubah.
3. **§25.5** — apakah pencarian internal juga dirapikan (tahap terpisah).
4. Peninjauan Tahap 85/86 & keputusan penutupan **F6**.

## 26. Tahap 88 — Perbaikan benturan slug publik dengan route internal (Opsi 1 + 1b) `[FAKTA]`

Keputusan teknis pemilik: **Opsi 1 + 1b** dari §25.4 disetujui untuk **diimplementasikan**. Persetujuan
ini **tidak** mencakup perubahan data produksi, migration, deployment, commit, atau push. F6 tetap
**MENUNGGU PENINJAUAN**; MariaDB belum terverifikasi; F7 **masih berjalan**; F8 kemudian selesai secara lokal (33).

### 26.1 Implementasi `[FAKTA]`

**Satu sumber daftar tercadang** — `app/Models/Cooperative.php`:

```php
public const RESERVED_SLUGS = ['dashboard','profil','izin','pengurus','kesehatan',
    'tata-kelola','aset','anggota','pengajuan','notifikasi'];

Cooperative::isReservedSlug($slug): bool              // normalisasi Str::slug() → kapitalisasi/tanda hubung
Cooperative::publicSlugPattern(): string              // '(?!(?:…)$)[^/]+'
Cooperative::generateUniqueSlug($nama, $ignoreId)     // slug baru tidak memakai segmen tercadang
```

Daftar diturunkan dari **inventaris route aktual** (probe read-only atas `router->getRoutes()`, bukan
tebakan) — 10 segmen pertama di bawah prefix `koperasi`, dan **tidak** ada segmen lain. Segmen tercadang
**hanya** ditolak bila sama persis: `anggota-sejahtera`, `profil-2`, `profiled`, `dashboard-koperasi`
tetap sah.

**Pembentukan slug (Opsi 1).** Segmen tercadang diperlakukan seperti slug yang sudah terpakai:
`Profil` → `profil-2`, `Anggota` → `anggota-2`, dan bila `profil-2` sudah dipakai → `profil-3`
(suffix numerik yang sama dengan konvensi aplikasi). Perilaku slug **normal tidak berubah**
(`Koperasi Sejahtera Bersama` → `koperasi-sejahtera-bersama`; bila terpakai → `…-2`), nama
kosong/slug kosong → fallback `koperasi` (tetap `koperasi-2` bila terpakai), pemeriksaan keunikan
`withTrashed()` dipertahankan, dan **slug lama tidak pernah digenerate ulang** (slug tetap immutable).

**Constraint route (Opsi 1b)** — `routes/web.php`:

```php
Route::get('/koperasi/{slug}', [KoperasiPublikController::class, 'show'])
    ->where('slug', Cooperative::publicSlugPattern())
    ->name('publik.koperasi');
```

Path publik tetap `/koperasi/{slug}` (PRD §9); **tidak ada route duplikat** (verified: tepat 1 route
ber-uri `koperasi/{slug}`); grup internal tidak dipindah dan middleware-nya tidak diubah.

**Catatan teknis (bukan preferensi):** `Route::where()` di Laravel memperlakukan **array** sebagai
pasangan `name => pola`, bukan allow-list nilai → constraint ditulis sebagai regex *negative lookahead*
yang dibungkus **grup non-capturing** `(?:…)`. Implementasi pertama **cacat** (tanpa `(?:…)` tanda `$`
hanya menempel pada alternatif terakhir sehingga `anggota-sejahtera`/`profil-2` ikut tertolak);
cacat ini **tertangkap oleh probe read-only sebelum tes ditulis**, lalu diperbaiki dan dikunci tes.

### 26.2 Batas yang ditemukan: slug lama tetap ditangani route internal, **bukan 404** `[FAKTA]`

Klaim opsi 1b pada §25.4 ("slug bentrok lama → 404") **tidak tercapai dengan constraint saja**.
Bukti (probe read-only + tes): untuk `/koperasi/profil`, route yang benar-benar cocok adalah
`pengelola.profil` (**internal**), dan `publik.koperasi->matches()` = `false`. Sebabnya: grup internal
didaftarkan **lebih dulu** (keputusan Tahap 84) dan **harus** mempertahankan urutan + middleware-nya
(`auth`, `active`, `role:pengelola_koperasi`) sesuai batas tahap ini. Karena itu tamu tetap menerima
**redirect `login`** dan pengguna non-pengelola **403** — deterministik, tanpa kebocoran data, tetapi
**bukan** 404.

Mewujudkan 404 untuk slug lama menuntut salah satu hal yang **di luar batas Tahap 88**:
shadowing segmen internal oleh route publik (dilarang — merusak halaman pengelola), **Opsi 2**
(perbaikan data slug lama; butuh izin DML + backup terverifikasi), atau **Opsi 3** (namespace URL;
butuh amendemen PRD §9). **Tidak ada** data slug yang diubah pada tahap ini; koperasi lama yang
slug-nya tercadang tetap tercatat demikian sampai ada keputusan terpisah.

Nilai nyata Opsi 1b yang tetap berlaku: route publik kini **berkontrak** tidak pernah melayani segmen
internal (pertahanan berlapis bila urutan route berubah atau segmen internal dihapus di masa depan),
dan aturan ini dikunci tes.

### 26.3 Verifikasi Tahap 88 `[FAKTA]`

| Perintah | Hasil |
| --- | --- |
| `php artisan test tests/Unit/CooperativeSlugTest.php` | **10 tes / 51 asersi PASS** |
| `php artisan test tests/Feature/Publik/DirektoriPublikTest.php` | **21 tes / 176 asersi PASS** |
| `php artisan test tests/Feature/Publik/DirektoriPublikTest.php tests/Feature/Admin/CooperativePublicationTest.php tests/Feature/Admin/CooperativeCrudTest.php tests/Feature/PengelolaSubmissionTest.php` | **76 tes / 460 asersi PASS** |
| `php artisan test` | **461 tes / 2.004 asersi PASS** (baseline Tahap 87: 446/1.884) |
| `vendor/bin/pint` + `pint --test` | **PASS** |
| `php -l` (4 berkas) | **No syntax errors** |
| `git diff --check` | **bersih** |
| Probe route (`router->getRoutes()`) | 99 route total; **1** route ber-uri `koperasi/{slug}`; `wheres.slug` = regex penolak segmen tercadang |
| Probe pencocokan route | slug tercadang → **tidak** cocok dengan route publik (ditangani route internal); `anggota-sejahtera`/`profil-2`/`profiled` → cocok route publik |
| Probe pembentukan URL | `route('publik.koperasi', ['slug' => 'profil'])` **tidak** melempar galat (Laravel tidak menegakkan `where` saat generasi URL) → direktori tidak 500 untuk data lama |

Angka di atas adalah **SQLite `:memory:`** — **bukan** bukti kompatibilitas MariaDB. Tidak ada
migration/seeder/DDL/DML maupun operasi Git destruktif yang dijalankan pada tahap ini.

### 26.4 Berkas yang benar-benar berubah (Tahap 88) `[FAKTA]`

| Berkas | Perubahan |
| --- | --- |
| `app/Models/Cooperative.php` | `RESERVED_SLUGS`, `isReservedSlug()`, `publicSlugPattern()`, `generateUniqueSlug()` |
| `routes/web.php` | `use App\Models\Cooperative;` + constraint `where('slug', …)` + komentar urutan/constraint |
| `tests/Unit/CooperativeSlugTest.php` | **baru** — 10 tes (slug normal, tercadang, kapitalisasi/pemisah, keunikan, soft delete, nama kosong, pola constraint) |
| `tests/Feature/Publik/DirektoriPublikTest.php` | +5 tes (constraint route publik, penjaga drift daftar tercadang, slug lama, otorisasi route internal, profil slug alternatif) |
| `docs/DECISIONS.md` | §24.2, §25.4 (koreksi), §26 (ini) |

Tidak ada perubahan pada `.env`, `phpunit.xml`, konfigurasi DB, migrasi (termasuk migrasi F6
`2026_10_19_000001_*` yang tetap `Pending` dan tidak diubah), maupun kode F6.

### 26.5 Yang **tidak** diklaim / masih terbuka `[TERBUKA]`

- **Q-003** (jenis koperasi), **Q-008** (field publik), **Q-012** (sumber referensi wilayah),
  **Q-014** (versi/edukasi MariaDB) dan **D-6** (semantik penarikan publikasi) **tetap terbuka**.
- **FR-191** pada Tahap 86 masih **sebagian**; tabel `settings` belum dibuat saat itu; **FR-183**
  `/statistik` tetap FASE-2. **Diperbarui:** UI + tabel `settings` ditutup di §32.
- Perbaikan slug lama (**Opsi 2**) dan namespace URL (**Opsi 3**) belum diputuskan/dijalankan.
- Pencarian internal tanpa escaping wildcard (§25.5) belum dirapikan.
- **F6: MENUNGGU PENINJAUAN. F7: masih berjalan. F8: belum dimulai. MariaDB: belum terverifikasi.**
- Seluruh perubahan (F6 + F7 Tahap 56–88) **belum** di-commit/push — menunggu otorisasi pemilik.

---

## 27. Tahap 95-96 — Audit keputusan slug & penerapan Opsi 1 sebagai status quo `[FAKTA + KEPUTUSAN SEMENTARA]`

### 27.1 Hasil audit (Tahap 95) `[FAKTA]`

Audit read-only mengonfirmasi implementasi Tahap 88 dan menganalisis tiga opsi penyelesaian slug lama:

**Implementasi saat ini (Opsi 1 + 1b sudah diterapkan):**
- Slug BARU: dibatasi via `Cooperative::generateUniqueSlug()` → nama tercadang → suffix `-2` konvensi (e.g., `profil` → `profil-2`).
- Slug BARU: constraint rute publik `.where('slug', Cooperative::publicSlugPattern())` menolak segmen tercadang (e.g., `profil`, `anggota`).
- Slug BARU: tidak dapat menggunakan segmen internal (`['dashboard', 'profil', 'izin', 'pengurus', 'kesehatan', 'tata-kelola', 'aset', 'anggota', 'pengajuan', 'notifikasi']`).
- Slug LAMA yang bertabrakan: tetap ditangani rute internal (`pengelola.profil` etc.) karena rute internal didaftarkan **pertama** di `routes/web.php`.
- Perilaku slug LAMA: tamu diarahkan ke login (302), bukan 404; profil publiknya tidak dapat diakses.
- **Tes:** 10 unit + 28 feature = 38 tes, 299 asersi, **PASS**.

**Matriks perbandingan tiga opsi:**

| **Aspek** | **Opsi 1 (Saat ini)** | **Opsi 2: Ubah data slug lama** | **Opsi 3: Namespace URL publik** |
| --- | --- | --- | --- |
| Status implementasi | ✅ Done (Tahap 88) | ❌ Pending | ❌ Pending |
| Perubahan kode | ✅ Minimal (done) | ❌ Besar (DML, mapping) | ❌ Besar (route, view, link) |
| Perubahan data | ✅ Tidak ada | ❌ Ada (UPDATE slug) | ✅ Tidak ada |
| Perubahan basis data skema | ✅ Tidak ada | ❌ Migration baru? | ✅ Tidak ada |
| Perubahan PRD/SLA | ✅ Tidak ada | ✅ Tidak ada | ❌ Ya (PRD §9 amandemen) |
| Perubahan URL publik | ✅ Tidak ada | ⚠️ Redirect lama (301) atau 404 | ❌ Semua URL berubah |
| Risiko SEO / tautan lama | ⚠️ Medium: `/koperasi/profil` → login, bukan profil publik | ❌ High: `/koperasi/profil` → 404 atau redirect | ❌ High: semua URL berbeda |
| Blocker terbuka | ✅ Tidak ada | ❌ Q-003; backup protocol; DML approval | ❌ PRD §9 amandemen; instansi approval |
| Kapan dapat dilaksanakan | ✅ Sudah done | ❌ Menunggu: Q-003, backup, approval | ❌ Menunggu: PRD amandemen |
| Keamanan routing | ✅ Aman; rute internal terlindungi middleware | ✅ Aman (setelah perubahan) | ✅ Aman (namespace terpisah) |
| Usaha implementasi | ✅ Done | 📊 Medium | 📊 Medium |

**Rekomendasi teknis:** Opsi 1 (status quo) untuk saat ini — aman, teruji, tanpa blocker baru. Opsi 2/3 memerlukan persetujuan & resource terpisah.

### 27.2 Keputusan proyek (Tahap 96) `[KEPUTUSAN SEMENTARA]`

**Keputusan:** Mempertahankan Opsi 1 (status quo) untuk saat ini.

**Justifikasi:**
- Slug BARU sepenuhnya terlindungi dari benturan; tidak ada regresi fungsi publik.
- Implementasi sudah stabil, teruji (38 tes, 299 asersi PASS), dan tanpa blocker teknis baru.
- Opsi 2 (ubah data) memerlukan Q-003 (kategori koperasi) + backup terverifikasi + approval DML — sumber daya tidak tersedia saat ini.
- Opsi 3 (namespace URL) memerlukan amandemen PRD §9 + persetujuan instansi — di luar wewenang proyek.

**Risiko UX yang diterima sementara:**
- Pengguna tamu yang kebetulan tahu ada koperasi bernama "Profil" atau "Aset" (slug bentrok lama) akan diarahkan ke login, bukan mendapat profil publik atau 404 yang jelas.
- Kemungkinan koperasi real dengan slug bentrok di basis data **dev saat ini: 0** (hanya tes); di **prod: belum inventaris** — keputusan ini dapat disesuaikan jika data riil ditemukan.
- Perilaku rute internal tetap aman dan tidak terpengaruh; middleware & policy tetap berlaku.

**Keputusan sementara ini bersifat tingkat proyek (rekomendasi teknis) dan bukan klaim persetujuan resmi Dinas. Opsi 2/3 tetap tersedia jika keputusan berubah atau sumber daya menjadi tersedia.**

### 27.3 Status Opsi 2 dan Opsi 3 `[TERBUKA]`

**Opsi 2 — Ubah slug lama yang bentrok:**
- Syarat: Q-003 dijawab (kategori koperasi); backup DB dev/prod terverifikasi (SHA256 + restore procedure); otorisasi DML tertulis (pemilik proyek + Dinas jika data prod).
- Langkah: (1) inventaris slug bentrok di DB; (2) tentukan mapping (e.g., `profil` → `profil-legacy` atau `profil-2`); (3) migrasi reversible; (4) DML + tes regresi; (5) update redirect jika perlu SEO.
- Timeline: Menunggu Q-003 + approval. Tidak dijalankan pada Tahap 96.

**Opsi 3 — Namespace URL publik:**
- Syarat: PRD §9 amandemen (perubahan kontrak URL `/koperasi` → `/direktori` atau prefix lain); persetujuan instansi (Dinas + stakeholder eksternal); komitmen URL baru jangka panjang.
- Langkah: (1) tentukan namespace baru; (2) refactor route publik + redirect lama (301) sementara; (3) bulk update dokumentasi & tautan; (4) tes coverage redirect + rute baru.
- Timeline: Menunggu PRD amandemen + approval instansi. Tidak dijalankan pada Tahap 96.

**Kesimpulan:** Opsi 1 (status quo) adalah keputusan proyek sementara. Opsi 2/3 tetap terbuka untuk implementasi di masa depan jika blocker terselesaikan dan persetujuan diperoleh.

---

## 28. Tahap 105 — Implementasi keputusan Q-008 & D-6, status Q-012/Q-014 `[FAKTA + KEPUTUSAN]`

Kebijakan **Q-008, Q-012, Q-014, dan D-6 diterima pemilik proyek**. Yang dapat diimplementasikan tanpa
dependensi eksternal (Q-008 untuk field yang datanya tersedia, dan D-6) **selesai dan teruji**. Q-012 dan
Q-014 tetap terblokir oleh data/infrastruktur eksternal dan **tidak dikarang**.

### 28.1 Q-008 — allowlist informasi publik `[SELESAI untuk data yang tersedia]`

Perubahan:
- `config/koperasi.php` → `koperasi.public.fields` = `nama`, `nomor_identitas`, `tanggal_berdiri`,
  `jenis_koperasi`, `website`.
- Saat Tahap 105, `KoperasiPublikController::ALLOWED_PUBLIC_FIELDS` disamakan sebagai pertahanan
  berlapis. Sejak §32, batas tersebut dipusatkan di `PublicFieldSettings::Q008_ALLOWED_FIELDS`.
- **Alamat publik = hierarki `regions`** (provinsi → kabupaten/kota → kecamatan → desa/kelurahan) lewat
  `regionPath()`. **Teks bebas `cooperatives.alamat` tidak dipublikasikan** karena dapat memuat nomor
  rumah/RT/RW di luar batas yang disetujui. `regions` masih kosong (Q-012) → alamat tampil
  "Belum ditetapkan".
- `status_verifikasi` **tidak** ditampilkan sebagai "status operasional". Sejak Tahap 106 kolom
  `status_operasional` tersedia dan ditampilkan terpisah (lihat §29).
- Email/telepon tetap termasking (`PiiMask`, NFR-SEC-015). Data pengurus, anggota, dan omzet tidak pernah
  dikirim ke view: model Eloquent tidak dikirim utuh, hanya array allowlist.
- Tabel `settings` **tidak** dibuat pada Tahap 105 (arahan saat itu: pertahankan arsitektur config).
  **Diperbarui:** tabel + UI dibuat di §32 tanpa mengubah batas Q-008.

Tes regresi (SQLite `:memory:`):
- `DirektoriPublikTest::test_published_profile_shows_allowlisted_fields_and_masks_sensitive_data`
- `DirektoriPublikTest::test_directory_never_exposes_sensitive_personal_data`
- `DirektoriPublikTest::test_unknown_public_field_names_are_ignored_without_exposing_data`

### 28.2 D-6 — jejak penarikan publikasi `[SELESAI]`

- Migrasi baru `database/migrations/2026_10_24_000001_add_unpublished_tracking_to_cooperatives_table.php`:
  `unpublished_at` (TIMESTAMP NULL) dan `unpublished_by` (BIGINT UNSIGNED NULL, FK `users.id`
  `ON DELETE SET NULL`; FK dilewati pada SQLite mengikuti konvensi migrasi `parent_submission_id`).
  **Additive, nullable, tanpa backfill maupun penghapusan data.**
- `CooperativeController::publish()` menstempel `published_at`/`published_by` (sudah ada) dan **tidak
  mengosongkan** `unpublished_at`/`unpublished_by`.
- `CooperativeController::unpublish()` menstempel `unpublished_at`/`unpublished_by` dan **mempertahankan**
  `published_at`/`published_by`.
- Penarikan berulang saat koperasi sudah tidak terbit → ditolak tanpa mengubah baris (jejak tidak ditimpa).
- Riwayat seluruh siklus tetap append-only di `activity_logs` (`publish_cooperative`/
  `unpublish_cooperative`), sehingga tabel riwayat terpisah belum diperlukan.
- UI admin menampilkan "Penarikan terakhir" beserta aktor saat koperasi tidak terbit.
- Direktori/profil publik tetap menyembunyikan koperasi yang ditarik (tes lama + baru).

Tes: `CooperativePublicationTest` (+3): stempel penarikan & jejak publikasi utuh; penarikan berulang tidak
menimpa; publikasi ulang mempertahankan jejak penarikan + 2 log publish / 1 log unpublish.

### 28.3 Q-012 — data wilayah `[TERBLOKIR DATA]`

Kebijakan diterima (Permendagri/BPS, hierarki 4 level, koperasi wajib berwilayah valid), tetapi **dataset
resmi tidak ada di repositori** (tidak ada CSV/JSON/SQL wilayah; tabel `regions` kosong). Karena itu:
- **tidak** ada seeder/importer berisi data karangan;
- **tidak** ada perubahan `region_id` menjadi `NOT NULL`;
- form admin sudah menampilkan peringatan saat `regions` kosong (perilaku lama, aman).

Yang dibutuhkan sebelum Q-012 dapat ditutup:
1. berkas dataset resmi (Permendagri/BPS) beserta versi/tanggal terbit;
2. format: `code`, `name`, `type` (`province|regency|city|district|village`), `parent_code`/`parent_id`;
3. importer idempotent (upsert berdasarkan `code`) + validasi hierarki/duplikasi;
4. audit koperasi dengan `region_id` NULL/tidak valid + strategi backfill yang dapat dibuktikan;
5. setelah backfill tuntas: migrasi baru `region_id` NN + validasi wajib.

Jangan menebak wilayah berdasarkan nama koperasi.

> **Update Tahap 107:** dataset resmi sudah tersedia, importer + audit + form berjenjang sudah dibuat.
> Lihat §30. Statusnya menjadi **sebagian selesai**; `region_id` tetap nullable sampai audit/backfill
> tuntas.

### 28.4 Q-014 — infrastruktur & MariaDB `[TERBLOKIR INFRA]`

Target diterima: Ubuntu Server LTS/AlmaLinux, MariaDB 10.11 LTS, subdomain `.go.id`, HTTPS
(auto-renew/instansi), backup DB harian + filesystem mingguan ke remote/server terpisah. **Tidak** ada akses
staging, kredensial, atau konfigurasi produksi pada tahap ini → `.env`/config DB **tidak** diubah dan tidak
ada klaim kompatibilitas MariaDB. Checklist konkret untuk tim IT: `docs/DEPLOYMENT_CHECKLIST.md`.
Migrasi F6 yang masih Pending (`2026_10_19_000001_*`) **belum** dijalankan pada MariaDB.

### 28.5 Verifikasi Tahap 105 `[FAKTA]`

| Perintah | Hasil |
| --- | --- |
| `php artisan test tests/Feature/Publik/` | **45 tes / 323 asersi PASS** |
| `php artisan test tests/Feature/Admin/CooperativePublicationTest.php` | **23 tes / 185 asersi PASS** |
| `php artisan test --no-coverage` | **507 tes / 2.248 asersi PASS** |
| `vendor/bin/pint --test` | **PASS** |
| `git diff --check` | **bersih** |

Seluruh bukti tes berjalan pada **SQLite `:memory:`** — **bukan** bukti kompatibilitas MariaDB.

### 28.6 Blocker rilis yang tersisa

1. **Q-014/MariaDB** — staging + izin; jalankan seluruh migrasi (termasuk yang Pending) dan suite pada
   MariaDB 10.11; verifikasi `sql_mode`.
2. **Q-012** — dataset wilayah resmi + importer + backfill + migrasi NN.
3. **F6** — masih menunggu peninjauan/persetujuan pemilik (tidak ditutup pada tahap ini).
4. **Status operasional koperasi** — **diselesaikan pada Tahap 106** (kolom `status_operasional`,
   lihat §29). Data lama yang belum dikonfirmasi tetap `NULL` dan perlu dilengkapi pihak berwenang.

---

## 29. Tahap 106 — Implementasi status operasional koperasi `[FAKTA + KEPUTUSAN]`

Keputusan pemilik proyek: status operasional koperasi bernilai **Aktif**, **Tidak Aktif**, atau **Bubar**.

### 29.1 Skema & makna

- Migrasi additive `database/migrations/2026_10_24_000002_add_status_operasional_to_cooperatives_table.php`:
  kolom `status_operasional` `ENUM('aktif','tidak_aktif','bubar')` **NULL**, **tanpa default**.
- `NULL` = **belum ditetapkan/belum dikonfirmasi**. Data lama **tidak** diberi nilai karangan dan **tidak**
  dianggap aktif. Pengisian dilakukan pihak berwenang melalui form edit.
- Tiga konsep terpisah dan tidak saling menggantikan:

  | Kolom | Arti |
  | --- | --- |
  | `status_verifikasi` | posisi data dalam alur verifikasi resmi |
  | `is_published` (+ jejak D-6) | tampil/tidak di direktori publik |
  | `status_operasional` | keadaan operasional koperasi (Aktif/Tidak Aktif/Bubar) |

- `enum` database + validasi server-side (`Rule::in(Cooperative::STATUS_OPERASIONAL)`) menolak nilai lain.
- Label tampilan bersumber tunggal dari `Cooperative::STATUS_OPERASIONAL_LABELS`.

### 29.2 Perilaku form & validasi

- **Tambah koperasi baru:** `status_operasional` **wajib** dipilih (placeholder kosong, `required`).
- **Edit data lama (NULL):** boleh tetap kosong saat mengubah field lain - tidak dipaksa memilih nilai palsu.
  Placeholder "Belum ditetapkan" dan help text menjelaskan kewajiban melengkapi.
- **Edit data yang sudah berstatus:** `Rule::requiredIf(...)` mencegah pengosongan diam-diam; nilai harus
  tetap salah satu dari tiga status resmi.
- Perubahan status dicatat pada audit `activity_logs` (`status_operasional` termasuk `$auditedFields`).

### 29.3 Tampilan publik (tetap dalam allowlist Q-008)

- `status_operasional` ditambahkan ke `config('koperasi.public.fields')` **dan** batas allowlist
  Q-008 (kini dipusatkan di `PublicFieldSettings::Q008_ALLOWED_FIELDS`).
- Hanya **label resmi** yang dirender; `NULL`/nilai tak dikenal -> baris tidak ditampilkan
  (`statusOperasionalLabel()` mengembalikan `null`).
- `status_verifikasi` **tidak** dipakai sebagai pengganti status operasional.
- Direktori & profil publik menampilkan "Status Operasional" hanya bila terisi.

### 29.4 Tes regresi (SQLite `:memory:`)

`tests/Feature/Admin/CooperativeStatusOperasionalTest.php` (11 tes): simpan ketiga nilai; wajib saat tambah;
tolak nilai asing (store & update); data lama NULL tetap NULL saat edit field lain dan dapat dilengkapi;
status yang sudah ada tidak bisa dikosongkan; detail admin & form; tampilan publik hanya bila valid;
direktori publik; database + model menolak/menyembunyikan nilai tak dikenal.
`DirektoriPublikTest` & `CooperativeCrudTest` disesuaikan untuk field wajib baru tanpa melemahkan asersi.

### 29.5 Verifikasi Tahap 106 `[FAKTA]`

| Perintah | Hasil |
| --- | --- |
| `php artisan test --no-coverage tests/Feature/Admin/CooperativeStatusOperasionalTest.php tests/Feature/Publik/DirektoriPublikTest.php tests/Feature/Admin/CooperativeCrudTest.php` | **77 tes / 428 asersi PASS** |
| `php artisan test --no-coverage` | **518 tes / 2.299 asersi PASS** |
| `vendor/bin/pint --test` | **PASS** |
| `git diff --check` | **bersih** |

Migrasi **tidak** dijalankan pada database persisten (tetap Pending bersama migrasi F6 & D-6).
Seluruh bukti tes = SQLite `:memory:` -> **bukan** bukti kompatibilitas MariaDB.

### 29.6 Dampak & batas

- Q-008 kini lengkap untuk data yang tersedia; **Q-012/Q-014 tetap terbuka**.
- Data lama berstatus NULL harus dilengkapi dinas; belum ada backfill otomatis dan **tidak boleh** dikarang.
- Belum ada filter/laporan khusus status operasional (tidak diminta pada tahap ini).

---

## 30. Tahap 107 — Integrasi data wilayah resmi (Q-012) `[FAKTA + KEPUTUSAN]`

### 30.1 Sumber kode: Kemendagri, bukan BPS `[KEPUTUSAN]`

- **Sumber utama kode & nama:** Kepmendagri Nomor 300.2.2-2138 Tahun 2025 (Kementerian Dalam
  Negeri, 25 April 2025). Kode disimpan **sebagai string** agar nol di depan tidak hilang.
- **BPS hanya untuk validasi:** Master File Desa Provinsi Jawa Barat 2025 Semester 2 (rilis
  30 Juni 2026) dipakai membandingkan jumlah dan membuktikan nama yang terpotong. Kolom kode BPS
  **tidak** dipakai karena memuat kesalahan cetak; kode BPS dan Kemendagri **tidak dicampur**.
- Provenance lengkap (URL, ukuran, SHA-256, lisensi, halaman) ada di
  `docs/REGION_DATA_SOURCES.md`. Ringkas:
  - Kepmendagri: 4.428 halaman, halaman Jawa Barat 1.369–1.604, SHA-256
    `4a40e41c…6b3fd`, **domain publik** (UU 28/2014 Pasal 42).
  - BPS: 776 halaman, SHA-256 `d1a2ca37…337f`, digunakan hanya untuk validasi.
- Tidak ada nama/kode yang dikarang. Nama OCR yang tidak terbukti **tidak** dikoreksi.

### 30.2 Dataset & jumlah `[FAKTA]`

`database/data/regions/jawa_barat_2025.csv` (6.612 baris) + laporan
`database/data/regions/jawa_barat_2025.report.json`:

| Level | Jumlah |
| --- | --- |
| Provinsi | 1 |
| Kabupaten | 18 |
| Kota | 9 |
| Kecamatan | 627 |
| Desa/kelurahan | 5.957 |
| **Total** | **6.612** |

Jumlah desa/kelurahan (5.957) dan kecamatan (627) cocok dengan BPS. CSV memakai header tetap
`level,code,name,parent_code,type`; `type` `kabupaten`/`kota` dipetakan importer ke `regency`/`city`.

### 30.3 Ekstraksi & koreksi `[FAKTA]`

- Skrip reproducible: `scripts/extract_regions.py` (Python + `pypdf`, **bukan** dependensi runtime).
- Validasi: hierarki (`parent_code` prefix), panjang kode, duplikasi kode, jumlah per level.
- Empat koreksi nama terpotong, hanya yang terbukti dari BPS:
  `32.09.29.2003` Prajawinangun Wetan, `32.12.28.2005` Kedokanbunder Wetan,
  `32.15.06.2001` Rengasdengklok Selatan, `32.15.06.2002` Rengasdengklok Utara.
- `32.03.31.2004` tetap `Mekarwangi/Sukatani` seperti tercetak di PDF resmi; BPS menampilkan
  `SUKATANI`. Perbedaan dicatat, tidak ditebak.
- PDF adalah OCR; artefak lain yang tidak terbukti tidak diubah.

### 30.4 Importer idempoten `[FAKTA]`

`php artisan regions:import`:

- Memvalidasi header/format/level/tipe/kode/hierarki/duplikasi **sebelum** menulis.
- Satu transaksi; gagal validasi = tidak ada perubahan.
- `updateOrCreate` berdasarkan `code` → menjalankan ulang tidak menggandakan.
- `--dry-run` untuk validasi tanpa menulis; `--expected=province:1,regency:27,district:627,village:5957`
  memeriksa jumlah per level.
- Tidak menghapus wilayah yang tidak ada di CSV.

Verifikasi CSV nyata pada SQLite sementara (bukan DB persisten): impor pertama 6.612 baris; impor
kedua **0 dibuat / 6.612 diperbarui**; jumlah tetap 6.612.

### 30.5 Audit `region_id` & kebijakan backfill `[FAKTA + KEPUTUSAN]`

`php artisan regions:audit-cooperatives [--json=...]` **read-only**, tanpa data pribadi. Alasan
temuan: `region_id_null`, `region_tidak_ditemukan`, `region_bukan_desa_kelurahan`.

Hasil pada database persisten dev Tahap 107 (database `koperasi_jabar`, **MariaDB 10.4.32 XAMPP**,
read-only — bukan SQLite dan bukan target MariaDB 10.11):

| Metrik | Jumlah |
| --- | --- |
| Total koperasi | 1 |
| Dengan wilayah | 0 |
| Tanpa wilayah (`NULL`) | 1 |
| Wilayah desa/kelurahan | 0 |
| Wilayah non-desa | 0 |

`regions` persisten masih 0 baris. **Tidak ada backfill** dan `region_id` **tidak** diubah menjadi
`NOT NULL`: belum ada pemetaan deterministik, dan satu-satunya temuan adalah `region_id_null`
(koperasi ID 1) yang harus diisi dari data resmi, bukan tebakan. Kolom
`cooperatives.status_operasional` belum ada di DB persisten karena migrasi `2026_10_24_000002_*`
masih `Pending`; tidak ada klaim tentang status operasional data lama sebelum migrasi dijalankan.

Kebijakan: backfill hanya boleh dilakukan bila ada bukti deterministik (kode/nama resmi yang
cocok). Sebelum itu, `region_id` tetap nullable. Perubahan ke `NN` memerlukan migrasi baru dan
persetujuan pemilik.

### 30.6 Semantik `region_id` & tampilan `[KEPUTUSAN]`

- Data baru: `region_id` menunjuk **desa/kelurahan**.
- Data lama: boleh tetap menunjuk kecamatan/kabupaten/kota/provinsi; ditampilkan sebagai
  “wilayah saat ini” dan **tidak** dipaksa berubah saat edit. Nilai non-desa tidak dapat dipilih
  baru.
- Form admin: empat pilihan berjenjang provinsi → kabupaten/kota → kecamatan → desa/kelurahan.
  Tanpa JS semua opsi tetap terlihat; **validasi server-side** (`ValidRegionSelection`) tetap
  menolak kombinasi yang tidak konsisten. Ini bukan pengamanan UI.
- Filter admin, dashboard, laporan, dan direktori publik memakai **descendant**: memilih
  kabupaten/kota mencakup seluruh desa di bawahnya.
- Dashboard & laporan mengelompokkan ke **kabupaten/kota** (`RegencyGrouping`) agar tidak pecah
  per desa; provinsi/tanpa rantai masuk “Belum ditetapkan”.
- Alamat publik dibentuk dari hierarki wilayah (controller publik `regionPath()`); halaman admin
  memakai `Region::fullPath()`. Teks bebas `alamat` tetap tidak dipublikasikan (Q-008).

### 30.7 Tinjauan migrasi pending `[FAKTA]`

Tidak ada migrasi baru untuk wilayah (tabel `regions` sudah ada). Tiga migrasi pending ditinjau
**statis** tanpa menjalankan migrasi persisten:

| Migrasi | SQLite | MariaDB 10.11 |
| --- | --- | --- |
| `2026_10_19_000001_add_parent_submission_id_to_submissions_table` | kolom + indeks; self-FK sengaja dilewati | kolom + indeks + self-FK; terverifikasi pada MariaDB 10.11.19 lokal |
| `2026_10_24_000001_add_unpublished_tracking_to_cooperatives_table` | kolom; FK dilewati | kolom + FK; terverifikasi pada MariaDB 10.11.19 lokal |
| `2026_10_24_000002_add_status_operasional_to_cooperatives_table` | enum → varchar + CHECK, nullable | enum native, nullable; terverifikasi pada MariaDB 10.11.19 lokal |

Kompatibilitas MariaDB **tidak** boleh disimpulkan dari SQLite. Bukti baca-saja tersedia dari
MariaDB **10.4.32** dev (audit `region_id`), dan DDL di atas sudah dijalankan pada MariaDB
**10.11.19** portabel lokal lewat suite penuh. Staging/produksi tetap belum diverifikasi; Q-014
tetap terbuka.

### 30.8 Tes `[FAKTA]`

- `ImportRegionsCommandTest`: 8 tes / 28 asersi — parsing, leading zero, hierarki, idempotensi,
  rollback, `--expected`.
- `AuditCooperativeRegionsTest`: 1 tes / 12 asersi — read-only, alasan temuan.
- `CooperativeCrudTest`: 37 tes / 133 asersi — cascade, validasi induk, legacy region, jalur penuh.
- `AdminDashboardTest`: 19 tes / 95 asersi — grouping desa → kabupaten/kota dan opsi filter hanya
  kabupaten/kota.
- Gabungan F6 wilayah/laporan (`AdminDashboardTest|AdminReportTest|CooperativeRegionFilterTest`):
  41 tes / 224 asersi.
- Gabungan F7 publik (`DirektoriPublikTest|CooperativePublicationTest|PublicSurfaceExposureTest`):
  59 tes / 467 asersi.
- **Suite penuh: 535 tes / 2.375 asersi PASS.**

Seluruh bukti tes berjalan pada SQLite `:memory:`; **bukan** bukti kompatibilitas MariaDB.
Audit `region_id` dijalankan read-only pada MariaDB 10.4.32 dev; DDL target 10.11.19 kemudian diuji
pada 34.3.

### 30.9 Batas & blocker `[TERBUKA]`

- Dataset sudah diimpor ke database lokal (26 Oktober 2026); database staging/produksi belum.
- `region_id` tetap nullable; belum ada backfill deterministik.
- Q-014 (staging/HTTPS/SMTP/backup) tetap **terblokir infra**; MariaDB 10.11.19 sudah diuji lokal.
- F6 dan F7 **selesai dari sisi implementasi & pengujian** pada finalisasi 24 Oktober 2026
  (lihat §31); F8 kemudian selesai secara lokal (33).
- Nama OCR dapat mengandung artefak yang belum terbukti; jangan mengoreksi tanpa sumber.
- Tidak ada commit/push pada tahap ini.

## 31. Keputusan finalisasi 24 Oktober 2026 — penutupan F6/F7 & D-5 `[KEPUTUSAN]`

Pemilik proyek meminta finalisasi setelah Tahap 107: menutup pekerjaan yang bukti AC-nya tersedia,
memperbaiki celah lokal, dan menyerahkan laporan tanpa commit/push.

### 31.1 D-5 dieksekusi secara PROVISIONAL

D-5 (§17.4) sebelumnya menunda filter periode pada daftar koperasi sampai Q-010 dijawab. Pada
finalisasi, filter periode **diimplementasikan** memakai definisi yang **sudah tercatat** sebagai
provisional (F6-3: tahun `cooperatives.created_at`) — **bukan definisi baru**:

- daftar koperasi memiliki filter `tahun` yang memakai `CooperativeFilters`/`PeriodExpression`;
- tautan “Daftar” pada tabel status/wilayah/jenis/periode dashboard mempertahankan filter aktif;
- laporan menambahkan rekap per periode (tahun) selain filter periode;
- UI memberi label **PROVISIONAL** dan tetap merujuk Q-010.

**Batas:** bila dinas menetapkan definisi periode resmi yang berbeda, filter/rekap harus disesuaikan
lewat keputusan baru. Implementasi ini **tidak** mengklaim Q-010 sudah terjawab.

### 31.2 Perbaikan lokal pada finalisasi

| Area | Perbaikan | Bukti regresi |
| --- | --- | --- |
| FR-200 | Halaman error `419` & `500` yang ramah, tanpa detail teknis | `ErrorHandlingTest` |
| FR-160/FR-020 | Filter periode daftar + tautan dashboard setara | `CooperativeRegionFilterTest`, `AdminDashboardTest` |
| FR-170 | Rekap laporan per periode | `AdminReportTest` |
| FR-180/191 | Skema website publik hanya `http`/`https` untuk data lama/import | `DirektoriPublikTest` |
| FR-021 | Antrian dashboard tidak memuat pengajuan koperasi terarsip | `AdminDashboardTest` |
| FR-190 | Peringatan admin bila publikasi aktif tetapi status tidak lagi layak tampil | `CooperativePublicationTest` |
| UX | Navigasi internal tersedia di ponsel/tablet (scroll horizontal) | pemeriksaan manual/responsif |

### 31.3 Status akhir

- **F6: SELESAI** (implementasi & pengujian; AC yang dapat diverifikasi lokal lulus).
- **F7: SELESAI** (implementasi & pengujian; AC-PUB-01/02/03 lulus).
- **F8: belum dimulai.**
- **Tidak ada commit/push** dan **tidak ada migrasi/import ke DB persisten** pada finalisasi.
- Tiga migrasi tetap `Pending`; Q-012 (impor wilayah/backfill/`NOT NULL`), Q-010 (definisi resmi
  periode), Q-006 (whitelist field), Q-022 (format laporan), dan Q-014 (staging/MariaDB 10.11)
  tetap menjadi batas eksternal.
- **Batas internal yang saat §31 ditulis belum ditutup (di luar F6/F7):** FR-003 (idle timeout/daftar
  sesi/paksa logout), FR-004 (reset password), dan US-009 (manajemen akun pengelola via UI) belum
  diimplementasikan; provisioning akun memakai `php artisan user:create`. FR-171 (ekspor) dan
  FR-183 (statistik publik) adalah FASE-2, belum diimplementasikan. FR-191 memakai allowlist
  konfigurasi, bukan UI pengaturan field oleh admin. **Status terbaru ada di §32.**
- Angka suite final dan rincian serah-terima ada pada laporan final; angka pada §30.8 adalah
  **baseline Tahap 107**, bukan angka final.

---

## 32. Penutupan gap F1 & UI FR-191 (24 Oktober 2026) `[KEPUTUSAN]`

Pemilik proyek meminta empat gap lokal ditutup **langsung** setelah finalisasi F6/F7, tanpa memulai
F8, tanpa commit/push, dan tanpa mengubah data/migrasi pada database persisten. Implementasi tetap
berada pada stack Laravel + Blade + Tailwind + session guard bawaan.

### 32.1 FR-003 — manajemen sesi

- **Idle timeout:** middleware `EnforceSessionIdleTimeout` (alias `idle`) diterapkan pada seluruh
  route terautentikasi. Batas default **30 menit**, dapat diatur lewat `SESSION_IDLE_TIMEOUT`
  (`config('session.idle_timeout')`, minimum 1 menit). Timestamp aktivitas disimpan di sesi
  (`auth.last_activity_at`) dan diperbarui pada setiap request terautentikasi; bila lewat batas,
  sesi di-logout, di-invalidate, dan pengguna diarahkan ke login dengan pesan.
- **Daftar & pengakhiran sesi:** `ActiveSessionService` membaca tabel `sessions` **hanya** bila
  `SESSION_DRIVER=database`. Halaman `/akun/keamanan` menampilkan sesi pengguna, menandai sesi saat
  ini, serta menyediakan aksi “akhiri semua sesi lain” dan “keluar dari semua perangkat”.
- **Batas driver:** driver `file`, `cookie`, `array`, `redis`, dll. **tidak** menyimpan metadata
  lintas perangkat yang seragam. Untuk driver tersebut UI menyatakan keterbatasan dan tidak
  mensimulasikan daftar sesi. Ini bukan klaim bahwa semua driver mendukung fitur.
- **Otorisasi/CSRF:** route berada di grup `auth` + `active` + `idle`; form memakai `@csrf` dan
  method `DELETE`. Tidak ada route publik untuk melihat/menghapus sesi pengguna lain.

### 32.2 FR-004 — reset password

- Memakai **password broker Laravel** (`password_reset_tokens`), sehingga token disimpan sebagai
  hash, memiliki masa berlaku (`config/auth.php` → 60 menit), dan token sekali pakai.
- Permintaan tautan selalu memberi respons generik, baik email terdaftar maupun tidak, untuk
  mengurangi enumerasi akun. Rate limit: route `throttle:6,1`, ditambah pembatas controller
  3 permintaan/10 menit per email+IP; broker juga memiliki throttle 60 detik.
- Password baru divalidasi minimal 8 karakter dan harus cocok dengan konfirmasi. Setelah reset,
  password lama tidak berlaku, `remember_token` diganti, dan seluruh sesi pengguna dihapus bila
  driver sesi mendukung. Bila pengguna sedang login, sesi saat ini juga diakhiri.
- **Admin-created account:** admin tidak pernah melihat/mengetik password akun baru. Sistem membuat
  password acak yang langsung di-hash, lalu mengirim tautan reset ke email akun. Admin dapat
  mengirim ulang tautan dari UI. Ini menggantikan “password sementara” pada PRD tanpa menampilkan
  atau menyimpan password teks biasa.
- **Mail:** lingkungan pengembangan memakai `MAIL_MAILER=log` (tidak mengirim email nyata); test
  memakai `Notification::fake()`. Transport produksi (SMTP/TLS, alamat pengirim, deliverability)
  **belum diverifikasi** dan tetap menjadi prasyarat go-live.

### 32.3 US-009 — manajemen akun

- Halaman `/admin/akun` (daftar/cari/filter), tambah, ubah, aktif/nonaktif, dan kirim tautan reset.
  Hanya `admin_dinas`; dijaga middleware `role:admin_dinas`, Gate `manage-accounts`, dan
  `authorize()` pada Form Request.
- Peran dibatasi `admin_dinas`/`pengelola_koperasi`. `koperasi_id` divalidasi harus ada dan
  belum dihapus (soft delete); untuk admin dinas nilainya selalu dipaksa `null`. Pengelola dapat
  ditautkan ke satu koperasi.
- Admin tidak dapat mengubah peran/menonaktifkan akun sendiri; admin aktif terakhir juga tidak
  dapat dinonaktifkan. Aksi sensitif dicatat ke `activity_logs` tanpa password/token.
- Tidak ada akun contoh/default berpassword yang ditambahkan ke seeder atau database persisten.

### 32.4 FR-191 — pengaturan field publik

- Migrasi **aditif** `2026_10_24_000003_create_settings_table` membuat tabel `settings`
  (`key` unik, `value` teks). Sudah dijalankan di DB lokal; staging/produksi belum.
- `PublicFieldSettings` menyimpan pilihan `public.fields` sebagai JSON: daftar field, alamat wilayah,
  email, dan telepon. Bila tabel/belum ada baris, nilai default dari `config('koperasi.public.*')`
  dipakai agar halaman publik tidak gagal.
- **Batas Q-008 berlapis:** `config('koperasi.public.allowed_fields')` dan konstanta
  `Q008_ALLOWED_FIELDS` di service. Config hanya dapat mempersempit; field di luar Q-008 (NIK,
  telepon pribadi, data anggota, omzet terlarang) ditolak validasi dan disaring lagi sebelum
  dikirim ke view. `allowed_fields` bukan mekanisme otorisasi; otorisasi tetap Gate/middleware.
- UI `/admin/pengaturan/publik` hanya menampilkan field allowlist. Perubahan langsung dipakai
  direktori dan profil publik; email/telepon tetap dimasking dan alamat tetap dari hierarki
  `regions` (bukan teks bebas).

### 32.5 Verifikasi & batas

- Suite saat itu: **583 tes / 2.609 asersi PASS** pada SQLite `:memory:`; hasil terkini di 34.3.
  Pint, `view:cache`,
  `npm run build`, dan `git diff --check` dijalankan pada penutupan.
- **Bukan bukti MariaDB 10.11:** suite SQLite tidak membuktikan kompatibilitas; suite 10.11.19
  lokal kemudian dijalankan (34.3).
- **Migrasi persisten saat itu:** empat migrasi masih `Pending`; semuanya sudah `Ran` di DB lokal
  (33). Tidak ada `migrate:fresh`, rollback, atau perubahan data persisten pada pekerjaan ini.
- Q-014 (staging/HTTPS/SMTP/backup), Q-012 (backfill/`NOT NULL`), Q-010 (periode resmi),
  Q-006/Q-022 (whitelist/format laporan), dan transport email produksi tetap terbuka.
- FR-171 (ekspor) dan FR-183 (statistik publik) tetap FASE-2/F8 dan tidak diimplementasikan.

---

## 33. Penyelesaian F8 & integrasi wilayah (26 Oktober 2026) `[KEPUTUSAN + FAKTA]`

Pemilik proyek menyetujui pengerjaan **F8 sebagai satu batch dengan checkpoint per sub-fitur**
(menyimpang dari kebiasaan satu fase per peninjauan, karena seluruh FR F8 sudah lama tertunda).
Semua keputusan di bawah bersifat **PROVISIONAL** sampai dinas mengonfirmasi aturan resmi; tidak ada
formula, ambang, atau pembagian wilayah yang diklaim sebagai ketetapan resmi.

### 33.1 Ringkasan FR yang diselesaikan

| FR | Implementasi | Sifat |
| --- | --- | --- |
| FR-012 | Peran `admin_wilayah`, `users.region_id`, Policy/Gate sadar wilayah, middleware resource, filter query, validasi form | PROVISIONAL (Q-005) |
| FR-022 | Ringkasan izin mendekati kedaluwarsa di dashboard admin; ambang hari konfigurabel | PROVISIONAL (ambang 30 hari) |
| FR-072 | Unggah versi dokumen, rantai `previous_version_id`, `superseded_at`, retensi berkas lama | Sesuai BR-011 |
| FR-082 | Lima komponen skor, skor berbobot & predikat dihitung server-side | PROVISIONAL (Q-007) |
| FR-091 | Checklist kepatuhan tata kelola, status `patuh`/`deviasi`, catatan wajib saat deviasi | PROVISIONAL (Q-007) |
| FR-171 | Ekspor CSV laporan (stream), hanya data `approved`, audit ekspor | CSV saja |
| FR-183 | Halaman `/statistik` agregat publik | Hanya data published + approved |

### 33.2 FR-012 — pembatasan wilayah admin

- Peran baru **`admin_wilayah`** ditambahkan lewat `RoleSeeder` (data referensi, tanpa akun contoh).
  `admin_dinas` tetap **super admin** dan tidak dibatasi.
- Kolom **`users.region_id`** (nullable, FK `regions.id`, `nullOnDelete`) ditambahkan lewat migrasi
  aditif `2026_10_26_000001_add_region_id_to_users_table`.
- **Default nonaktif:** `ADMIN_REGION_SCOPE_ENABLED=false` /
  `config('koperasi.admin_region_scope.enabled')`. Saat nonaktif, perilaku lama tidak berubah dan
  `admin_wilayah` (bila ada) tidak dibatasi. Saat aktif, `AdminRegionScope` membatasi ke wilayah
  user **beserta seluruh turunannya**; `admin_wilayah` tanpa `region_id` tidak dapat mengakses data
  koperasi.
- Penjagaan berlapis: Policy (`CooperativePolicy` dan policy turunan) memeriksa wilayah,
  middleware `admin-region` menolak resource koperasi di luar wilayah, daftar/agregat difilter di
  controller/service, dan `WithinAdminRegion` memvalidasi pilihan wilayah pada form. Menyembunyikan
  menu **bukan** pengamanan.
- Akun `admin_wilayah` wajib memiliki `region_id` pada form akun; `admin_dinas` dipaksa `null`.
  Pembatasan ini **belum** diklaim sebagai pembagian kewenangan resmi (Q-005).

### 33.3 FR-022 — izin mendekati kedaluwarsa

- Dashboard admin menampilkan jumlah dan daftar izin yang akan kedaluwarsa dalam
  `config('koperasi.license_expiring_days')` hari (default **30**, dapat diubah lewat
  `LICENSE_EXPIRING_DAYS`).
- Daftar dibatasi izin aktif yang belum kedaluwarsa dan tetap tunduk pada cakupan wilayah admin.
- Ambang hari adalah **[PROPOSED]**, bukan aturan dinas.

### 33.4 FR-072 — versi & arsip dokumen

- Unggah versi baru menyimpan berkas baru, menautkan `previous_version_id` ke versi lama, dan
  mengisi `superseded_at` pada versi lama. **Tidak ada berkas yang dihapus** (BR-011).
- Rantai versi ditampilkan pada halaman izin; unduhan tetap melalui controller ber-Policy dan disk
  privat. Versi lama tidak dapat diubah menjadi versi aktif tanpa unggahan baru.
- Migrasi `2026_10_26_000004_add_versioning_to_documents_table` bersifat aditif.

### 33.5 FR-082 — perhitungan kesehatan otomatis (PROVISIONAL)

- Lima komponen: `permodalan`, `likuiditas`, `rentabilitas`, `kualitas_aset`, `efisiensi`
  (masing-masing 0–100), disimpan pada kolom `component_*`.
- Skor total = rata-rata berbobot; bobot default **20%** per komponen dan dinormalisasi bila total
  bobot bukan 1. Predikat default: **≥85 Sehat**, **70–84,99 Cukup Sehat**, **55–69,99 Kurang
  Sehat**, **<55 Tidak Sehat**.
- Perhitungan dilakukan **server-side**; `auto_calculated` menandai baris yang dihitung. Bila
  komponen tidak diisi, input manual tetap tersedia.
- Semua nilai berada di `config('koperasi.health_assessment')`. **Ini bukan formula resmi**
  (Q-007); perubahan formula cukup lewat konfigurasi, bukan migrasi.

### 33.6 FR-091 — evaluasi kepatuhan tata kelola (PROVISIONAL)

- Checklist: RAT tepat waktu, minimal **2 rapat/tahun**, laporan keuangan tersedia, dokumen dasar
  lengkap. Semua terpenuhi → `patuh`; ada yang tidak → `deviasi` dan **catatan wajib**.
- Status dihitung server-side; kolom `compliance_status`/`compliance_notes` ditambahkan lewat
  migrasi aditif `2026_10_26_000003_add_compliance_fields_to_governance_records_table`.
- Ambang rapat dan label status berada di `config('koperasi.governance_compliance')`. Checklist ini
  **bukan** kriteria resmi dinas (Q-007).

### 33.7 FR-171 — ekspor laporan

- Ekspor **CSV** memakai streamed response tanpa paket baru; hanya data `approved` yang diekspor dan
  filter laporan yang aktif ikut diterapkan.
- Nilai sel yang diawali karakter formula (`=`, `+`, `-`, `@`) diberi guard agar tidak dieksekusi
  spreadsheet (CSV injection).
- Setiap ekspor dicatat ke `activity_logs` (aksi ekspor + jumlah baris/filter), tanpa data pribadi
  berlebih. Excel/PDF **tidak** ditambahkan karena memerlukan dependency baru dan belum disetujui.

### 33.8 FR-183 — statistik agregat publik

- Halaman `/statistik` menampilkan **agregat saja**: total koperasi terpublikasi, per kabupaten/kota,
  per jenis, dan per status operasional.
- Hanya koperasi dengan `is_published = true` **dan** `status_verifikasi = approved` yang dihitung;
  tidak ada nama pengurus, kontak, NIK, omzet, atau data personal.
- Statistik memakai query agregat dan tidak mengekspos ID internal.

### 33.9 Impor wilayah, audit, dan backfill (Q-012)

- Dataset `database/data/regions/jawa_barat_2025.csv` diimpor ke database **lokal** MariaDB 10.4.32
  `koperasi_jabar` dengan `php artisan regions:import`: **6.612 baris** (1 provinsi, 27
  kabupaten/kota, 627 kecamatan, 5.957 desa/kelurahan). Importer idempoten dan transaksional.
- Audit `php artisan regions:audit-cooperatives` dijalankan setelah impor. Hasil:
  **1 koperasi, 0 memiliki wilayah, 1 `NULL`**, tanpa wilayah non-desa. Laporan:
  `storage/app/reports/region-audit-2026-10-26.json`.
- **Tidak ada backfill.** Satu-satunya koperasi (`id = 1`) belum memiliki `region_id` dan tidak ada
  pemetaan deterministik dari data yang tersedia. Menebak wilayah dilarang.
- **`cooperatives.region_id` tetap nullable.** Migrasi `NOT NULL` **tidak** dibuat karena audit belum
  bersih; ini memerlukan data resmi dan persetujuan pemilik.
- Impor dijalankan pada database **lokal**; database produksi/staging tidak tersedia dan tidak
  disentuh.

### 33.10 Migrasi

Empat migrasi aditif F8 dibuat dan dijalankan pada database lokal (batch 8):

1. `2026_10_26_000001_add_region_id_to_users_table`
2. `2026_10_26_000002_add_component_scores_to_health_assessments_table`
3. `2026_10_26_000003_add_compliance_fields_to_governance_records_table`
4. `2026_10_26_000004_add_versioning_to_documents_table`

Tidak ada migrasi lama yang diubah/dihapus, tidak ada `migrate:fresh`, rollback massal, atau
penghapusan data. Sebelum migrasi dibuat backup lokal di luar repositori.

### 33.11 Verifikasi

- Suite penuh: **610 tes / 2.728 asersi PASS** pada SQLite `:memory:` (1 skipped khusus
  MySQL/MariaDB). Suite MariaDB 10.11.19 portabel: **610 tes / 2.733 asersi PASS** (1 skipped
  khusus SQLite); lihat 34.3.
- Pemeriksaan lokal MariaDB 10.4.32: `php artisan migrate:status` menunjukkan **30 migrasi `Ran`,
  tidak ada `Pending`**; impor wilayah dan audit dijalankan pada database lokal. Kolom F8
  (`users.region_id`, komponen kesehatan, kepatuhan, versi dokumen) terverifikasi lewat
  `SHOW COLUMNS`.
- Smoke HTTP lokal: `/statistik`, `/koperasi`, dan `/login` mengembalikan **200**.
  `RoleSeeder` dijalankan ulang (aditif) untuk menambahkan peran referensi `admin_wilayah`.
- `pint`, `composer audit`, `view:cache`, build aset, `git diff --check`, dan pemeriksaan secret
  dijalankan sebelum commit.

### 33.12 Batas yang tetap terbuka

- **Q-005** pembagian kewenangan wilayah, **Q-007** formula kesehatan/kriteria kepatuhan, dan
  **Q-010** definisi periode resmi belum dijawab; semua implementasi di atas berlabel PROVISIONAL.
- **Q-012** backfill/`NOT NULL` belum selesai karena data koperasi lama tidak memiliki pemetaan
  wilayah yang terverifikasi.
- **Q-014** staging, HTTPS, domain, SMTP, dan backup produksi belum tersedia; MariaDB 10.11.19
  sudah diuji lokal, tetapi staging tetap diperlukan.
- Ekspor Excel/PDF, pembatasan wilayah resmi, dan formula resmi memerlukan keputusan/dependency baru.

---

## 34. Pengerasan produksi: header keamanan, preflight, dan verifikasi MariaDB 10.11 (26 Oktober 2026) `[KEPUTUSAN]`

Pemilik proyek meminta pekerjaan wajib sebelum status produksi diselesaikan. Pada saat yang sama,
staging, domain `.go.id`, HTTPS, SMTP nyata, dan akses backup **belum tersedia**. Karena itu
pekerjaan ini dibatasi pada pengerasan kode, pemeriksaan kesiapan, dan verifikasi database target
secara lokal. **Tidak ada klaim bahwa aplikasi sudah siap go-live.**

### 34.1 Header keamanan (NFR-SEC-017)

- Middleware global `App\Http\Middleware\SecurityHeaders` dipasang lewat
  `$middleware->append(...)` di `bootstrap/app.php`, sehingga header juga berlaku pada respons error.
- Header yang dipasang: `X-Frame-Options` (default `DENY`), `X-Content-Type-Options: nosniff`,
  `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, HSTS, dan CSP.
- HSTS **hanya** dikirim pada request HTTPS. Default `max-age=31536000`; `includeSubDomains` dan
  `preload` default **nonaktif** sampai domain resmi dikonfirmasi. Bila `preload=true`,
  `includeSubDomains` otomatis ditambahkan.
- CSP default **nonaktif** agar Vite HMR lokal tidak terganggu. Produksi wajib mengaktifkan
  `SECURITY_CSP_ENABLED=true`; `app:production-check` akan gagal bila masih nonaktif.
- Kebijakan CSP default: `default-src 'self'`, `script-src 'self'`, `style-src 'self'
  'unsafe-inline'`, `frame-ancestors 'none'`, `object-src 'none'`, `form-action 'self'`.
  `unsafe-inline` hanya untuk **style** (atribut `style` pada progress bar), bukan script.
- Seluruh `onsubmit="return confirm(...)"` di Blade diganti dengan atribut `data-confirm` dan
  listener delegasi di `resources/js/app.js`. Ini memungkinkan `script-src 'self'` tanpa
  `unsafe-inline`.
- `TRUSTED_PROXIES` dibaca dari `config/trustedproxy.php` (bukan `bootstrap/app.php`) agar tetap
  benar saat `config:cache` aktif. Daftar IP/CIDR harus diisi bila aplikasi berada di belakang
  reverse proxy/load balancer yang menangani HTTPS.

### 34.2 Perintah `app:production-check`

Perintah baru `php artisan app:production-check` bersifat **read-only** dan memeriksa:

- aplikasi: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL` HTTPS publik,
  `APP_TIMEZONE=Asia/Jakarta`, dan locale;
- sesi: driver `database`, cookie `secure`/`http_only`, `SameSite`, idle timeout, dan enkripsi;
- email/penyimpanan: mailer bukan `log`/`array`, alamat pengirim sah, cache persisten, queue,
  serta disk dokumen privat;
- header keamanan: HSTS, CSP, kebijakan CSP tanpa `script-src unsafe-inline`, dan
  `X-Frame-Options`;
- runtime: direktori `storage/`, `bootstrap/cache/`, `public/build/manifest.json`, izin `.env`
  (Linux), dan status `config:cache`/`route:cache`;
- database: driver `mysql`/`mariadb`, koneksi, versi server (MariaDB minimal 10.11 atau MySQL 8.0),
  migrasi tertunda, jumlah `regions`, dan akun `admin_dinas` aktif.

Opsi `--json` menghasilkan keluaran mesin; `--skip-database` dipakai pada pengujian/CI tanpa
database. Exit code `1` bila ada item `FAIL`; item `WARN` tidak menggagalkan perintah.

### 34.3 Verifikasi MariaDB 10.11 dan perbaikan tes

- MariaDB **10.11.19** portabel dijalankan pada port **3307** dengan data directory terpisah di
  luar repositori. Database uji `koperasi_jabar_test_1011` dibuat khusus; database
  `koperasi_jabar` tidak disentuh.
- Suite penuh pada MariaDB 10.11.19: **610 tes, 609 lulus, 1 skipped, 2.733 asersi**.
  Suite penuh pada SQLite `:memory:`: **610 tes, 609 lulus, 1 skipped, 2.728 asersi**.
  Pada MariaDB 10.4.32 lokal: **610 tes, 609 lulus, 1 skipped, 2.733 asersi**.
- `ParentSubmissionForeignKeyTest` diperbaiki agar **sadar driver**:
  - tes tanpa self-FK hanya berjalan di SQLite;
  - tes baru memverifikasi self-FK `submissions.parent_submission_id` dan `ON DELETE SET NULL`
    pada MySQL/MariaDB;
  - simulasi skema parsial melepas FK lebih dulu sebelum menghapus indeks, karena MariaDB menolak
    `DROP INDEX` selama indeks masih dipakai FK (`errno 1553`), lalu memverifikasi FK dibuat ulang.
- `app:production-check` pada MariaDB 10.11.19 dengan konfigurasi produksi, dataset wilayah
  (6.612 baris), dan satu akun admin uji: **32 PASS, 2 WARN, 0 FAIL**. Dua WARN adalah
  `SESSION_ENCRYPT=false` (disarankan true) dan cache konfigurasi/route belum dibuat saat perintah
  dijalankan. Ini **bukan** pengganti staging.
- Konfigurasi PHPUnit untuk database uji MySQL/MariaDB disediakan sebagai
  `phpunit.mariadb.xml.example`. Salin menjadi `phpunit.mariadb.xml` (sudah di-.gitignore), isi
  kredensial database uji khusus, lalu jalankan
  `php vendor/bin/phpunit -c phpunit.mariadb.xml --no-coverage`. **Jangan** arahkan ke database
  staging/produksi berisi data karena `RefreshDatabase` menjalankan migrasi bersih.

### 34.4 Batas yang tetap terbuka

- Verifikasi 10.11 di atas dilakukan pada **MariaDB portabel lokal**, bukan staging resmi. `sql_mode`
  produksi, konfigurasi web server, HTTPS/domain, SMTP nyata, dan backup/restore **belum** diuji.
- Uji konkurensi `lockForUpdate()` dan perilaku FK di bawah beban produksi belum dilakukan.
- Q-005, Q-007, Q-010, Q-012, Q-014, dan pertanyaan resmi lain tetap terbuka. Semua nilai
  PROVISIONAL tidak berubah.
- Tidak ada migrasi lama yang diubah, tidak ada `migrate:fresh` pada database berisi data, dan tidak
  ada data koperasi yang diubah. Database uji 10.11 hanya dipakai untuk verifikasi.
- Ini adalah status terkini; catatan pada 1-33 adalah historis dan tidak boleh dibaca sebagai
  status produksi.
