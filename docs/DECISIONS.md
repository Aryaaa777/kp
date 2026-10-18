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
