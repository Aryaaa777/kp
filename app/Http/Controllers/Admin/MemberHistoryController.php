<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StoreMemberHistoryRequest;
use App\Http\Requests\Member\UpdateMemberHistoryRequest;
use App\Models\Cooperative;
use App\Models\MemberHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD riwayat keanggotaan koperasi oleh admin dinas (FR-110/111).
 *
 * Route bersarang `admin/koperasi/{cooperative}/anggota/{memberHistory}` dengan scoped binding.
 *
 * PROVISIONAL — pencatatan per kejadian/perubahan (bukan rekap tahunan). Lihat DECISIONS §10.
 * Tidak mengumpulkan data anggota individual/NIK.
 */
class MemberHistoryController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Cooperative $cooperative): View
    {
        $this->authorize('viewAny', MemberHistory::class);

        $changeType = (string) $request->query('change_type', '');

        $histories = $cooperative->memberHistories()
            ->when($changeType !== '', fn (Builder $query) => $query->where('change_type', $changeType))
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.koperasi.anggota.index', [
            'cooperative' => $cooperative,
            'histories' => $histories,
            'changeTypeFilter' => $changeType,
            'changeTypeOptions' => MemberHistory::changeTypeOptions(),
        ]);
    }

    public function create(Cooperative $cooperative): View
    {
        $this->authorize('create', MemberHistory::class);

        return view('admin.koperasi.anggota.create', [
            'cooperative' => $cooperative,
            'changeTypeOptions' => MemberHistory::changeTypeOptions(),
        ]);
    }

    public function store(StoreMemberHistoryRequest $request, Cooperative $cooperative): RedirectResponse
    {
        $this->authorize('create', MemberHistory::class);

        $history = new MemberHistory($request->validated());
        $history->cooperative_id = $cooperative->id;
        $history->recorded_by = $request->user()->id;
        $history->save();

        return redirect()
            ->route('admin.koperasi.anggota.index', $cooperative)
            ->with('status', 'Catatan keanggotaan berhasil ditambahkan.');
    }

    public function show(Cooperative $cooperative, MemberHistory $memberHistory): View
    {
        $this->authorize('view', $memberHistory);

        $memberHistory->load('recorder');

        return view('admin.koperasi.anggota.show', [
            'cooperative' => $cooperative,
            'history' => $memberHistory,
        ]);
    }

    public function edit(Cooperative $cooperative, MemberHistory $memberHistory): View
    {
        $this->authorize('update', $memberHistory);

        return view('admin.koperasi.anggota.edit', [
            'cooperative' => $cooperative,
            'history' => $memberHistory,
            'changeTypeOptions' => MemberHistory::changeTypeOptions(),
        ]);
    }

    public function update(UpdateMemberHistoryRequest $request, Cooperative $cooperative, MemberHistory $memberHistory): RedirectResponse
    {
        $this->authorize('update', $memberHistory);

        $memberHistory->fill($request->validated());
        $memberHistory->save();

        return redirect()
            ->route('admin.koperasi.anggota.index', $cooperative)
            ->with('status', 'Catatan keanggotaan berhasil diperbarui.');
    }

    /**
     * Arsipkan catatan keanggotaan (soft delete, BR-011).
     */
    public function destroy(Cooperative $cooperative, MemberHistory $memberHistory): RedirectResponse
    {
        $this->authorize('delete', $memberHistory);

        $memberHistory->delete();

        return redirect()
            ->route('admin.koperasi.anggota.index', $cooperative)
            ->with('status', 'Catatan keanggotaan telah diarsipkan.');
    }
}
