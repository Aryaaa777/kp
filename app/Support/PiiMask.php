<?php

namespace App\Support;

/**
 * Masking data pribadi untuk tampilan publik (NFR-SEC-015, AC-PUB-03).
 *
 * Dipakai oleh halaman direktori/profil publik (F7) agar email & telepon tidak
 * pernah tampil utuh. Bentuk keluaran mengikuti `docs/PROMPT_PACK.md` §F7:
 *
 * - email  : `ab***@example.com` (2 karakter pertama + `***` + domain)
 * - telepon: `0812****5678`    (4 digit awal + `****` + 4 digit akhir)
 *
 * Kelas ini murni presentasi (tanpa I/O) sehingga dapat diuji unit. Nilai kosong
 * atau tidak memenuhi bentuk minimum dikembalikan `null` agar view menyembunyikan
 * barisnya, bukan menampilkan placeholder yang menyesatkan.
 */
final class PiiMask
{
    /**
     * Masking alamat email. Mengembalikan `null` bila tidak ada bentuk email yang wajar.
     */
    public static function email(?string $email): ?string
    {
        $email = trim((string) $email);

        if ($email === '' || substr_count($email, '@') !== 1) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $local = trim($local);
        $domain = trim($domain);

        if ($local === '' || $domain === '') {
            return null;
        }

        return mb_substr($local, 0, 2).'***@'.$domain;
    }

    /**
     * Masking nomor telepon. Hanya digit yang dipertahankan agar bentuk keluaran konsisten.
     *
     * Nomor dengan kurang dari 8 digit tidak dapat dimasking secara aman → dikembalikan
     * sebagai deretan bintang (atau `null` bila benar-benar kosong).
     */
    public static function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) < 8) {
            return str_repeat('*', strlen($digits));
        }

        return substr($digits, 0, 4).'****'.substr($digits, -4);
    }
}
