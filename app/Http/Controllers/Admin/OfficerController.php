<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Officer\StoreOfficerRequest;
use App\Http\Requests\Officer\UpdateOfficerRequest;
use App\Models\Cooperative;
use App\Models\Officer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller admin untuk mengelola data pengurus koperasi (officers).
 *
 * FR-120: CRUD susunan pengurus & periode jabatan.
 * FR-121: Status aktif/nonaktif pengurus.
 *
 * Route bersarang: /admin/koperasi/{cooperative}/pengurus/{officer}
 * Scoped binding: pengurus koperasi lain → 404 (anti-IDOR).
 *
 * Otorisasi: admin_dinas (penuh). Pengelola tidak dapat mengubah di F4 (akan via workflow F5).
 *
 * @see docs/PRD.md FR-120, FR-121
 * @see docs/ROADMAP_REMAINING.md F4
 */
class OfficerController extends Controller
{
    use AuthorizesRequests;

    /**
     * Tampilkan daftar pengurus koperasi.
     */
    public function index(Request $request, Cooperative $cooperative): View
    {
        $this->authorize('viewAny', Officer::class);

        $officers = $cooperative->officers()
            ->withTrashed()
            ->orderByDesc('is_active')
            ->orderBy('period_start', 'desc')
            ->paginate(15);

        return view('admin.koperasi.pengurus.index', [
            'cooperative' => $cooperative,
            'officers' => $officers,
        ]);
    }

    /**
     * Tampilkan form tambah pengurus baru.
     */
    public function create(Cooperative $cooperative): View
    {
        $this->authorize('create', Officer::class);

        return view('admin.koperasi.pengurus.create', [
            'cooperative' => $cooperative,
        ]);
    }

    /**
     * Simpan pengurus baru ke database.
     */
    public function store(StoreOfficerRequest $request, Cooperative $cooperative): RedirectResponse
    {
        $this->authorize('create', Officer::class);

        $officer = new Officer($request->validated());
        $officer->cooperative_id = $cooperative->id;
        $officer->is_active = $request->boolean('is_active', true);
        $officer->save();

        return redirect()
            ->route('admin.koperasi.pengurus.index', $cooperative)
            ->with('success', "Pengurus \"{$officer->name}\" berhasil ditambahkan.");
    }

    /**
     * Tampilkan detail pengurus.
     */
    public function show(Cooperative $cooperative, Officer $officer): View
    {
        $this->authorize('view', $officer);

        return view('admin.koperasi.pengurus.show', [
            'cooperative' => $cooperative,
            'officer' => $officer,
        ]);
    }

    /**
     * Tampilkan form ubah pengurus.
     */
    public function edit(Cooperative $cooperative, Officer $officer): View
    {
        $this->authorize('update', $officer);

        return view('admin.koperasi.pengurus.edit', [
            'cooperative' => $cooperative,
            'officer' => $officer,
        ]);
    }

    /**
     * Perbarui data pengurus di database.
     */
    public function update(UpdateOfficerRequest $request, Cooperative $cooperative, Officer $officer): RedirectResponse
    {
        $this->authorize('update', $officer);

        $validated = $request->validated();

        // Handle identity_number: jika kosong/null, pertahankan NIK lama (BR-012)
        if (array_key_exists('identity_number', $validated) && empty($validated['identity_number'])) {
            unset($validated['identity_number']);
        }

        $officer->fill($validated);

        // Handle checkbox is_active (unchecked = false, checked = true)
        if ($request->has('is_active')) {
            $officer->is_active = $request->boolean('is_active');
        }

        $officer->save();

        return redirect()
            ->route('admin.koperasi.pengurus.index', $cooperative)
            ->with('success', "Pengurus \"{$officer->name}\" berhasil diperbarui.");
    }

    /**
     * Hapus (soft delete) pengurus dari database.
     *
     * Soft delete (BR-011): data tidak dihapus permanen, tetapi disembunyikan.
     * Riwayat jabatan tetap terjaga.
     */
    public function destroy(Cooperative $cooperative, Officer $officer): RedirectResponse
    {
        $this->authorize('delete', $officer);

        $name = $officer->name;
        $officer->delete();

        return redirect()
            ->route('admin.koperasi.pengurus.index', $cooperative)
            ->with('success', "Pengurus \"{$name}\" berhasil diarsipkan.");
    }
}
