<?php

namespace App\Http\Requests\License;

use App\Models\CooperativeLicense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Memperbarui data izin (FR-050/060). Status verifikasi TIDAK diubah lewat form ini —
 * perubahan status hanya melalui aksi verifikasi (FR-051/061).
 */
class UpdateCooperativeLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $license = $this->route('license');

        return $license instanceof CooperativeLicense
            && ($this->user()?->can('update', $license) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var CooperativeLicense $license */
        $license = $this->route('license');

        return [
            'license_type' => ['required', Rule::in(CooperativeLicense::TYPES)],
            'nomor' => [
                'required',
                'string',
                'max:100',
                Rule::unique('cooperative_licenses', 'nomor')
                    ->where('cooperative_id', $license->cooperative_id)
                    ->where('license_type', $this->input('license_type'))
                    ->ignore($license->getKey()),
            ],
            'tanggal_terbit' => ['required', 'date'],
            'tanggal_berlaku_sampai' => ['nullable', 'date', 'after_or_equal:tanggal_terbit'],
            'penerbit' => ['nullable', 'string', 'max:200'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return (new StoreCooperativeLicenseRequest)->messages();
    }
}
