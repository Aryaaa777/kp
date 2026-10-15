<?php

namespace App\Http\Requests\Cooperative;

use App\Models\Cooperative;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCooperativeRequest extends FormRequest
{
    /**
     * Hanya admin dinas yang boleh menambah data induk (FR-040).
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Cooperative::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:200'],
            // Format nomor identitas resmi belum dikonfirmasi (Q-001) → divalidasi sebagai string
            // dan unik (BR-019, [ASUMSI]) tanpa memaksakan pola format yang belum pasti.
            'nomor_identitas' => ['required', 'string', 'max:100', Rule::unique('cooperatives', 'nomor_identitas')],
            'jenis_koperasi' => ['required', 'string', 'max:100'],
            'bentuk_koperasi' => ['nullable', 'string', 'max:100'],
            'tanggal_berdiri' => ['nullable', 'date', 'before_or_equal:today'],
            // Wilayah belum tersedia (Q-012) → opsional sampai referensi resmi di-seed.
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
        return [
            'nama.required' => 'Nama koperasi wajib diisi.',
            'nomor_identitas.required' => 'Nomor identitas wajib diisi.',
            'nomor_identitas.unique' => 'Nomor identitas sudah digunakan koperasi lain.',
            'jenis_koperasi.required' => 'Jenis koperasi wajib diisi.',
            'tanggal_berdiri.before_or_equal' => 'Tanggal berdiri tidak boleh melebihi hari ini.',
            'email.email' => 'Format email tidak valid.',
            'website.url' => 'Format website tidak valid (contoh: https://contoh.id).',
            'status_verifikasi.required' => 'Status data wajib dipilih.',
            'status_verifikasi.in' => 'Status data tidak dikenal.',
        ];
    }
}
