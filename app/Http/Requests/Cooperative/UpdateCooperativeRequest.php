<?php

namespace App\Http\Requests\Cooperative;

use App\Models\Cooperative;
use App\Rules\ValidRegionSelection;
use App\Rules\WithinAdminRegion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCooperativeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cooperative = $this->route('cooperative');

        return $cooperative instanceof Cooperative
            && ($this->user()?->can('update', $cooperative) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:200'],
            'nomor_identitas' => [
                'required',
                'string',
                'max:100',
                Rule::unique('cooperatives', 'nomor_identitas')
                    ->ignore($this->route('cooperative')),
            ],
            'jenis_koperasi' => ['required', 'string', 'max:100'],
            'bentuk_koperasi' => ['nullable', 'string', 'max:100'],
            'tanggal_berdiri' => ['nullable', 'date', 'before_or_equal:today'],
            'province_id' => ['nullable', 'integer', 'exists:regions,id'],
            'regency_id' => ['nullable', 'integer', 'exists:regions,id'],
            'district_id' => ['nullable', 'integer', 'exists:regions,id'],
            'region_id' => [
                'nullable',
                'integer',
                'exists:regions,id',
                new ValidRegionSelection($this->all(), $this->route('cooperative')),
                new WithinAdminRegion($this->user()),
            ],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            // Sama seperti form tambah: skema dibatasi ke http/https (Tahap 87).
            'website' => ['nullable', 'url:http,https', 'max:200'],
            'status_verifikasi' => ['required', Rule::in(Cooperative::assignableStatuses())],
            // Data lama yang status operasionalnya belum dikonfirmasi tetap boleh NULL saat
            // mengedit field lain. Status yang SUDAH ditetapkan tidak boleh dikosongkan diam-diam
            // (harus tetap salah satu dari tiga nilai resmi).
            'status_operasional' => [
                Rule::requiredIf(function (): bool {
                    $cooperative = $this->route('cooperative');

                    return $cooperative instanceof Cooperative
                        && $cooperative->status_operasional !== null;
                }),
                'nullable',
                Rule::in(Cooperative::STATUS_OPERASIONAL),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return (new StoreCooperativeRequest)->messages();
    }
}
