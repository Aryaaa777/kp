<?php

namespace App\Http\Requests\Cooperative;

use App\Models\Cooperative;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Arsip/nonaktifkan koperasi (FR-043). Alasan wajib diisi.
 */
class ArchiveCooperativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cooperative = $this->route('cooperative');

        return $cooperative instanceof Cooperative
            && ($this->user()?->can('delete', $cooperative) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'archived_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'archived_reason.required' => 'Alasan pengarsipan wajib diisi.',
            'archived_reason.max' => 'Alasan pengarsipan maksimal 1000 karakter.',
        ];
    }
}
