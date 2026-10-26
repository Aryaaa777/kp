<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'in:admin_dinas,admin_wilayah,pengelola_koperasi'],
            'koperasi_id' => [
                'nullable',
                'integer',
                Rule::exists('cooperatives', 'id')->whereNull('deleted_at'),
            ],
            'region_id' => [
                'nullable',
                'integer',
                Rule::exists('regions', 'id'),
                Rule::requiredIf(fn (): bool => $this->input('role') === 'admin_wilayah'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama akun wajib diisi.',
            'region_id.required' => 'Wilayah wajib dipilih untuk peran Admin Wilayah.',
            'region_id.exists' => 'Wilayah yang dipilih tidak valid.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'role.required' => 'Peran wajib dipilih.',
            'role.in' => 'Peran tidak dikenal.',
            'koperasi_id.exists' => 'Koperasi yang dipilih tidak ditemukan.',
        ];
    }
}
