<?php

namespace App\Http\Requests\Health;

use App\Models\HealthAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi perubahan catatan kesehatan (FR-080).
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS. `cooperative_id`/`assessor_id` tidak dapat diubah.
 *
 * @see docs/DATA_DICTIONARY.md §9 (health_assessments)
 */
class UpdateHealthAssessmentRequest extends FormRequest
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
        $assessment = $this->route('healthAssessment');
        $cooperativeId = $this->route('cooperative')?->id;
        $period = $this->input('period', $assessment?->period);
        $maxYear = (int) date('Y') + 1;

        return [
            'period_year' => [
                'sometimes', 'required', 'integer', 'min:1900', 'max:'.$maxYear,
                Rule::unique('health_assessments')
                    ->ignore($assessment)
                    ->where(fn ($query) => $query
                        ->where('cooperative_id', $cooperativeId)
                        ->where('period', $period)),
            ],
            'period' => ['sometimes', 'required', 'string', Rule::in(array_keys(HealthAssessment::periodOptions()))],
            'assessment_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'auto_calculate' => ['nullable', 'boolean'],
            'component_permodalan' => $this->componentRule(),
            'component_likuiditas' => $this->componentRule(),
            'component_rentabilitas' => $this->componentRule(),
            'component_kualitas_aset' => $this->componentRule(),
            'component_efisiensi' => $this->componentRule(),
            'score' => ['nullable', 'numeric', 'between:0,100'],
            'predicate' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', Rule::in(HealthAssessment::STATUSES)],
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function componentRule(): array
    {
        return [
            'nullable',
            'numeric',
            'between:0,100',
            Rule::requiredIf(fn (): bool => $this->boolean('auto_calculate')),
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
            'assessment_date' => 'tanggal penilaian',
            'component_permodalan' => 'komponen permodalan',
            'component_likuiditas' => 'komponen likuiditas',
            'component_rentabilitas' => 'komponen rentabilitas',
            'component_kualitas_aset' => 'komponen kualitas aset',
            'component_efisiensi' => 'komponen efisiensi',
            'score' => 'skor',
            'predicate' => 'predikat',
            'notes' => 'catatan',
            'status' => 'status',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_year.unique' => 'Catatan kesehatan untuk periode ini sudah ada (tidak menimpa — BR-007).',
            'assessment_date.before_or_equal' => 'Tanggal penilaian tidak boleh di masa depan.',
        ];
    }
}
