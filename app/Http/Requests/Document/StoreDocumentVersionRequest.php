<?php

namespace App\Http\Requests\Document;

use App\Models\Document;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Unggah versi baru dokumen (FR-072).
 *
 * Kategori dan relasi dokumen mengikuti versi sebelumnya; hanya berkas yang diganti.
 */
class StoreDocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document
            && ($this->user()?->can('update', $document) ?? false);
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
            'file.required' => 'Berkas wajib dipilih.',
            'file.file' => 'Berkas tidak valid.',
            'file.mimes' => 'Tipe berkas tidak diizinkan. Gunakan PDF, JPG, JPEG, atau PNG.',
            'file.mimetypes' => 'Tipe berkas tidak diizinkan. Gunakan PDF, JPG, JPEG, atau PNG.',
            'file.max' => "Ukuran berkas melebihi batas {$maxMb} MB.",
        ];
    }
}
