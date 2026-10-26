<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cooperative\ArchiveCooperativeRequest;
use App\Http\Requests\Cooperative\StoreCooperativeRequest;
use App\Http\Requests\Cooperative\UpdateCooperativeRequest;
use App\Models\Cooperative;
use App\Models\Region;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AdminRegionScope;
use App\Services\Reporting\CooperativeFilters;
use App\Services\Reporting\PeriodExpression;
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
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly AdminRegionScope $regionScope,
    ) {}

    /**
     * Kolom yang boleh dipakai untuk pengurutan (whitelist — FR-160).
     *
     * @var list<string>
     */
    public const SORTABLE = ['nama', 'nomor_identitas', 'jenis_koperasi', 'status_verifikasi', 'created_at'];

    /**
     * Nilai filter wilayah untuk koperasi tanpa wilayah (`region_id` NULL) — F6-4.
     */
    public const REGION_NONE = CooperativeFilters::REGION_NONE;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Cooperative::class);

        $q = trim((string) $request->query('q', ''));
        $status = CooperativeFilters::normalizeStatus($request->query('status_verifikasi'));
        $jenis = (string) $request->query('jenis_koperasi', '');
        $region = $this->normalizeRegionFilter($request->query('region_id'));
        $tahun = CooperativeFilters::normalize(['tahun' => $request->query('tahun')])['tahun'];
        $sort = (string) $request->query('sort', 'nama');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'nama';
        }

        $query = $this->regionScope->applyToCooperatives(Cooperative::query(), $request->user());

        $cooperatives = $query
            ->when($q !== '', function (Builder $query) use ($q): void {
                $pattern = '%'.$this->escapeLike($q).'%';
                $query->where(function (Builder $inner) use ($pattern): void {
                    $inner->whereRaw("nama LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("nomor_identitas LIKE ? ESCAPE '!'", [$pattern]);
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('status_verifikasi', $status))
            ->when($jenis !== '', fn (Builder $query) => $query->where('jenis_koperasi', $jenis))
            ->when($region === self::REGION_NONE, fn (Builder $query) => $query->whereNull('region_id'))
            ->when(
                $region !== '' && $region !== self::REGION_NONE,
                fn (Builder $query) => $query->whereIn(
                    'region_id',
                    Region::descendantIdsOf((int) $region),
                ),
            )
            ->when($tahun !== '', fn (Builder $query) => $query->whereRaw(
                PeriodExpression::year('cooperatives.created_at').' = ?',
                [(int) $tahun],
            ))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return view('admin.koperasi.index', [
            'cooperatives' => $cooperatives,
            'filters' => [
                'q' => $q,
                'status_verifikasi' => $status,
                'jenis_koperasi' => $jenis,
                'region_id' => $region,
                'tahun' => $tahun,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'regions' => Region::query()
                ->whereIn('type', ['regency', 'city'])
                ->orderBy('name')
                ->get(),
            'yearOptions' => $this->availableYears($request->user()),
            'statusOptions' => Cooperative::assignableStatusLabels(),
            'jenisOptions' => $this->regionScope
                ->applyToCooperatives(Cooperative::query(), $request->user())
                ->select('jenis_koperasi')
                ->distinct()
                ->orderBy('jenis_koperasi')
                ->pluck('jenis_koperasi'),
        ]);
    }

    /**
     * Normalisasi filter wilayah (FR-160): `''` (tanpa filter), `none` (tanpa wilayah),
     * atau ID wilayah yang benar-benar ada. Nilai lain diabaikan secara aman.
     */
    private function normalizeRegionFilter(mixed $value): string
    {
        $region = trim((string) $value);

        if ($region === '' || $region === self::REGION_NONE) {
            return $region;
        }

        if (! ctype_digit($region) || ! Region::query()->whereKey((int) $region)->exists()) {
            return '';
        }

        return $region;
    }

    /**
     * Tahun pendaftaran yang tersedia untuk filter periode (definisi sementara F6-3).
     *
     * Opsi dihitung dari seluruh status agar pilihan tidak mengecil saat filter lain aktif;
     * definisi resmi tetap menunggu Q-010.
     *
     * @return list<int>
     */
    private function availableYears(?User $user = null): array
    {
        $year = PeriodExpression::year('cooperatives.created_at');

        return $this->regionScope
            ->applyToCooperatives(Cooperative::query(), $user)
            ->selectRaw("{$year} as tahun")
            ->groupByRaw($year)
            ->orderByRaw($year)
            ->get()
            ->map(fn (Cooperative $row): int => (int) $row->tahun)
            ->unique()
            ->values()
            ->all();
    }

    public function create(): View
    {
        Gate::authorize('create', Cooperative::class);

        return view('admin.koperasi.create', $this->formData());
    }

    public function store(StoreCooperativeRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['province_id'], $data['regency_id'], $data['district_id']);
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

        $cooperative->load(['region', 'creator', 'updater', 'publisher', 'unpublisher'])->loadCount('licenses');

        return view('admin.koperasi.show', [
            'cooperative' => $cooperative,
            // Alasan koperasi belum dapat dipublikasikan (`null` = memenuhi syarat);
            // view memakainya agar tidak menampilkan tombol yang pasti gagal (FR-190).
            'publicationBlocker' => $this->publicationBlocker($cooperative),
        ]);
    }

    public function edit(Cooperative $cooperative): View
    {
        Gate::authorize('update', $cooperative);

        $cooperative->loadMissing('region.parent.parent.parent');

        return view('admin.koperasi.edit', array_merge(
            ['cooperative' => $cooperative],
            $this->formData(),
        ));
    }

    public function update(UpdateCooperativeRequest $request, Cooperative $cooperative): RedirectResponse
    {
        $data = $request->validated();
        unset($data['province_id'], $data['regency_id'], $data['district_id']);
        $data['updated_by'] = $request->user()->id;

        // Nilai lama untuk audit (FR-040/F2-7, FR-150).
        $auditedFields = [
            'nama', 'nomor_identitas', 'jenis_koperasi', 'bentuk_koperasi',
            'tanggal_berdiri', 'alamat', 'telephone', 'email', 'website',
            'region_id', 'status_verifikasi', 'status_operasional',
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
     * Terbitkan koperasi ke direktori publik (FR-190).
     *
     * Kelayakan memakai **aturan yang sama** dengan direktori publik
     * (`config('koperasi.public.visible_statuses')`) — lihat `publicationBlocker()`.
     * `published_at`/`published_by` diisi sistem, **bukan** dari input pengguna; status
     * verifikasi tidak diubah.
     *
     * D-6: publikasi ulang setelah penarikan menstempel siklus publikasi baru, tetapi
     * **tidak mengosongkan** `unpublished_at`/`unpublished_by` (jejak penarikan terakhir)
     * maupun riwayat di `activity_logs`.
     */
    public function publish(Request $request, Cooperative $cooperative): RedirectResponse
    {
        Gate::authorize('publish', $cooperative);

        if ($cooperative->is_published) {
            return back()->with('error', 'Koperasi ini sudah dipublikasikan.');
        }

        $blocker = $this->publicationBlocker($cooperative);

        if ($blocker !== null) {
            return back()->with('error', $blocker);
        }

        $cooperative->update([
            'is_published' => true,
            'published_at' => now(),
            'published_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->logger->log('publish_cooperative', $cooperative, $request->user(), [
            'status_verifikasi' => $cooperative->status_verifikasi,
            'published_at' => $cooperative->published_at?->toIso8601String(),
        ], 'Koperasi dipublikasikan ke direktori publik.');

        return redirect()
            ->route('admin.koperasi.show', $cooperative)
            ->with('status', 'Koperasi berhasil dipublikasikan ke direktori publik.');
    }

    /**
     * Tarik koperasi dari direktori publik (FR-190).
     *
     * Yang berubah hanya `is_published` + jejak penarikan. Status verifikasi tidak diubah
     * (tidak menjadi `rejected`/`archived`) dan data tidak diarsipkan.
     *
     * **D-6 (Tahap 105) — jejak publikasi dipertahankan:**
     * - `published_at`/`published_by` dari siklus publikasi **tidak dikosongkan**;
     * - `unpublished_at`/`unpublished_by` mencatat penarikan terakhir;
     * - `activity_logs` (append-only) menyimpan seluruh siklus publish/unpublish beserta aktor.
     * Penarikan berulang tidak menghapus jejak: bila koperasi sudah tidak dipublikasikan,
     * aksi ditolak tanpa mengubah baris (lihat penjagaan di awal method).
     *
     * @see docs/DECISIONS.md §27 (D-6)
     */
    public function unpublish(Request $request, Cooperative $cooperative): RedirectResponse
    {
        Gate::authorize('publish', $cooperative);

        if (! $cooperative->is_published) {
            return back()->with('error', 'Koperasi ini belum dipublikasikan.');
        }

        $cooperative->update([
            'is_published' => false,
            'unpublished_at' => now(),
            'unpublished_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        $this->logger->log('unpublish_cooperative', $cooperative, $request->user(), [
            'status_verifikasi' => $cooperative->status_verifikasi,
            'published_at' => $cooperative->published_at?->toIso8601String(),
            'published_by' => $cooperative->published_by,
            'unpublished_at' => $cooperative->unpublished_at?->toIso8601String(),
            'unpublished_by' => $cooperative->unpublished_by,
        ], 'Publikasi koperasi ditarik dari direktori publik.');

        return redirect()
            ->route('admin.koperasi.show', $cooperative)
            ->with('status', 'Publikasi koperasi ditarik dari direktori publik.');
    }

    /**
     * Alasan koperasi belum dapat dipublikasikan; `null` bila memenuhi syarat.
     *
     * Satu-satunya sumber syarat adalah `config('koperasi.public.visible_statuses')` — konfigurasi
     * yang **sama** dipakai direktori publik (`Publik\KoperasiPublikController::visibleQuery()`),
     * sehingga koperasi yang lolos di sini pasti tampil di direktori, dan sebaliknya.
     */
    private function publicationBlocker(Cooperative $cooperative): ?string
    {
        $allowed = (array) config('koperasi.public.visible_statuses', ['approved']);

        if (in_array($cooperative->status_verifikasi, $allowed, true)) {
            return null;
        }

        $labels = array_map(
            fn (string $status): string => Cooperative::STATUS_LABELS[$status] ?? $status,
            $allowed,
        );

        return sprintf(
            'Koperasi belum dapat dipublikasikan: status verifikasi harus %s. Status saat ini: %s.',
            implode(' / ', $labels),
            $cooperative->statusLabel(),
        );
    }

    /**
     * @return array{
     *     provinces: Collection<int, Region>,
     *     regencies: Collection<int, Region>,
     *     districts: Collection<int, Region>,
     *     villages: Collection<int, Region>,
     *     jenisOptions: array<int, string>,
     *     statusOptions: array<string, string>,
     *     statusOperasionalOptions: array<string, string>
     * }
     */
    private function formData(): array
    {
        $columns = ['id', 'name', 'type', 'parent_id'];

        return [
            'provinces' => Region::query()
                ->where('type', 'province')
                ->orderBy('name')
                ->get($columns),
            'regencies' => Region::query()
                ->whereIn('type', ['regency', 'city'])
                ->orderBy('name')
                ->get($columns),
            'districts' => Region::query()
                ->where('type', 'district')
                ->orderBy('name')
                ->get($columns),
            'villages' => Region::query()
                ->where('type', 'village')
                ->orderBy('name')
                ->get($columns),
            'jenisOptions' => config('koperasi.jenis_koperasi_options', []),
            'statusOptions' => Cooperative::assignableStatusLabels(),
            'statusOperasionalOptions' => Cooperative::statusOperasionalLabels(),
        ];
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
