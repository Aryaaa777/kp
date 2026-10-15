<?php

namespace App\Http\Requests\Cooperative;

use App\Models\Cooperative;
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
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'url', 'max:200'],
            'status_verifikasi' => ['required', Rule::in(Cooperative::assignableStatuses())],
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
