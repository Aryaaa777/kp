<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Health\StoreHealthAssessmentRequest;
use App\Http\Requests\Health\UpdateHealthAssessmentRequest;
use App\Models\Cooperative;
use App\Models\HealthAssessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD penilaian kesehatan koperasi oleh admin dinas (FR-080/081).
 *
 * Route bersarang `admin/koperasi/{cooperative}/kesehatan/{healthAssessment}` dengan scoped binding
 * → catatan koperasi lain → 404 (anti-IDOR). Penulisan hanya admin (BR-010).
 *
 * Catatan terarsip (soft delete) tidak dapat diakses endpoint biasa; pemulihan admin memakai
 * route `.../pulihkan` dengan `withTrashed()` (lihat `restore()`).
 *
 * PROVISIONAL — periode tahunan; skor/predikat manual (tanpa formula resmi). Lihat DECISIONS §10.
 */
class HealthAssessmentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Cooperative $cooperative): View
    {
        $this->authorize('viewAny', HealthAssessment::class);

        $year = $request->filled('period_year') ? (int) $request->input('period_year') : null;
        $showArchived = $request->boolean('arsip');

        $assessments = $cooperative->healthAssessments()
            ->when($showArchived, fn (Builder $query) => $query->onlyTrashed())
            ->when($year !== null, fn (Builder $query) => $query->where('period_year', $year))
            ->orderByDesc('period_year')
            ->orderByDesc('assessment_date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.koperasi.kesehatan.index', [
            'cooperative' => $cooperative,
            'assessments' => $assessments,
            'yearFilter' => $year,
            'showArchived' => $showArchived,
            'periodOptions' => HealthAssessment::periodOptions(),
        ]);
    }

    public function create(Cooperative $cooperative): View
    {
        $this->authorize('create', HealthAssessment::class);

        return view('admin.koperasi.kesehatan.create', [
            'cooperative' => $cooperative,
            'periodOptions' => HealthAssessment::periodOptions(),
            'statusOptions' => HealthAssessment::statusOptions(),
        ]);
    }

    public function store(StoreHealthAssessmentRequest $request, Cooperative $cooperative): RedirectResponse
    {
        $this->authorize('create', HealthAssessment::class);

        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'draft';

        $assessment = new HealthAssessment($data);
        $assessment->cooperative_id = $cooperative->id;
        $assessment->assessor_id = $request->user()->id;
        $assessment->save();

        return redirect()
            ->route('admin.koperasi.kesehatan.index', $cooperative)
            ->with('status', 'Catatan kesehatan berhasil ditambahkan.');
    }

    public function show(Cooperative $cooperative, HealthAssessment $healthAssessment): View
    {
        $this->authorize('view', $healthAssessment);

        $healthAssessment->load('assessor');

        return view('admin.koperasi.kesehatan.show', [
            'cooperative' => $cooperative,
            'assessment' => $healthAssessment,
        ]);
    }

    public function edit(Cooperative $cooperative, HealthAssessment $healthAssessment): View
    {
        $this->authorize('update', $healthAssessment);

        return view('admin.koperasi.kesehatan.edit', [
            'cooperative' => $cooperative,
            'assessment' => $healthAssessment,
            'periodOptions' => HealthAssessment::periodOptions(),
            'statusOptions' => HealthAssessment::statusOptions(),
        ]);
    }

    public function update(UpdateHealthAssessmentRequest $request, Cooperative $cooperative, HealthAssessment $healthAssessment): RedirectResponse
    {
        $this->authorize('update', $healthAssessment);

        $healthAssessment->fill($request->validated());
        $healthAssessment->save();

        return redirect()
            ->route('admin.koperasi.kesehatan.index', $cooperative)
            ->with('status', 'Catatan kesehatan berhasil diperbarui.');
    }

    /**
     * Arsipkan catatan kesehatan (soft delete, BR-011).
     */
    public function destroy(Cooperative $cooperative, HealthAssessment $healthAssessment): RedirectResponse
    {
        $this->authorize('delete', $healthAssessment);

        $healthAssessment->delete();

        return redirect()
            ->route('admin.koperasi.kesehatan.index', $cooperative)
            ->with('status', 'Catatan kesehatan telah diarsipkan.');
    }

    /**
     * Pulihkan catatan kesehatan terarsip (admin berwenang).
     *
     * Route memakai `withTrashed()` + scoped binding sehingga hanya record milik `{cooperative}`
     * yang dapat dipulihkan (record koperasi lain → 404). Otorisasi diperiksa Policy (`restore`).
     */
    public function restore(Cooperative $cooperative, HealthAssessment $healthAssessment): RedirectResponse
    {
        $this->authorize('restore', $healthAssessment);

        $healthAssessment->restore();

        return redirect()
            ->route('admin.koperasi.kesehatan.index', $cooperative)
            ->with('status', 'Catatan kesehatan berhasil dipulihkan.');
    }
}
