<?php

namespace App\Http\Controllers\Pengelola;

use App\Exceptions\EmptySubmissionException;
use App\Exceptions\SubmissionTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submission\StoreSubmissionRequest;
use App\Http\Requests\Submission\UpdateSubmissionRequest;
use App\Models\Cooperative;
use App\Models\Submission;
use App\Services\Submissions\SubmissionModules;
use App\Services\SubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pengajuan perubahan data oleh pengelola koperasi (FR-140…143, sisi pengelola).
 *
 * Pengelola hanya mengakses pengajuan **koperasinya sendiri** (Policy ownership). Perubahan
 * baru diterapkan ke data resmi setelah disetujui admin (BR-014, PRD §12.3).
 *
 * PROVISIONAL — Q-006 (whitelist field) & Q-004 (prosedur verifikasi) belum dikonfirmasi.
 *
 * @see docs/DECISIONS.md §12 (keputusan F5)
 */
class SubmissionController extends Controller
{
    public function __construct(private readonly SubmissionService $service) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Submission::class);

        $user = $request->user();

        if ($user->koperasi_id === null) {
            abort(403, 'Akun Anda belum terhubung ke koperasi. Hubungi admin dinas.');
        }

        $status = (string) $request->query('status', '');

        $submissions = Submission::query()
            ->where('cooperative_id', $user->koperasi_id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('pengelola.pengajuan.index', [
            'submissions' => $submissions,
            'filters' => ['status' => $status],
            'statusOptions' => Submission::filterableStatusLabels(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Submission::class);

        $user = $request->user();
        $cooperative = $user->koperasi;

        if ($cooperative === null) {
            abort(403, 'Akun Anda belum terhubung ke koperasi. Hubungi admin dinas.');
        }

        $module = (string) $request->query('module', SubmissionModules::defaultModule() ?? '');

        if (! SubmissionModules::has($module)) {
            $module = SubmissionModules::defaultModule() ?? '';
        }

        return view('pengelola.pengajuan.create', [
            'cooperative' => $cooperative,
            'module' => $module,
            'modules' => SubmissionModules::labels(),
            'fields' => SubmissionModules::fields($module),
            'values' => $module !== '' ? $this->service->officialValues($cooperative, $module) : [],
        ]);
    }

    public function store(StoreSubmissionRequest $request): RedirectResponse
    {
        $user = $request->user();
        $cooperative = $user->koperasi;

        if ($cooperative === null) {
            abort(403, 'Akun Anda belum terhubung ke koperasi. Hubungi admin dinas.');
        }

        $module = (string) $request->validated('module');
        $fields = SubmissionModules::fields($module);

        try {
            $submission = $this->service->createDraft(
                $user,
                $cooperative,
                $module,
                $request->safe()->only(array_keys($fields)),
                $request->validated('title'),
            );
        } catch (EmptySubmissionException $exception) {
            return back()->withInput()->withErrors(['module' => $exception->getMessage()]);
        }

        return redirect()
            ->route('pengelola.pengajuan.show', $submission)
            ->with('status', 'Draft pengajuan disimpan.');
    }

    public function show(Submission $submission): View
    {
        Gate::authorize('view', $submission);

        $submission->load(['cooperative', 'reviewer', 'items', 'reviews.user']);

        return view('pengelola.pengajuan.show', [
            'submission' => $submission,
            'fields' => SubmissionModules::fields($submission->module),
        ]);
    }

    public function edit(Submission $submission): View
    {
        Gate::authorize('update', $submission);

        // L3: koperasi target mungkin tidak tersedia (mis. ter-soft delete). Jangan biarkan
        // TypeError menjadi HTTP 500; beri respons aman.
        $cooperative = $submission->cooperative;

        if (! $cooperative instanceof Cooperative) {
            abort(404, 'Koperasi terkait pengajuan tidak tersedia.');
        }

        $official = $this->service->officialValues($cooperative, $submission->module);
        $proposed = $this->service->proposedValues($submission);

        return view('pengelola.pengajuan.edit', [
            'submission' => $submission,
            'module' => $submission->module,
            'modules' => SubmissionModules::labels(),
            'fields' => SubmissionModules::fields($submission->module),
            'values' => array_merge($official, $proposed),
        ]);
    }

    public function update(UpdateSubmissionRequest $request, Submission $submission): RedirectResponse
    {
        $fields = SubmissionModules::fields($submission->module);

        try {
            $this->service->updateDraft(
                $submission,
                $request->user(),
                $request->safe()->only(array_keys($fields)),
                $request->validated('title'),
            );
        } catch (EmptySubmissionException|SubmissionTransitionException $exception) {
            return back()->withInput()->withErrors(['module' => $exception->getMessage()]);
        }

        return redirect()
            ->route('pengelola.pengajuan.show', $submission)
            ->with('status', 'Draft pengajuan diperbarui.');
    }

    public function submit(Submission $submission): RedirectResponse
    {
        Gate::authorize('submit', $submission);

        try {
            $this->service->submit($submission, request()->user());
        } catch (EmptySubmissionException|SubmissionTransitionException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('pengelola.pengajuan.show', $submission)
            ->with('status', 'Pengajuan telah dikirim untuk diverifikasi.');
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
            ->route('pengelola.pengajuan.index')
            ->with('status', 'Pengajuan telah diarsipkan.');
    }
}
