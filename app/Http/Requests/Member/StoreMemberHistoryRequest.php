<?php

namespace App\Http\Requests\Member;

use App\Http\Requests\Member\Concerns\ValidatesMemberCounts;
use App\Models\MemberHistory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validasi pembuatan catatan riwayat keanggotaan (FR-110).
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS: pencatatan per kejadian/perubahan (bukan rekap
 * tahunan). Aturan jumlah (kualitas data sementara, lihat `ValidatesMemberCounts`):
 * minimal salah satu jumlah terisi, tidak negatif, dan tidak boleh keduanya nol.
 * TIDAK mengumpulkan data anggota individual / NIK.
 * Mass assignment guard: `cooperative_id`/`recorded_by` ditetapkan di controller.
 *
 * @see docs/DATA_DICTIONARY.md §12 (member_histories)
 * @see docs/DECISIONS.md §10 (aturan provisional)
 */
class StoreMemberHistoryRequest extends FormRequest
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
            'event_date' => ['required', 'date', 'before_or_equal:today'],
            'change_type' => ['required', 'string', Rule::in(array_keys(MemberHistory::changeTypeOptions()))],
            'members_added' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'members_removed' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateMemberCounts($validator));
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
