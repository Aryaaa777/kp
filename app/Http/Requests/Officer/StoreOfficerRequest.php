<?php

namespace App\Http\Requests\Officer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request untuk membuat pengurus koperasi baru.
 *
 * FR-120: CRUD susunan pengurus & periode jabatan.
 *
 * Validasi:
 * - Nama, jabatan, tanggal mulai wajib.
 * - Tanggal akhir opsional, >= tanggal mulai (jika diisi).
 * - Nomor identitas opsional (data pribadi sensitif).
 * - Status aktif boolean.
 *
 * Mass assignment guard: `cooperative_id` ditetapkan di controller, bukan dari input.
 *
 * @see docs/DATA_DICTIONARY.md §13 (officers)
 */
class StoreOfficerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Otorisasi ditangani Policy di controller
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'position' => ['required', 'string', 'max:100'],
            'identity_number' => ['nullable', 'string', 'max:50'],
            'period_start' => ['required', 'date', 'before_or_equal:today'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama pengurus',
            'position' => 'jabatan',
            'identity_number' => 'nomor identitas',
            'period_start' => 'tanggal mulai menjabat',
            'period_end' => 'tanggal akhir menjabat',
            'is_active' => 'status aktif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_start.before_or_equal' => 'Tanggal mulai menjabat tidak boleh di masa depan.',
            'period_end.after_or_equal' => 'Tanggal akhir menjabat harus setelah atau sama dengan tanggal mulai.',
        ];
    }
}
