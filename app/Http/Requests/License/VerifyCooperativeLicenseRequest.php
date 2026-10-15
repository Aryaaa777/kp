<?php

namespace App\Http\Requests\License;

use App\Models\CooperativeLicense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Verifikasi izin (FR-051/061). Hanya admin dinas; mengubah status hasil + catatan.
 */
class VerifyCooperativeLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $license = $this->route('license');

        return $license instanceof CooperativeLicense
            && ($this->user()?->can('verify', $license) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // `pending` tidak dapat dipilih di sini (status awal, belum diverifikasi).
            'status' => ['required', Rule::in(CooperativeLicense::verifiableStatuses())],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status verifikasi wajib dipilih.',
            'status.in' => 'Status verifikasi tidak dikenal.',
            'catatan.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
