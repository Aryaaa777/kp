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
