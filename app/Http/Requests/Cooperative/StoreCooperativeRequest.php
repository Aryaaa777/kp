<?php

namespace App\Http\Requests\Cooperative;

use App\Models\Cooperative;
use App\Rules\ValidRegionSelection;
use App\Rules\WithinAdminRegion;
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
            // Hierarki wilayah (Q-012): provinsi → kabupaten/kota → kecamatan →
            // desa/kelurahan. `region_id` menyimpan wilayah desa/kelurahan.
            'province_id' => ['nullable', 'integer', 'exists:regions,id'],
            'regency_id' => ['nullable', 'integer', 'exists:regions,id'],
            'district_id' => ['nullable', 'integer', 'exists:regions,id'],
            'region_id' => [
                'nullable',
                'integer',
                'exists:regions,id',
                new ValidRegionSelection($this->all()),
                new WithinAdminRegion($this->user()),
            ],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            // Skema dibatasi eksplisit ke http/https (Tahap 87): rule `url` bawaan menerima
            // banyak skema non-web (mis. `ftp://`, `data://`, `file://`) yang tidak layak
            // ditautkan dari halaman publik.
            'website' => ['nullable', 'url:http,https', 'max:200'],
            'status_verifikasi' => ['required', Rule::in(Cooperative::assignableStatuses())],
            // Status operasional: tiga nilai resmi (Tahap 106). Wajib saat membuat koperasi baru;
            // data lama yang belum dikonfirmasi tetap NULL (lihat UpdateCooperativeRequest).
            'status_operasional' => ['required', Rule::in(Cooperative::STATUS_OPERASIONAL)],
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
            'website.url' => 'Format website tidak valid (harus diawali http:// atau https://, contoh: https://contoh.id).',
            'status_verifikasi.required' => 'Status data wajib dipilih.',
            'status_verifikasi.in' => 'Status data tidak dikenal.',
            'status_operasional.required' => 'Status operasional wajib dipilih.',
            'status_operasional.in' => 'Status operasional tidak dikenal.',
            'region_id.exists' => 'Wilayah yang dipilih tidak ditemukan.',
            'province_id.exists' => 'Provinsi yang dipilih tidak ditemukan.',
            'regency_id.exists' => 'Kabupaten/kota yang dipilih tidak ditemukan.',
            'district_id.exists' => 'Kecamatan yang dipilih tidak ditemukan.',
        ];
    }
}
