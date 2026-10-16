<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreCooperativeAssetRequest;
use App\Http\Requests\Asset\UpdateCooperativeAssetRequest;
use App\Models\Cooperative;
use App\Models\CooperativeAsset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD aset koperasi oleh admin dinas (FR-100/101).
 *
 * Route bersarang `admin/koperasi/{cooperative}/aset/{asset}` dengan scoped binding.
 *
 * PROVISIONAL — snapshot tahunan; tidak ada integrasi akuntansi. Lihat DECISIONS §10.
 */
class CooperativeAssetController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Cooperative $cooperative): View
    {
        $this->authorize('viewAny', CooperativeAsset::class);

        $year = $request->filled('period_year') ? (int) $request->input('period_year') : null;

        $assets = $cooperative->assets()
            ->when($year !== null, fn (Builder $query) => $query->where('period_year', $year))
            ->orderByDesc('period_year')
            ->orderBy('asset_type')
            ->paginate(15)
            ->withQueryString();

        return view('admin.koperasi.aset.index', [
            'cooperative' => $cooperative,
            'assets' => $assets,
            'yearFilter' => $year,
        ]);
    }

    public function create(Cooperative $cooperative): View
    {
        $this->authorize('create', CooperativeAsset::class);

        return view('admin.koperasi.aset.create', [
            'cooperative' => $cooperative,
            'typeOptions' => CooperativeAsset::typeOptions(),
            'conditionOptions' => CooperativeAsset::conditionOptions(),
        ]);
    }

    public function store(StoreCooperativeAssetRequest $request, Cooperative $cooperative): RedirectResponse
    {
        $this->authorize('create', CooperativeAsset::class);

        $asset = new CooperativeAsset($request->validated());
        $asset->cooperative_id = $cooperative->id;
        $asset->recorded_by = $request->user()->id;
        $asset->save();

        return redirect()
            ->route('admin.koperasi.aset.index', $cooperative)
            ->with('status', 'Aset berhasil ditambahkan.');
    }

    public function show(Cooperative $cooperative, CooperativeAsset $asset): View
    {
        $this->authorize('view', $asset);

        $asset->load('recorder');

        return view('admin.koperasi.aset.show', [
            'cooperative' => $cooperative,
            'asset' => $asset,
        ]);
    }

    public function edit(Cooperative $cooperative, CooperativeAsset $asset): View
    {
        $this->authorize('update', $asset);

        return view('admin.koperasi.aset.edit', [
            'cooperative' => $cooperative,
            'asset' => $asset,
            'typeOptions' => CooperativeAsset::typeOptions(),
            'conditionOptions' => CooperativeAsset::conditionOptions(),
        ]);
    }

    public function update(UpdateCooperativeAssetRequest $request, Cooperative $cooperative, CooperativeAsset $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $asset->fill($request->validated());
        $asset->save();

        return redirect()
            ->route('admin.koperasi.aset.index', $cooperative)
            ->with('status', 'Aset berhasil diperbarui.');
    }

    /**
     * Arsipkan aset (soft delete, BR-011). Data periode lain tidak terpengaruh (BR-007).
     */
    public function destroy(Cooperative $cooperative, CooperativeAsset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        $asset->delete();

        return redirect()
            ->route('admin.koperasi.aset.index', $cooperative)
            ->with('status', 'Aset telah diarsipkan.');
    }
}
