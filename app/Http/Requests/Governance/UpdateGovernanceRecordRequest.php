<?php

namespace App\Http\Requests\Governance;

use App\Models\GovernanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi perubahan catatan tata kelola (FR-090).
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS. `cooperative_id`/`recorded_by` tidak dapat diubah.
 *
 * @see docs/DATA_DICTIONARY.md §10 (governance_records)
 */
class UpdateGovernanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $record = $this->route('governanceRecord');
        $cooperativeId = $this->route('cooperative')?->id;
        $period = $this->input('period', $record?->period);
        $maxYear = (int) date('Y') + 1;

        return [
            'period_year' => [
                'sometimes', 'required', 'integer', 'min:1900', 'max:'.$maxYear,
                Rule::unique('governance_records')
                    ->ignore($record)
                    ->where(fn ($query) => $query
                        ->where('cooperative_id', $cooperativeId)
                        ->where('period', $period)),
            ],
            'period' => ['sometimes', 'required', 'string', Rule::in(array_keys(GovernanceRecord::periodOptions()))],
            'meeting_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'governance_score' => ['nullable', 'numeric', 'between:0,100'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', Rule::in(GovernanceRecord::STATUSES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'period_year' => 'tahun periode',
            'period' => 'periode',
            'meeting_count' => 'jumlah rapat',
            'governance_score' => 'skor tata kelola',
            'summary' => 'ringkasan',
            'status' => 'status',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_year.unique' => 'Catatan tata kelola untuk periode ini sudah ada (tidak menimpa — BR-007).',
        ];
    }
}
