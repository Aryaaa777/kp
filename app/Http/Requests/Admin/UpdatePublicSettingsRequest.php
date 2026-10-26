<?php

namespace App\Http\Requests\Admin;

use App\Services\PublicFieldSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePublicSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin_dinas') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $allowed = app(PublicFieldSettings::class)->allowedFields();

        return [
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['string', Rule::in($allowed)],
            'show_region' => ['sometimes', 'boolean'],
            'contact_email' => ['sometimes', 'boolean'],
            'contact_telephone' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.required' => 'Pilih minimal satu field publik.',
            'fields.min' => 'Pilih minimal satu field publik.',
            'fields.*.in' => 'Field yang dipilih tidak diizinkan untuk tampil publik.',
            'show_region.boolean' => 'Pilihan alamat wilayah tidak valid.',
            'contact_email.boolean' => 'Pilihan email tidak valid.',
            'contact_telephone.boolean' => 'Pilihan telepon tidak valid.',
        ];
    }
}
