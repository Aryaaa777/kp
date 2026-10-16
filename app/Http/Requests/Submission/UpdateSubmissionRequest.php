<?php

namespace App\Http\Requests\Submission;

use App\Models\Submission;
use App\Services\Submissions\SubmissionModules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi perubahan draft pengajuan (FR-140) — hanya saat status masih editable.
 *
 * Modul tidak dapat diubah; field & aturan mengikuti handler modul pengajuan tersebut.
 */
class UpdateSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $submission = $this->route('submission');

        return $submission instanceof Submission
            && ($this->user()?->can('update', $submission) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $submission = $this->route('submission');
        $module = $submission instanceof Submission ? $submission->module : '';

        return array_merge([
            'title' => ['nullable', 'string', 'max:200'],
        ], SubmissionModules::fieldRules($module));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $submission = $this->route('submission');
        $module = $submission instanceof Submission ? $submission->module : '';

        $attributes = [];

        foreach (SubmissionModules::fields($module) as $field => $definition) {
            $attributes[$field] = mb_strtolower($definition['label'] ?? $field);
        }

        return $attributes;
    }
}
