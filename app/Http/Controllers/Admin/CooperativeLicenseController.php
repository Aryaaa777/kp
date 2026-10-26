<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\License\StoreCooperativeLicenseRequest;
use App\Http\Requests\License\UpdateCooperativeLicenseRequest;
use App\Http\Requests\License\VerifyCooperativeLicenseRequest;
use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\Document;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * CRUD izin usaha & izin usaha simpan pinjam oleh admin dinas (FR-050/051/060/061).
 *
 * Route bersarang di bawah koperasi; route model binding di-scope sehingga izin koperasi lain
 * tidak dapat diakses lewat id yang ditebak (anti-IDOR, 404).
 */
class CooperativeLicenseController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(Cooperative $cooperative): View
    {
        Gate::authorize('viewAny', CooperativeLicense::class);

        $licenses = $cooperative->licenses()
            ->withCount('documents')
            ->orderBy('license_type')
            ->orderByDesc('tanggal_terbit')
            ->paginate(10);

        return view('admin.izin.index', [
            'cooperative' => $cooperative,
            'licenses' => $licenses,
        ]);
    }

    public function create(Cooperative $cooperative): View
    {
        Gate::authorize('create', CooperativeLicense::class);

        return view('admin.izin.create', [
            'cooperative' => $cooperative,
            'license' => null,
        ]);
    }

    public function store(StoreCooperativeLicenseRequest $request, Cooperative $cooperative): RedirectResponse
    {
        // Status awal `pending`; perubahan status hanya lewat aksi verifikasi (FR-051/061).
        $license = $cooperative->licenses()->create(
            $request->validated() + ['status' => 'pending'],
        );

        return redirect()
            ->route('admin.koperasi.izin.show', [$cooperative, $license])
            ->with('status', 'Izin berhasil ditambahkan.');
    }

    public function show(Cooperative $cooperative, CooperativeLicense $license): View
    {
        Gate::authorize('view', $license);

        $license->load([
            'verifier',
            'documents' => fn ($query) => $query
                ->with(['uploader', 'previousVersion'])
                ->orderBy('category')
                ->orderByDesc('version')
                ->orderByDesc('id'),
        ]);

        return view('admin.izin.show', [
            'cooperative' => $cooperative,
            'license' => $license,
            'categorySuggestions' => Document::CATEGORY_SUGGESTIONS,
            'verifyStatusOptions' => CooperativeLicense::verifiableStatusLabels(),
        ]);
    }

    public function edit(Cooperative $cooperative, CooperativeLicense $license): View
    {
        Gate::authorize('update', $license);

        return view('admin.izin.edit', [
            'cooperative' => $cooperative,
            'license' => $license,
        ]);
    }

    public function update(UpdateCooperativeLicenseRequest $request, Cooperative $cooperative, CooperativeLicense $license): RedirectResponse
    {
        // Status verifikasi tidak ikut diubah di sini.
        $license->update($request->validated());

        return redirect()
            ->route('admin.koperasi.izin.show', [$cooperative, $license])
            ->with('status', 'Data izin berhasil diperbarui.');
    }

    /**
     * Arsip izin (soft delete). Tidak ada penghapusan permanen (BR-011).
     */
    public function destroy(Cooperative $cooperative, CooperativeLicense $license): RedirectResponse
    {
        Gate::authorize('delete', $license);

        $license->delete();

        return redirect()
            ->route('admin.koperasi.izin.index', $cooperative)
            ->with('status', 'Izin telah diarsipkan.');
    }

    /**
     * Verifikasi izin (FR-051/061): menetapkan status hasil + catatan + jejak verifikator.
     */
    public function verify(VerifyCooperativeLicenseRequest $request, Cooperative $cooperative, CooperativeLicense $license): RedirectResponse
    {
        $data = $request->validated();
        $before = $license->status;

        $license->update([
            'status' => $data['status'],
            'catatan' => $data['catatan'] ?? $license->catatan,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        // Audit perubahan status verifikasi izin (FR-051, FR-151).
        $this->logger->log('verify_license', $license, $request->user(), [
            'before' => $before,
            'after' => $license->status,
        ], 'Status verifikasi izin diubah.');

        return redirect()
            ->route('admin.koperasi.izin.show', [$cooperative, $license])
            ->with('status', 'Status verifikasi izin berhasil disimpan.');
    }
}
