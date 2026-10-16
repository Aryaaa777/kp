<?php

namespace App\Http\Requests\Submission;

use App\Models\Submission;
use App\Services\Submissions\SubmissionModules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi pembuatan draft pengajuan (FR-140).
 *
 * Field yang boleh diajukan & aturannya berasal dari handler modul (whitelist Q-006).
 * `module` dibatasi pada modul yang terdaftar di `config('koperasi.submission_modules')`.
 *
 * @see docs/DECISIONS.md §12 (keputusan F5)
 */
class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Submission::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $module = (string) $this->input('module');

        return array_merge([
            'module' => ['required', 'string', Rule::in(array_keys(SubmissionModules::all()))],
            'title' => ['nullable', 'string', 'max:200'],
        ], SubmissionModules::fieldRules($module));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->fieldAttributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'module.in' => 'Modul pengajuan tidak dikenal.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fieldAttributes(): array
    {
        $attributes = [];

        foreach (SubmissionModules::fields((string) $this->input('module')) as $field => $definition) {
            $attributes[$field] = mb_strtolower($definition['label'] ?? $field);
        }

        return $attributes;
    }
}
