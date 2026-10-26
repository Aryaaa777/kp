<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\SubmissionTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submission\DecisionSubmissionRequest;
use App\Models\Submission;
use App\Services\AdminRegionScope;
use App\Services\Submissions\SubmissionModules;
use App\Services\SubmissionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
    public function __construct(
        private readonly SubmissionService $service,
        private readonly AdminRegionScope $regionScope,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Submission::class);

        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));
        $umurMin = $this->normalizeAgeFilter($request->query('umur_min'));
        $umurMax = $this->normalizeAgeFilter($request->query('umur_max'));
        $now = Carbon::now();

        $submissions = $this->regionScope
            ->applyToRelated(Submission::query(), $request->user())
            ->with(['cooperative', 'submitter'])
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($q !== '', function (Builder $query) use ($q): void {
                $pattern = '%'.$this->escapeLike($q).'%';
                $query->where(function (Builder $inner) use ($pattern): void {
                    $inner->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereHas('cooperative', function (Builder $cooperative) use ($pattern): void {
                            $cooperative->whereRaw("nama LIKE ? ESCAPE '!'", [$pattern])
                                ->orWhereRaw("nomor_identitas LIKE ? ESCAPE '!'", [$pattern]);
                        });
                });
            })
            // FR-021 "filter ... umur pengajuan": memakai `updated_at` — kolom waktu yang sudah
            // dipakai antrian (pengurutan & kolom "Diperbarui"). Ini penanda "umur sejak aktivitas
            // terakhir", bukan `created_at` (pengajuan bisa lama berstatus draft). Ambang berupa
            // angka hari dari pengguna (inklusif), bukan kategori bisnis tetap — FR-021 tidak
            // menetapkan ambangnya. Lihat docs/DECISIONS.md §16.
            ->when($umurMin !== null, fn (Builder $query) => $query->where('updated_at', '<=', $now->copy()->subDays($umurMin)))
            ->when($umurMax !== null, fn (Builder $query) => $query->where('updated_at', '>=', $now->copy()->subDays($umurMax)))
            ->orderByRaw("CASE status WHEN 'submitted' THEN 0 WHEN 'under_review' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.pengajuan.index', [
            'submissions' => $submissions,
            'filters' => [
                'status' => $status,
                'q' => $q,
                'umur_min' => $umurMin === null ? '' : (string) $umurMin,
                'umur_max' => $umurMax === null ? '' : (string) $umurMax,
            ],
            'statusOptions' => Submission::filterableStatusLabels(),
        ]);
    }

    /**
     * Normalisasi filter umur pengajuan (FR-021) menjadi jumlah hari `>= 0`.
     *
     * Mengembalikan `null` bila parameter kosong/tidak sah sehingga filter diabaikan — pola sama
     * dengan `Admin\CooperativeController::normalizeRegionFilter()`. `ctype_digit()` menolak angka
     * negatif, desimal, dan nilai non-numerik.
     */
    private function normalizeAgeFilter(mixed $value): ?int
    {
        $raw = trim((string) $value);

        if ($raw === '' || ! ctype_digit($raw)) {
            return null;
        }

        return (int) $raw;
    }

    public function show(Submission $submission): View
    {
        Gate::authorize('view', $submission);

        $submission->load(['cooperative', 'submitter', 'reviewer', 'items', 'reviews.user', 'parent', 'children']);

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

    /**
     * Escape karakter wildcard LIKE agar diperlakukan sebagai literal.
     * Urutan: `!` dulu, lalu `%` dan `_` dengan escape character `!`.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
