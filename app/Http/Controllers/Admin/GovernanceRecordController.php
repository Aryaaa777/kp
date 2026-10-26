<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Governance\StoreGovernanceRecordRequest;
use App\Http\Requests\Governance\UpdateGovernanceRecordRequest;
use App\Models\Cooperative;
use App\Models\GovernanceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD data tata kelola koperasi oleh admin dinas (FR-090).
 *
 * Route bersarang `admin/koperasi/{cooperative}/tata-kelola/{governanceRecord}` dengan scoped binding.
 *
 * Catatan terarsip (soft delete) tidak dapat diakses endpoint biasa; pemulihan admin memakai
 * route `.../pulihkan` dengan `withTrashed()` (lihat `restore()`).
 *
 * PROVISIONAL — periode tahunan; indikator resmi belum ada. Checklist kepatuhan
 * (FR-091) dievaluasi server-side; bukan kriteria resmi dinas (Q-007). Lihat
 * DECISIONS §10 dan §33.
 */
class GovernanceRecordController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Cooperative $cooperative): View
    {
        $this->authorize('viewAny', GovernanceRecord::class);

        $year = $request->filled('period_year') ? (int) $request->input('period_year') : null;
        $showArchived = $request->boolean('arsip');

        $records = $cooperative->governanceRecords()
            ->when($showArchived, fn (Builder $query) => $query->onlyTrashed())
            ->when($year !== null, fn (Builder $query) => $query->where('period_year', $year))
            ->orderByDesc('period_year')
            ->paginate(15)
            ->withQueryString();

        return view('admin.koperasi.tata-kelola.index', [
            'cooperative' => $cooperative,
            'records' => $records,
            'yearFilter' => $year,
            'showArchived' => $showArchived,
            'periodOptions' => GovernanceRecord::periodOptions(),
        ]);
    }

    public function create(Cooperative $cooperative): View
    {
        $this->authorize('create', GovernanceRecord::class);

        return view('admin.koperasi.tata-kelola.create', [
            'cooperative' => $cooperative,
            'periodOptions' => GovernanceRecord::periodOptions(),
            'statusOptions' => GovernanceRecord::statusOptions(),
        ]);
    }

    public function store(StoreGovernanceRecordRequest $request, Cooperative $cooperative): RedirectResponse
    {
        $this->authorize('create', GovernanceRecord::class);

        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'draft';

        foreach (['rat_on_time', 'financial_report_available', 'basic_documents_complete'] as $checklist) {
            if ($request->has($checklist)) {
                $data[$checklist] = $request->boolean($checklist);
            }
        }

        $record = new GovernanceRecord($data);
        $record->cooperative_id = $cooperative->id;
        $record->recorded_by = $request->user()->id;
        $record->evaluateCompliance();

        if ($record->requiresComplianceNotes()) {
            return back()->withInput()->withErrors([
                'compliance_notes' => 'Catatan wajib diisi bila hasil evaluasi kepatuhan adalah Deviasi.',
            ]);
        }

        $record->save();

        return redirect()
            ->route('admin.koperasi.tata-kelola.index', $cooperative)
            ->with('status', 'Catatan tata kelola berhasil ditambahkan.');
    }

    public function show(Cooperative $cooperative, GovernanceRecord $governanceRecord): View
    {
        $this->authorize('view', $governanceRecord);

        $governanceRecord->load('recorder');

        return view('admin.koperasi.tata-kelola.show', [
            'cooperative' => $cooperative,
            'record' => $governanceRecord,
        ]);
    }

    public function edit(Cooperative $cooperative, GovernanceRecord $governanceRecord): View
    {
        $this->authorize('update', $governanceRecord);

        return view('admin.koperasi.tata-kelola.edit', [
            'cooperative' => $cooperative,
            'record' => $governanceRecord,
            'periodOptions' => GovernanceRecord::periodOptions(),
            'statusOptions' => GovernanceRecord::statusOptions(),
        ]);
    }

    public function update(UpdateGovernanceRecordRequest $request, Cooperative $cooperative, GovernanceRecord $governanceRecord): RedirectResponse
    {
        $this->authorize('update', $governanceRecord);

        $data = $request->validated();

        foreach (['rat_on_time', 'financial_report_available', 'basic_documents_complete'] as $checklist) {
            if ($request->has($checklist)) {
                $data[$checklist] = $request->boolean($checklist);
            }
        }

        $governanceRecord->fill($data);
        $governanceRecord->evaluateCompliance();

        if ($governanceRecord->requiresComplianceNotes()) {
            return back()->withInput()->withErrors([
                'compliance_notes' => 'Catatan wajib diisi bila hasil evaluasi kepatuhan adalah Deviasi.',
            ]);
        }

        $governanceRecord->save();

        return redirect()
            ->route('admin.koperasi.tata-kelola.index', $cooperative)
            ->with('status', 'Catatan tata kelola berhasil diperbarui.');
    }

    /**
     * Arsipkan catatan tata kelola (soft delete, BR-011).
     */
    public function destroy(Cooperative $cooperative, GovernanceRecord $governanceRecord): RedirectResponse
    {
        $this->authorize('delete', $governanceRecord);

        $governanceRecord->delete();

        return redirect()
            ->route('admin.koperasi.tata-kelola.index', $cooperative)
            ->with('status', 'Catatan tata kelola telah diarsipkan.');
    }

    /**
     * Pulihkan catatan tata kelola terarsip (admin berwenang).
     *
     * Route memakai `withTrashed()` + scoped binding sehingga hanya record milik `{cooperative}`
     * yang dapat dipulihkan (record koperasi lain → 404). Otorisasi diperiksa Policy (`restore`).
     */
    public function restore(Cooperative $cooperative, GovernanceRecord $governanceRecord): RedirectResponse
    {
        $this->authorize('restore', $governanceRecord);

        $governanceRecord->restore();

        return redirect()
            ->route('admin.koperasi.tata-kelola.index', $cooperative)
            ->with('status', 'Catatan tata kelola berhasil dipulihkan.');
    }
}
