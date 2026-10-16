<?php

namespace App\Http\Requests\Member;

use App\Http\Requests\Member\Concerns\ValidatesMemberCounts;
use App\Models\MemberHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi perubahan catatan riwayat keanggotaan (FR-110).
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS. Aturan jumlah disamakan dengan Store (lihat
 * `ValidatesMemberCounts`): minimal salah satu terisi, tidak negatif, tidak keduanya nol.
 * Untuk Update, penolakan memakai **nilai efektif** = request ∪ nilai tersimpan sehingga jumlah
 * yang tidak dikirim tetap dipertahankan.
 * `cooperative_id`/`recorded_by` tidak dapat diubah.
 *
 * @see docs/DATA_DICTIONARY.md §12 (member_histories)
 * @see docs/DECISIONS.md §10 (aturan provisional)
 */
class UpdateMemberHistoryRequest extends FormRequest
{
    use ValidatesMemberCounts;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'event_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'change_type' => ['sometimes', 'required', 'string', Rule::in(array_keys(MemberHistory::changeTypeOptions()))],
            'members_added' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'members_removed' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $history = $this->route('memberHistory');

        $validator->after(fn (Validator $validator) => $this->validateMemberCounts(
            $validator,
            $history?->members_added,
            $history?->members_removed,
        ));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'event_date' => 'tanggal kejadian',
            'change_type' => 'jenis perubahan',
            'members_added' => 'jumlah bertambah',
            'members_removed' => 'jumlah berkurang',
            'notes' => 'catatan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event_date.before_or_equal' => 'Tanggal kejadian tidak boleh di masa depan.',
        ];
    }
}
