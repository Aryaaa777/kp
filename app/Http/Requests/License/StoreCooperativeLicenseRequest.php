<?php

namespace App\Http\Requests\License;

use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Menambah izin (FR-050/060) untuk sebuah koperasi. Hanya admin dinas.
 */
class StoreCooperativeLicenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('cooperative') instanceof Cooperative
            && ($this->user()?->can('create', CooperativeLicense::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $cooperativeId = $this->route('cooperative')?->getKey();

        return [
            'license_type' => ['required', Rule::in(CooperativeLicense::TYPES)],
            // Format nomor izin resmi belum dikonfirmasi (Q-004) → divalidasi string dan unik
            // per (koperasi, jenis izin). Rule::unique menilai tabel apa adanya sehingga keunikan
            // juga berlaku untuk data terarsip — konsisten dengan indeks unik basis data.
            'nomor' => [
                'required',
                'string',
                'max:100',
                Rule::unique('cooperative_licenses', 'nomor')
                    ->where('cooperative_id', $cooperativeId)
                    ->where('license_type', $this->input('license_type')),
            ],
            'tanggal_terbit' => ['required', 'date'],
            // AC-LIC-01: tanggal berlaku tidak boleh mendahului tanggal terbit.
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
        return [
            'license_type.required' => 'Jenis izin wajib dipilih.',
            'license_type.in' => 'Jenis izin tidak dikenal.',
            'nomor.required' => 'Nomor izin wajib diisi.',
            'nomor.unique' => 'Nomor izin ini sudah tercatat untuk koperasi dan jenis izin yang sama.',
            'tanggal_terbit.required' => 'Tanggal terbit wajib diisi.',
            'tanggal_terbit.date' => 'Tanggal terbit tidak valid.',
            'tanggal_berlaku_sampai.date' => 'Tanggal berlaku sampai tidak valid.',
            'tanggal_berlaku_sampai.after_or_equal' => 'Tanggal berlaku tidak boleh lebih awal daripada tanggal terbit.',
            'catatan.max' => 'Catatan maksimal 1000 karakter.',
        ];
    }
}
