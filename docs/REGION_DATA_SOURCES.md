# Sumber Data Wilayah (Q-012)

Dokumen ini mencatat **asal, versi, lisensi, checksum, cara ekstraksi, dan batas penggunaan** data
wilayah yang dipakai proyek. Rujukan kebutuhan tetap `docs/PRD.md`; keputusan teknisnya ada di
`docs/DECISIONS.md` §30.

Status singkat: **dataset resmi tersedia di repositori dan sudah diimpor ke database lokal**
MariaDB 10.4.32 (`koperasi_jabar`) pada 26 Oktober 2026 — **6.612 baris**. Database produksi/staging
belum tersedia dan tidak disentuh. Tidak ada nama/kode wilayah yang dikarang.

---

## 1. Sumber utama — kode & nama resmi

| Atribut | Nilai |
| --- | --- |
| Judul | Keputusan Menteri Dalam Negeri Nomor 300.2.2-2138 Tahun 2025 tentang Pemberian dan Pemutakhiran Kode, Data Wilayah Administrasi Pemerintahan, dan Pulau |
| Penerbit | Kementerian Dalam Negeri Republik Indonesia |
| Tanggal | 25 April 2025 (sesuai halaman berkas) |
| Berkas acuan | `kepmendagri_2138_2025.pdf` |
| Ukuran | 60.269.027 byte (± 57,5 MiB) |
| Halaman total | 4.428 |
| Halaman rincian Jawa Barat | 1.369–1.604 (indeks fisik PDF, 0-based; inklusif) |
| SHA-256 | `4a40e41cc2be515ca5b88df17a91e3f7ac6161ce7326a9fd38691753efa6b3fd` |
| URL unduhan | `https://upload.wikimedia.org/wikipedia/commons/5/51/Keputusan_Menteri_Dalam_Negeri_Nomor_300.2.2-2138_Tahun_2025.pdf` |
| Halaman sumber | `https://commons.wikimedia.org/wiki/File:Keputusan_Menteri_Dalam_Negeri_Nomor_300.2.2-2138_Tahun_2025.pdf` |
| Lisensi | **Klaim: domain publik** — peraturan perundang-undangan tidak dilindungi hak cipta menurut Pasal 42 Undang-Undang Nomor 28 Tahun 2014 tentang Hak Cipta; halaman Wikimedia menyatakan berkas berasal dari `e-database.kemendagri.go.id`. **Belum diverifikasi independen.** |

**Mengapa Kemendagri menjadi sumber kode:** kode wilayah pada lampiran Kepmendagri adalah kode
administrasi pemerintahan yang menjadi acuan resmi. Kode BPS **tidak** dipakai sebagai sumber kode
karena menurut pemeriksaan Tahap 107 kolom kode pada publikasi BPS memuat kesalahan cetak;
mencampur keduanya akan menghasilkan kode yang tidak konsisten.

Berkas PDF sumber **tidak disimpan di repositori**. Unduh dari URL di atas dan cocokkan SHA-256
sebelum ekstraksi.

**Batas:** PDF ini adalah hasil pindai dengan **lapisan teks OCR**, bukan PDF teks asli. Nama dapat
mengandung artefak OCR. Koreksi hanya dilakukan bila terbukti (lihat §4).

> **Status verifikasi review:** checksum PDF sumber dan status lisensi di atas **belum diverifikasi
> ulang secara independen** pada review ini karena PDF sumber tidak disertakan di repositori.
> Klaim perbandingan BPS dan empat koreksi nama juga belum diverifikasi ulang karena PDF BPS tidak
> disertakan. Yang sudah diverifikasi adalah checksum CSV, jumlah baris, struktur, hierarki, dan
> konsistensi laporan. Konfirmasi hukum/instansi tetap diperlukan sebelum publikasi ulang dataset.

---

## 2. Sumber validasi — jumlah & bukti nama terpotong

| Atribut | Nilai |
| --- | --- |
| Judul | Master File Desa Provinsi Jawa Barat 2025 Semester 2 |
| Penerbit | Badan Pusat Statistik Provinsi Jawa Barat |
| Volume | 22, Nomor 1, 2026 |
| Katalog | 1304053.32 |
| Nomor publikasi | 32000.26025 |
| Tanggal rilis | 30 Juni 2026 |
| Revisi terakhir | 6 Oktober 2026 |
| Berkas acuan | `mfd_jabar_2025_s2.pdf` |
| Ukuran | 5.272.291 byte (± 5,03 MiB) |
| Halaman total | 776 |
| SHA-256 | `d1a2ca37003804b4c1d834bf8b1d3a6c8c6a6b523c3cfe486b31ec22f26d337f` |
| URL publikasi | `https://jabar.bps.go.id/id/publication/2026/06/30/dc879103e5ee651ac3e57f0d/master-file-desa-provinsi-jawa-barat-2025-semester-2-.html` |
| Lisensi/ketentuan | Publikasi BPS; digunakan **hanya untuk validasi** dan tidak disebarluaskan ulang. Hak cipta dan ketentuan penggunaan BPS tetap berlaku (`https://www.bps.go.id/id/terms`). |

BPS dipakai untuk:

1. membandingkan **jumlah** desa/kelurahan (5.957 baris) dan kecamatan (627);
2. membuktikan empat nama yang terpotong pada OCR Kepmendagri (§4).

BPS **tidak** dipakai sebagai sumber kode dan tidak disalin mentah-mentah ke dataset. Berkas BPS
juga tidak disimpan di repositori; unduh dari publikasi resmi dan cocokkan SHA-256.

---

## 3. Dataset yang dihasilkan

Berkas: `database/data/regions/jawa_barat_2025.csv`

| Level | Jumlah |
| --- | --- |
| Provinsi | 1 |
| Kabupaten | 18 |
| Kota | 9 |
| Kecamatan | 627 |
| Desa/kelurahan | 5.957 |
| **Total** | **6.612** |

Pada CSV, **kabupaten dan kota sama-sama `level=regency`**; kolom `type` (`kabupaten`/`kota`)
membedakannya. Importer memetakan keduanya ke enum database `regency`/`city`.

- SHA-256 CSV: `208d7aca0cc7993721a39f3e3c003e290b9675d37d2f7d8506d5bc7f755a6526`
- Laporan provenance: `database/data/regions/jawa_barat_2025.report.json` (memuat checksum sumber,
  checksum output, jumlah per level, daftar koreksi, dan catatan ekstraksi).

Format CSV (header tetap, urutan tetap):

```csv
level,code,name,parent_code,type
province,32,Jawa Barat,,province
regency,32.01,Kabupaten Bogor,32,kabupaten
regency,32.73,Kota Bandung,32,kota
district,32.73.01,Sukasari,32.73,district
village,32.73.01.1001,Sukasari,32.73.01,village
```

- `code` **selalu string**; nol di depan dipertahankan (`32.01`, `32.73.01.1001`).
- `type` pada CSV memakai `kabupaten`/`kota`; importer memetakan ke enum database
  `regency`/`city`.
- `village` mencakup **desa dan kelurahan**. Digit pertama segmen terakhir membedakan kelurahan
  (`1`) dan desa (`2`); skema database tidak memisahkan tipenya.
- Nama diambil dari teks resmi. Nama yang sama di wilayah berbeda tetap sah karena kunci unik
  adalah `code`, bukan `name`.

---

## 4. Ekstraksi dan koreksi

Skrip: `scripts/extract_regions.py` (Python 3, **bukan** dependensi runtime aplikasi).

```sh
python -m pip install pypdf
python scripts/extract_regions.py \
  --pdf /path/kepmendagri_2138_2025.pdf \
  --output database/data/regions/jawa_barat_2025.csv \
  --report database/data/regions/jawa_barat_2025.report.json
```

Skrip:

1. membaca halaman 1.369–1.604;
2. mengambil kode dan nama dari baris resmi;
3. membersihkan catatan kolom keterangan yang menempel pada nama;
4. menerapkan koreksi eksplisit hanya untuk nama yang terbukti terpotong;
5. memvalidasi hierarki (`parent_code` harus prefix kode anak), panjang kode, duplikasi kode, dan
   jumlah baris;
6. menulis CSV + laporan checksum.

Koreksi yang diterapkan (hanya empat, semuanya terbukti dari kolom desa yang sama pada BPS):

| Kode | Nama final |
| --- | --- |
| `32.09.29.2003` | Prajawinangun Wetan |
| `32.12.28.2005` | Kedokanbunder Wetan |
| `32.15.06.2001` | Rengasdengklok Selatan |
| `32.15.06.2002` | Rengasdengklok Utara |

Kasus yang **sengaja tidak** "diperbaiki": `32.03.31.2004` tetap memakai nama seperti tercetak di
PDF resmi, `Mekarwangi/Sukatani`; BPS menampilkan `SUKATANI`. Perbedaan ini dicatat, bukan
ditebak. Jangan menambah koreksi tanpa bukti dari sumber resmi.

Batas OCR: artefak lain yang tidak terbukti **tidak** dikoreksi. Bila dinas menemukan nama yang
salah, perbaiki lewat pembaruan dataset + laporan koreksi, bukan edit manual di database.

---

## 5. Impor ke database

Perintah: `php artisan regions:import`

```sh
# Validasi tanpa menulis
php artisan regions:import --dry-run \
  --expected=province:1,regency:27,district:627,village:5957

# Impor (transaksional & idempoten)
php artisan regions:import \
  --expected=province:1,regency:27,district:627,village:5957
```

Perilaku:

- Membaca CSV default `database/data/regions/jawa_barat_2025.csv` (boleh diberi path lain).
- Memvalidasi header, level, tipe, panjang/kode, `parent_code`, hierarki, dan duplikasi kode
  **sebelum** menulis.
- Menjalankan seluruh penulisan dalam **satu transaksi**; gagal validasi = tidak ada perubahan.
- **Idempoten**: `updateOrCreate` berdasarkan `code`; menjalankan ulang tidak menggandakan baris.
- `--expected` membandingkan jumlah per level dan berhenti bila tidak sesuai.
- Tidak menghapus wilayah yang tidak ada di CSV. Penghapusan/penataan data lama harus menjadi
  keputusan terpisah.

Urutan aman pada database yang sudah berisi koperasi:

1. backup database;
2. `regions:import --dry-run`;
3. `regions:import`;
4. `regions:audit-cooperatives` (lihat §6);
5. backfill `region_id` hanya bila pemetaannya deterministik;
6. baru pertimbangkan migrasi `NOT NULL`.

**Verifikasi impor pada Tahap 107 (SQLite sementara):** impor pertama menghasilkan **6.612 baris**
(1 provinsi, 27 kabupaten/kota, 627 kecamatan, 5.957 desa/kelurahan); impor kedua menghasilkan
**0 dibuat / 6.612 diperbarui** dan jumlah tetap **6.612**. Ini membuktikan idempotensi pada data CSV
nyata, bukan hanya fixture tes.

**Impor database lokal (26 Oktober 2026):** `php artisan regions:import` dijalankan pada database
lokal MariaDB 10.4.32 `koperasi_jabar` dan menghasilkan **6.612 baris** dengan komposisi yang sama.
Impor ini **bukan** bukti database produksi; staging/produksi tidak tersedia.

---

## 6. Audit `region_id` (read-only)

Perintah: `php artisan regions:audit-cooperatives`

```sh
php artisan regions:audit-cooperatives
php artisan regions:audit-cooperatives --json=storage/app/region-audit.json
```

Perintah ini **hanya membaca** dan tidak mengubah data. Laporan memuat:

- total koperasi, jumlah yang punya wilayah, jumlah `NULL`;
- jumlah wilayah desa/kelurahan;
- jumlah wilayah non-desa (kecamatan/kabupaten/kota/provinsi) yang perlu penanganan;
- daftar koperasi bermasalah beserta `region_id`, kode, tipe, dan alasan:
  `region_id_null`, `region_tidak_ditemukan`, `region_bukan_desa_kelurahan`.

Laporan JSON **tidak** memuat data pribadi.

**Hasil audit pada database lokal (26 Oktober 2026, read-only):** database `koperasi_jabar` pada
**MariaDB 10.4.32 (XAMPP)**, bukan SQLite dan bukan MariaDB 10.11.

| Metrik | Jumlah |
| --- | --- |
| Total koperasi | 1 |
| Dengan wilayah | 0 |
| Tanpa wilayah (`NULL`) | 1 |
| Wilayah desa/kelurahan | 0 |
| Wilayah non-desa | 0 |

`regions` pada database lokal berisi **6.612 baris** setelah impor, tetapi satu-satunya koperasi
(`id = 1`) masih **`region_id = NULL`**. Karena tidak ada `region_id` lama yang bisa dipetakan secara
deterministik, **tidak ada backfill** yang dilakukan dan `region_id` **tidak** diubah menjadi
`NOT NULL`. Temuan `region_id_null` memerlukan data resmi dari pihak berwenang, bukan tebakan.
Laporan lengkap: `storage/app/reports/region-audit-2026-10-26.json`.

---

## 7. Pemakaian di aplikasi

- Form admin koperasi memakai empat pilihan berjenjang: **provinsi → kabupaten/kota → kecamatan →
  desa/kelurahan**. Nilai akhir disimpan di `cooperatives.region_id`.
- Validasi server-side `App\Rules\ValidRegionSelection` memastikan keempat nilai saling konsisten
  (anak benar-benar berada di bawah induknya). Menyembunyikan/menonaktifkan pilihan di browser
  **bukan** pengamanan.
- Data lama yang `region_id`-nya menunjuk kecamatan/kabupaten/kota **tetap ditampilkan** sebagai
  “wilayah saat ini” dan tidak dipaksa berubah saat edit. Nilai non-desa tidak dapat **dipilih
  baru**.
- Filter admin, dashboard, laporan, dan direktori publik memakai **descendant**: memilih
  kabupaten/kota mencakup seluruh desa/kelurahan di bawahnya.
- Dashboard dan laporan mengelompokkan ke **kabupaten/kota** agar angka tidak pecah menjadi ribuan
  desa.
- Alamat publik dibentuk dari **hierarki wilayah** (controller publik `regionPath()`); halaman admin
  memakai `Region::fullPath()`. Teks bebas `alamat` tetap tidak dipublikasikan (keputusan Q-008).

---

## 8. Tinjauan migrasi (status lokal vs target)

Empat migrasi yang sebelumnya `Pending` kini **sudah dijalankan pada database lokal** MariaDB 10.4.32
(batch 7). Empat migrasi F8 berikutnya juga dijalankan (batch 8). Tinjauan di bawah tetap berlaku
sebagai catatan desain; **MariaDB 10.11.19 lokal sudah diuji; staging/produksi belum**.

| Migrasi | Isi | SQLite | MariaDB 10.11 |
| --- | --- | --- | --- |
| `2026_10_19_000001_add_parent_submission_id_to_submissions_table` | kolom nullable `submissions.parent_submission_id`, indeks eksplisit, self-FK `ON DELETE SET NULL` | kolom + indeks dibuat; **self-FK sengaja dilewati**; `down()` melepas indeks sebelum kolom | kolom + indeks + self-FK; `down()` melepas FK berdasarkan definisi. Perilaku DDL nyata terverifikasi pada MariaDB 10.11.19 lokal |
| `2026_10_24_000001_add_unpublished_tracking_to_cooperatives_table` | `unpublished_at`, `unpublished_by` nullable; FK ke `users.id` `ON DELETE SET NULL` | kolom dibuat; **FK dilewati**; `down()` hanya melepas objek milik migrasi | kolom + FK; `down()` memakai `dropForeign([kolom])`. Terverifikasi pada MariaDB 10.11.19 lokal |
| `2026_10_24_000002_add_status_operasional_to_cooperatives_table` | `status_operasional` enum nullable (`aktif`, `tidak_aktif`, `bubar`) | enum dipetakan ke `varchar` + `CHECK` oleh grammar Laravel; kolom nullable | enum native; kolom nullable. Terverifikasi pada MariaDB 10.11.19 lokal |
| `2026_10_24_000003_create_settings_table` | Tabel `settings` (`key` unik, `value` teks, timestamps) untuk FR-191 | dibuat sebagai tabel biasa; tidak ada FK | tabel + unique index `key`. Terverifikasi pada MariaDB 10.11.19 lokal |

Kesimpulan: tidak ada migrasi baru yang diperlukan untuk data wilayah (tabel `regions` sudah ada).
Bukti runtime tersedia dari MariaDB **10.4.32** lokal (migrasi `Ran`, impor, dan audit `region_id`),
dan suite tes berjalan pada SQLite `:memory:`. DDL/migrasi juga sudah dijalankan pada MariaDB
**10.11.19** portabel lokal lewat suite penuh. Staging/produksi tetap **belum** diverifikasi;
verifikasi runtime di sana tetap menjadi **Q-014** dan memerlukan staging/kredensial.

---

## 9. Batas & pekerjaan yang belum selesai

- Dataset **sudah diimpor ke database lokal** (6.612 baris); database produksi/staging belum.
- `cooperatives.region_id` **masih nullable** dan belum `NOT NULL`; audit menunjukkan satu koperasi
  lama (`id = 1`) belum memiliki wilayah dan tidak ada pemetaan deterministik untuk backfill.
- Nama OCR dapat mengandung artefak yang belum terbukti; jangan mengoreksi tanpa sumber.
- Kode BPS tidak dipakai; jangan mencampur kode BPS dan Kemendagri.
- Kompatibilitas MariaDB 10.11.19 sudah diuji pada server portabel lokal; staging/produksi belum.
- Perubahan dataset harus disertai pembaruan checksum, laporan, dan catatan keputusan.
