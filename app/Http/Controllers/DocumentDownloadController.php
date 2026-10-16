<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh dokumen berotorisasi (FR-071).
 *
 * Berkas berada di storage privat; hanya admin dinas (semua dokumen) atau pengelola pemilik
 * koperasi yang bersangkutan yang boleh mengunduh (AC-DOC-02/03, NFR-SEC-010).
 */
class DocumentDownloadController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function __invoke(Document $document): StreamedResponse
    {
        Gate::authorize('download', $document);

        $disk = Storage::disk((string) config('koperasi.document_disk', 'local'));

        if (! $disk->exists($document->file_path)) {
            abort(404, 'Berkas dokumen tidak ditemukan pada penyimpanan.');
        }

        // Audit unduh dokumen (FR-151).
        $this->logger->log('download_document', $document, request()->user(), [
            'original_name' => $document->original_name,
        ], 'Dokumen diunduh.');

        return $disk->download($document->file_path, $document->original_name);
    }
}
