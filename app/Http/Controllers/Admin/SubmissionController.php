<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\SubmissionTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submission\DecisionSubmissionRequest;
use App\Models\Submission;
use App\Services\Submissions\SubmissionModules;
use App\Services\SubmissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Antrian verifikasi & keputusan pengajuan oleh admin dinas (FR-141/142/143).
 *
 * Admin melihat semua pengajuan (kewenangan dinas). Aksi keputusan diverifikasi di server
 * melalui `SubmissionPolicy` + penjaga matriks transisi di `SubmissionService` (PRD §12.2).
 *
 * PROVISIONAL — verifikasi **satu tingkat** (Q-004); whitelist modul/field Q-006.
 *
 * @see docs/DECISIONS.md §12 (keputusan F5)
 */
class SubmissionController extends Controller
{
    public function __construct(private readonly SubmissionService $service) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Submission::class);

        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $submissions = Submission::query()
            ->with(['cooperative', 'submitter'])
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($q !== '', function (Builder $query) use ($q): void {
                $query->where(function (Builder $inner) use ($q): void {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhereHas('cooperative', function (Builder $cooperative) use ($q): void {
                            $cooperative->where('nama', 'like', "%{$q}%")
                                ->orWhere('nomor_identitas', 'like', "%{$q}%");
                        });
                });
            })
            ->orderByRaw("CASE status WHEN 'submitted' THEN 0 WHEN 'under_review' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.pengajuan.index', [
            'submissions' => $submissions,
            'filters' => ['status' => $status, 'q' => $q],
            'statusOptions' => Submission::filterableStatusLabels(),
        ]);
    }

    public function show(Submission $submission): View
    {
        Gate::authorize('view', $submission);

        $submission->load(['cooperative', 'submitter', 'reviewer', 'items', 'reviews.user']);

        return view('admin.pengajuan.show', [
            'submission' => $submission,
            'fields' => SubmissionModules::fields($submission->module),
        ]);
    }

    /**
     * Admin mengambil pengajuan untuk ditinjau (`submitted` → `under_review`).
     */
    public function startReview(Submission $submission): RedirectResponse
    {
        Gate::authorize('review', $submission);

        try {
            $this->service->startReview($submission, request()->user());
        } catch (SubmissionTransitionException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.pengajuan.show', $submission)
            ->with('status', 'Pengajuan ditandai sedang ditinjau.');
    }

    /**
     * Putuskan pengajuan: setujui / perlu perbaikan / tolak (FR-141/142/143).
     */
    public function decide(DecisionSubmissionRequest $request, Submission $submission): RedirectResponse
    {
        try {
            match ($request->decision()) {
                'approve' => $this->service->approve($submission, $request->user()),
                'needs_revision' => $this->service->requestRevision($submission, $request->user(), (string) $request->reason()),
                'rejected' => $this->service->reject($submission, $request->user(), (string) $request->reason()),
                default => throw SubmissionTransitionException::invalid($submission, 'unknown'),
            };
        } catch (SubmissionTransitionException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.pengajuan.show', $submission)
            ->with('status', 'Keputusan pengajuan berhasil disimpan.');
    }

    public function archive(Submission $submission): RedirectResponse
    {
        Gate::authorize('archive', $submission);

        try {
            $this->service->archive($submission, request()->user());
        } catch (SubmissionTransitionException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.pengajuan.index')
            ->with('status', 'Pengajuan telah diarsipkan.');
    }
}
