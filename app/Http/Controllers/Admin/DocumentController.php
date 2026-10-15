<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Unggah & hapus dokumen pendukung (FR-070). Hanya admin dinas (FLOW-ADM-05).
 *
 * Berkas disimpan di disk **privat** (`config('koperasi.document_disk')`, default `local` →
 * `storage/app/private/documents/{koperasi_id}/...`), bukan di direktori publik (NFR-SEC-009).
 */
class DocumentController extends Controller
{
    public function store(
        StoreDocumentRequest $request,
        Cooperative $cooperative,
        CooperativeLicense $license,
    ): RedirectResponse {
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            return back()->withErrors(['file' => 'Berkas tidak valid.']);
        }

        $disk = (string) config('koperasi.document_disk', 'local');

        // Nama berkas dibuat acak oleh Laravel (`hashName`) — nama asli hanya disimpan sebagai metadata.
        $path = $file->store("documents/{$cooperative->getKey()}", $disk);

        $license->documents()->create([
            'cooperative_id' => $cooperative->getKey(),
            'category' => $request->validated()['category'],
            'original_name' => $this->sanitizeOriginalName($file->getClientOriginalName()),
            'file_path' => $path,
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
            'version' => 1,
            'uploaded_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.koperasi.izin.show', [$cooperative, $license])
            ->with('status', 'Dokumen berhasil diunggah.');
    }

    /**
     * Arsipkan dokumen (soft delete). Berkas fisik tetap disimpan sesuai kebijakan retensi (BR-011).
     */
    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        $document->delete();

        return back()->with('status', 'Dokumen telah diarsipkan.');
    }

    /**
     * Bersihkan nama asli berkas dari komponen path agar tidak muncul sebagai direktori/berbahaya
     * saat ditampilkan atau dipakai pada Content-Disposition.
     */
    private function sanitizeOriginalName(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $base = preg_replace('/[\x00-\x1F\x7F]+/', '', $base) ?? $base;
        $base = trim($base);

        return $base === '' ? 'dokumen' : mb_substr($base, 0, 255);
    }
}
