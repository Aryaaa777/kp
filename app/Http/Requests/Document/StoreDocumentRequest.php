<?php

namespace App\Http\Requests\Document;

use App\Models\CooperativeLicense;
use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Unggah dokumen pendukung (FR-070) pada sebuah izin.
 *
 * Hanya admin dinas (FLOW-ADM-05). Pengelola mengunggah dokumen melalui workflow pengajuan (F5).
 */
class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('license') instanceof CooperativeLicense
            && ($this->user()?->can('create', Document::class) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $extensions = implode(',', config('koperasi.document_allowed_extensions'));
        $mimetypes = implode(',', config('koperasi.document_allowed_mimetypes'));
        $maxKb = (int) config('koperasi.document_max_size_kb');

        return [
            'category' => ['required', 'string', 'max:100'],
            // NFR-SEC-008: whitelist ekstensi DAN MIME, plus batas ukuran (FR-070).
            'file' => ['required', 'file', "mimes:{$extensions}", "mimetypes:{$mimetypes}", "max:{$maxKb}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = round(((int) config('koperasi.document_max_size_kb')) / 1024);

        return [
            'category.required' => 'Kategori dokumen wajib diisi.',
            'category.max' => 'Kategori dokumen maksimal 100 karakter.',
            'file.required' => 'Berkas wajib dipilih.',
            'file.file' => 'Berkas tidak valid.',
            'file.mimes' => 'Tipe berkas tidak diizinkan. Gunakan PDF, JPG, JPEG, atau PNG.',
            'file.mimetypes' => 'Tipe berkas tidak diizinkan. Gunakan PDF, JPG, JPEG, atau PNG.',
            'file.max' => "Ukuran berkas melebihi batas {$maxMb} MB.",
        ];
    }
}
