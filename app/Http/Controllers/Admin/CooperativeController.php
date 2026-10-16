<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cooperative\ArchiveCooperativeRequest;
use App\Http\Requests\Cooperative\StoreCooperativeRequest;
use App\Http\Requests\Cooperative\UpdateCooperativeRequest;
use App\Models\Cooperative;
use App\Models\Region;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * CRUD data induk koperasi (FR-040, FR-043) + pencarian/filter/pagination (FR-160).
 */
class CooperativeController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * Kolom yang boleh dipakai untuk pengurutan (whitelist — FR-160).
     *
     * @var list<string>
     */
    public const SORTABLE = ['nama', 'nomor_identitas', 'jenis_koperasi', 'status_verifikasi', 'created_at'];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Cooperative::class);

        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status_verifikasi', '');
        $jenis = (string) $request->query('jenis_koperasi', '');
        $sort = (string) $request->query('sort', 'nama');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'nama';
        }

        $cooperatives = Cooperative::query()
            ->when($q !== '', function (Builder $query) use ($q): void {
                $query->where(function (Builder $inner) use ($q): void {
                    $inner->where('nama', 'like', "%{$q}%")
                        ->orWhere('nomor_identitas', 'like', "%{$q}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status_verifikasi', $status))
            ->when($jenis !== '', fn (Builder $query) => $query->where('jenis_koperasi', $jenis))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return view('admin.koperasi.index', [
            'cooperatives' => $cooperatives,
            'filters' => [
                'q' => $q,
                'status_verifikasi' => $status,
                'jenis_koperasi' => $jenis,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusOptions' => Cooperative::assignableStatusLabels(),
            'jenisOptions' => Cooperative::query()
                ->select('jenis_koperasi')
                ->distinct()
                ->orderBy('jenis_koperasi')
                ->pluck('jenis_koperasi'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Cooperative::class);

        return view('admin.koperasi.create', $this->formData());
    }

    public function store(StoreCooperativeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $cooperative = Cooperative::create($data);

        // Audit trail (FR-040/F2-7 dipenuhi pada F5): perubahan data induk dicatat.
        $this->logger->log('create_cooperative', $cooperative, $request->user(), [
            'nama' => $cooperative->nama,
            'nomor_identitas' => $cooperative->nomor_identitas,
        ], 'Data koperasi dibuat.');

        return redirect()
            ->route('admin.koperasi.show', $cooperative)
            ->with('status', 'Data koperasi berhasil ditambahkan.');
    }

    public function show(Cooperative $cooperative): View
    {
        Gate::authorize('view', $cooperative);

        $cooperative->load(['region', 'creator', 'updater'])->loadCount('licenses');

        return view('admin.koperasi.show', [
            'cooperative' => $cooperative,
        ]);
    }

    public function edit(Cooperative $cooperative): View
    {
        Gate::authorize('update', $cooperative);

        return view('admin.koperasi.edit', array_merge(
            ['cooperative' => $cooperative],
            $this->formData(),
        ));
    }

    public function update(UpdateCooperativeRequest $request, Cooperative $cooperative): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        // Nilai lama untuk audit (FR-040/F2-7, FR-150).
        $auditedFields = [
            'nama', 'nomor_identitas', 'jenis_koperasi', 'bentuk_koperasi',
            'tanggal_berdiri', 'alamat', 'telephone', 'email', 'website',
        ];
        $before = $cooperative->only($auditedFields);

        $cooperative->update($data);

        $this->logger->log('update_cooperative', $cooperative, $request->user(), [
            'before' => $before,
            'after' => $cooperative->only($auditedFields),
        ], 'Data koperasi diperbarui.');

        return redirect()
            ->route('admin.koperasi.show', $cooperative)
            ->with('status', 'Data koperasi berhasil diperbarui.');
    }

    /**
     * Arsip/nonaktifkan koperasi (FR-043) — soft delete, wajib alasan.
     */
    public function destroy(ArchiveCooperativeRequest $request, Cooperative $cooperative): RedirectResponse
    {
        $cooperative->update([
            'status_verifikasi' => 'archived',
            'archived_reason' => $request->validated()['archived_reason'],
            'updated_by' => $request->user()->id,
        ]);

        $cooperative->delete();

        $this->logger->log('archive_cooperative', $cooperative, $request->user(), [
            'reason' => $request->validated()['archived_reason'],
        ], 'Data koperasi diarsipkan.');

        return redirect()
            ->route('admin.koperasi.index')
            ->with('status', 'Data koperasi telah diarsipkan.');
    }

    /**
     * @return array{regions: Collection<int, Region>, jenisOptions: array<int, string>, statusOptions: array<string, string>}
     */
    private function formData(): array
    {
        return [
            'regions' => Region::query()->orderBy('name')->get(),
            'jenisOptions' => config('koperasi.jenis_koperasi_options', []),
            'statusOptions' => Cooperative::assignableStatusLabels(),
        ];
    }
}
