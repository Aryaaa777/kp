<?php

namespace App\Http\Requests\Submission;

use App\Models\Submission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi keputusan admin atas pengajuan (FR-141/142).
 *
 * `reason` **wajib** saat `needs_revision`/`rejected` (BR-005, AC-WF-03).
 */
class DecisionSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('submission');

        return $submission instanceof Submission
            && ($this->user()?->can('review', $submission) ?? false);
    }

    /**
     * Rapikan input sebelum validasi: alasan di-trim agar string yang hanya berisi spasi
     * dianggap kosong dan ditolak oleh aturan `required` (L5).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reason'))) {
            $this->merge(['reason' => trim($this->input('reason'))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', Rule::in(['approve', 'needs_revision', 'rejected'])],
            'reason' => [
                Rule::requiredIf(fn (): bool => in_array($this->input('decision'), ['needs_revision', 'rejected'], true)),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan wajib dipilih.',
            'reason.required' => 'Alasan wajib diisi saat meminta perbaikan atau menolak pengajuan.',
        ];
    }

    public function decision(): string
    {
        return (string) $this->validated('decision');
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }
}
