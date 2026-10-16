<?php

namespace App\Http\Requests\Member\Concerns;

use Illuminate\Validation\Validator;

/**
 * Aturan lintas-field jumlah riwayat keanggotaan (FR-110).
 *
 * PROVISIONAL — aturan kualitas data sementara (lihat `docs/DECISIONS.md` §10):
 * - minimal salah satu jumlah terisi;
 * - nilai tidak boleh negatif (aturan `min:0` pada masing-masing FormRequest);
 * - kedua jumlah tidak boleh sama-sama nol;
 * - kedua jumlah boleh terisi bersamaan (penambahan & pengurangan pada kejadian yang sama).
 *
 * Dipakai bersama oleh Store & Update agar konsisten — termasuk saat salah satu nilai sudah
 * tersimpan lalu dikosongkan (Update memakai nilai efektif = request ∪ record tersimpan).
 *
 * Catatan teknis: dua aturan `required_without` saja TIDAK cukup, karena `required_without`
 * hanya melihat keberadaan field di request (nilai `0` dianggap "ada") dan tidak menolak pasangan
 * (0, 0). Karena itu penolakan "keduanya nol" diperiksa pada hook `after` di sini.
 */
trait ValidatesMemberCounts
{
    /**
     * @param  int|null  $existingAdded  Nilai tersimpan (dipakai Update; null pada Store).
     */
    protected function validateMemberCounts(Validator $validator, ?int $existingAdded = null, ?int $existingRemoved = null): void
    {
        $added = $this->effectiveCount('members_added', $existingAdded);
        $removed = $this->effectiveCount('members_removed', $existingRemoved);

        if ($added === null && $removed === null) {
            $message = 'Isi minimal salah satu: jumlah bertambah atau jumlah berkurang.';
            $validator->errors()->add('members_added', $message);
            $validator->errors()->add('members_removed', $message);

            return;
        }

        if ($added === 0 && $removed === 0) {
            $message = 'Jumlah bertambah dan jumlah berkurang tidak boleh keduanya nol.';
            $validator->errors()->add('members_added', $message);
            $validator->errors()->add('members_removed', $message);
        }
    }

    /**
     * Nilai efektif sebuah field: dari request bila key dikirim (termasuk dikosongkan → null),
     * jika tidak memakai nilai tersimpan.
     */
    private function effectiveCount(string $field, ?int $existing): ?int
    {
        if (! $this->exists($field)) {
            return $existing;
        }

        $value = $this->input($field);

        return ($value === null || $value === '') ? null : (int) $value;
    }
}
