<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreDocumentRequest;
use App\Http\Requests\Document\StoreDocumentVersionRequest;
use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Document;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
    public function __construct(private readonly ActivityLogger $logger) {}

    public function store(
        StoreDocumentRequest $request,
        Cooperative $cooperative,
        CooperativeLicense $license,
    ): RedirectResponse {
        Gate::authorize('create', Document::class);

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
     * Unggah versi baru dokumen (FR-072).
     *
     * Versi lama tidak dihapus: ditandai `superseded_at` dan tetap dapat diunduh
     * sebagai riwayat. Hanya versi terbaru yang boleh diganti.
     */
    public function storeVersion(
        StoreDocumentVersionRequest $request,
        Cooperative $cooperative,
        CooperativeLicense $license,
        Document $document,
    ): RedirectResponse {
        Gate::authorize('update', $document);

        if ((int) $document->cooperative_id !== (int) $cooperative->getKey()
            || (int) $document->documentable_id !== (int) $license->getKey()) {
            abort(404);
        }

        if ($document->isSuperseded()) {
            return back()->withErrors([
                'file' => 'Dokumen ini sudah digantikan versi yang lebih baru. Unggah versi baru dari dokumen terbaru.',
            ]);
        }

        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            return back()->withErrors(['file' => 'Berkas tidak valid.']);
        }

        $disk = (string) config('koperasi.document_disk', 'local');
        $path = $file->store("documents/{$cooperative->getKey()}", $disk);

        try {
            DB::transaction(function () use ($document, $license, $cooperative, $file, $path, $request): void {
                // Kunci baris agar dua unggahan bersamaan tidak memakai nomor versi yang sama.
                $current = Document::query()->lockForUpdate()->findOrFail($document->getKey());
                $newVersion = (int) $current->version + 1;

                $current->forceFill(['superseded_at' => now()])->save();

                $replacement = $license->documents()->create([
                    'cooperative_id' => $cooperative->getKey(),
                    'category' => $current->category,
                    'original_name' => $this->sanitizeOriginalName($file->getClientOriginalName()),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                    'size_bytes' => $file->getSize(),
                    'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
                    'version' => $newVersion,
                    'previous_version_id' => $current->getKey(),
                    'uploaded_by' => $request->user()->id,
                ]);

                $this->logger->log(
                    'document_version_created',
                    $replacement,
                    $request->user(),
                    [
                        'document_id' => $current->getKey(),
                        'replacement_id' => $replacement->getKey(),
                        'version' => $newVersion,
                    ],
                    'Versi baru dokumen diunggah.',
                );
            });
        } catch (\Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        return redirect()
            ->route('admin.koperasi.izin.show', [$cooperative, $license])
            ->with('status', 'Versi baru dokumen berhasil diunggah.');
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
