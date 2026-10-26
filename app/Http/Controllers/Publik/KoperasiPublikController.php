<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\Cooperative;
use App\Models\Region;
use App\Services\PublicFieldSettings;
use App\Support\PiiMask;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Direktori & profil publik koperasi (F7 — FR-180/181/182).
 *
 * Akses tanpa autentikasi (middleware `web` saja). Dua penjagaan utama:
 * 1. **Query ketat** — hanya koperasi `is_published = true` **dan** berstatus resmi
 *    yang boleh tampil (`Cooperative` + `config('koperasi.public.visible_statuses')`).
 *    Penjagaan ini dipakai ulang oleh direktori, pencarian, filter, dan profil, sehingga
 *    koperasi draft/tidak dipublikasikan tidak dapat dijangkau lewat URL langsung (AC-PUB-01).
 * 2. **Allowlist field** — view hanya menerima data yang sudah disaring oleh
 *    `PublicFieldSettings` (pilihan admin yang dibatasi Q-008) dan kontak termasking
 *    (FR-191, NFR-SEC-015, AC-PUB-03). Model koperasi **tidak pernah** dikirim utuh ke view publik.
 *
 * Q-008 sudah dijawab (Tahap 105). Field publik: nama, nomor badan hukum, tanggal berdiri,
 * alamat sampai tingkat desa/kecamatan, jenis koperasi, status operasional. Klarifikasi teknis:
 * - **Alamat** dipublikasikan dari **hierarki wilayah terstruktur** (`regions`: provinsi →
 *   kabupaten/kota → kecamatan → desa/kelurahan), bukan dari teks bebas `cooperatives.alamat`
 *   yang dapat memuat nomor rumah/RT/RW di luar batas yang disetujui.
 * - **Status operasional** tersedia sebagai kolom `status_operasional` (Tahap 106) dan masuk
 *   allowlist publik; `status_verifikasi` **bukan** status operasional sehingga tidak
 *   ditampilkan dengan label tersebut.
 * Q-012: dataset wilayah resmi sudah tersedia (Tahap 107), tetapi impor ke database persisten
 * belum dijalankan. Label wilayah hanya muncul bila datanya tersedia (tidak ada pilihan wilayah
 * karangan). Detail: `docs/REGION_DATA_SOURCES.md`, `docs/DECISIONS.md` §30.
 */
class KoperasiPublikController extends Controller
{
    public function __construct(private readonly PublicFieldSettings $publicFields) {}

    /**
     * Direktori koperasi terpublikasi + pencarian & filter (FR-180, FR-181).
     */
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $settings = $this->publicFields->current();

        $koperasi = $this->visibleQuery()
            ->with(['region.parent.parent.parent'])
            ->when($filters['q'] !== '', fn (Builder $query) => $query->whereRaw(
                "cooperatives.nama LIKE ? ESCAPE '!'",
                ['%'.$this->escapeLike($filters['q']).'%']
            ))
            ->when($filters['jenis'] !== '', fn (Builder $query) => $query->where('jenis_koperasi', $filters['jenis']))
            ->when(
                $filters['wilayah'] !== null,
                fn (Builder $query) => $query->whereIn(
                    'region_id',
                    Region::descendantIdsOf($filters['wilayah']),
                ),
            )
            ->orderBy('nama')
            ->orderBy('id')
            ->paginate($this->perPage())
            ->withQueryString()
            ->through(fn (Cooperative $cooperative): array => $this->present($cooperative, $settings));

        return view('publik.direktori', [
            'koperasi' => $koperasi,
            'filters' => $filters,
            'jenisOptions' => $this->jenisOptions(),
            'regionOptions' => $this->regionOptions(),
        ]);
    }

    /**
     * Profil publik koperasi berdasarkan slug (FR-182).
     *
     * Koperasi yang tidak dipublikasikan/tidak berstatus resmi → **404**, sama seperti slug
     * yang tidak ada, agar tidak membocorkan keberadaan maupun isi data koperasi tersebut.
     */
    public function show(string $slug): View
    {
        $cooperative = $this->visibleQuery()
            ->with(['region.parent.parent.parent'])
            ->where('slug', $slug)
            ->first();

        if ($cooperative === null) {
            abort(404);
        }

        return view('publik.profil', [
            'koperasi' => $this->present($cooperative, $this->publicFields->current()),
        ]);
    }

    /**
     * Statistik agregat publik (FR-183).
     *
     * Hanya menghitung koperasi yang lolos `visibleQuery()` (published + status resmi).
     * Tidak ada data per-koperasi selain agregat; wilayah ditampilkan sampai kabupaten/kota.
     */
    public function statistics(): View
    {
        $base = $this->visibleQuery();

        $byJenis = (clone $base)
            ->select('jenis_koperasi', DB::raw('COUNT(*) as total'))
            ->groupBy('jenis_koperasi')
            ->orderByDesc('total')
            ->orderBy('jenis_koperasi')
            ->get()
            ->map(fn (Cooperative $row): array => [
                'label' => $row->jenis_koperasi ?: 'Belum diisi',
                'total' => (int) $row->total,
            ])
            ->all();

        $byStatus = (clone $base)
            ->select('status_operasional', DB::raw('COUNT(*) as total'))
            ->groupBy('status_operasional')
            ->orderByDesc('total')
            ->get()
            ->map(fn (Cooperative $row): array => [
                'label' => Cooperative::STATUS_OPERASIONAL_LABELS[$row->status_operasional] ?? 'Belum ditetapkan',
                'total' => (int) $row->total,
            ])
            ->all();

        $regionCounts = (clone $base)
            ->whereNotNull('region_id')
            ->select('region_id', DB::raw('COUNT(*) as total'))
            ->groupBy('region_id')
            ->pluck('total', 'region_id');

        $regions = Region::query()
            ->whereIn('id', $regionCounts->keys())
            ->with('parent.parent.parent')
            ->get()
            ->keyBy('id');

        $byRegion = [];

        foreach ($regionCounts as $regionId => $total) {
            $region = $regions->get((int) $regionId);
            $regency = $region?->ancestorOfType('regency') ?? $region?->ancestorOfType('city') ?? $region;
            $key = $regency?->getKey() ?? 0;

            if (! isset($byRegion[$key])) {
                $byRegion[$key] = [
                    'label' => $regency?->name ?? 'Belum ditetapkan',
                    'total' => 0,
                ];
            }

            $byRegion[$key]['total'] += (int) $total;
        }

        $unassigned = (clone $base)->whereNull('region_id')->count();

        if ($unassigned > 0) {
            $byRegion[0] = ['label' => 'Belum ditetapkan', 'total' => (int) $unassigned];
        }

        usort($byRegion, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return view('publik.statistik', [
            'total' => (clone $base)->count(),
            'byRegion' => array_values($byRegion),
            'byJenis' => $byJenis,
            'byStatus' => $byStatus,
        ]);
    }

    /**
     * Query dasar "boleh tampil publik". Satu-satunya sumber kebenaran penjagaan F7.
     *
     * @return Builder<Cooperative>
     */
    private function visibleQuery(): Builder
    {
        $statuses = (array) config('koperasi.public.visible_statuses', ['approved']);

        return Cooperative::query()
            ->where('is_published', true)
            ->whereIn('status_verifikasi', $statuses);
    }

    /**
     * Filter yang dinormalisasi; nilai tak dikenal **diabaikan** (tidak menimbulkan error).
     *
     * @return array{q: string, jenis: string, wilayah: int|null}
     */
    private function filters(Request $request): array
    {
        $q = trim((string) $request->query('q', ''));
        $jenis = trim((string) $request->query('jenis', ''));

        $wilayahRaw = $request->query('wilayah');
        $wilayah = (is_string($wilayahRaw) || is_int($wilayahRaw)) && ctype_digit((string) $wilayahRaw)
            ? (int) $wilayahRaw
            : null;

        // Filter jenis hanya diterima bila memang ada pada data terpublikasi — mencegah
        // penggunaan nilai karangan (Q-003 belum dikonfirmasi).
        if ($jenis !== '' && ! in_array($jenis, $this->jenisOptions(), true)) {
            $jenis = '';
        }

        // Filter wilayah hanya diterima bila wilayah itu memang dipakai data terpublikasi (Q-012).
        if ($wilayah !== null && ! in_array($wilayah, array_column($this->regionOptions(), 'id'), true)) {
            $wilayah = null;
        }

        return ['q' => $q, 'jenis' => $jenis, 'wilayah' => $wilayah];
    }

    /**
     * Pilihan jenis koperasi diambil dari **data yang benar-benar terpublikasi**, bukan daftar
     * resmi (Q-003 belum dikonfirmasi). Kosong = filter tidak ditampilkan.
     *
     * @return list<string>
     */
    private function jenisOptions(): array
    {
        return $this->visibleQuery()
            ->whereNotNull('jenis_koperasi')
            ->where('jenis_koperasi', '<>', '')
            ->distinct()
            ->orderBy('jenis_koperasi')
            ->pluck('jenis_koperasi')
            ->map(fn ($jenis): string => (string) $jenis)
            ->all();
    }

    /**
     * Pilihan filter wilayah = kabupaten/kota dari data terpublikasi.
     *
     * `region_id` koperasi dapat menunjuk desa/kelurahan, sehingga opsi filter
     * dinaikkan ke leluhur kabupaten/kota. Dengan begitu filter tetap ringkas dan
     * memilih kabupaten/kota mencakup seluruh desa/kelurahan di bawahnya.
     *
     * @return list<array{id: int, name: string}>
     */
    private function regionOptions(): array
    {
        $regionIds = $this->visibleQuery()
            ->whereNotNull('region_id')
            ->distinct()
            ->pluck('region_id');

        if ($regionIds->isEmpty()) {
            return [];
        }

        return Region::query()
            ->whereIn('id', $regionIds)
            ->with('parent.parent.parent')
            ->get()
            ->map(fn (Region $region): ?Region => $region->ancestorOfType('regency')
                ?? $region->ancestorOfType('city'))
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->map(fn (Region $region): array => [
                'id' => (int) $region->id,
                'name' => (string) $region->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Susun data publik koperasi: hanya field yang diaktifkan admin (subset allowlist Q-008)
     * + wilayah + kontak termasking.
     *
     * @param  array{fields: list<string>, show_region: bool, contact: array{email: bool, telephone: bool}}  $settings
     * @return array<string, mixed>
     */
    private function present(Cooperative $cooperative, array $settings): array
    {
        $data = [];

        // `slug` adalah identitas URL publik (PRD §9), bukan atribut kebijakan field —
        // karena itu selalu ada agar tautan profil dapat dibentuk.
        $data['slug'] = $cooperative->slug;

        $allowedFields = $this->publicFields->allowedFields();

        foreach ($settings['fields'] as $field) {
            // Hanya kolom skalar yang secara eksplisit diizinkan yang diambil. Nama relasi
            // (`region`, `officers`, `documents`, …) dan field tak dikenal **diabaikan lebih
            // dahulu**, sehingga tidak pernah menyentuh model — benar/salahnya konfigurasi
            // tidak dapat memicu lazy loading, menyerialkan model lain, atau membocorkan PII.
            if (! is_string($field) || ! in_array($field, $allowedFields, true)) {
                continue;
            }

            $value = $cooperative->getAttribute($field);

            // Transformasi khusus field tertentu (Q-008).
            if ($field === 'tanggal_berdiri' && $value !== null) {
                // Format tanggal Indonesia: dd MMMM yyyy.
                $value = Carbon::parse($value)->locale('id')->translatedFormat('d F Y');
            } elseif ($field === 'status_operasional') {
                // Hanya label resmi; `null` bila belum ditetapkan atau nilainya tak dikenal —
                // view tidak menampilkan apa pun (bukan nilai rekaan).
                $value = $cooperative->statusOperasionalLabel();
            } elseif ($field === 'website') {
                // Data lama/import dapat melewati validasi form. Jangan pernah menautkan
                // skema non-web (mis. javascript:) dari halaman publik.
                $value = $this->safeWebsite($cooperative->website);
            }

            $data[$field] = $value;
        }

        // Alamat publik = hierarki wilayah administratif sampai desa/kelurahan (Q-008).
        // Teks bebas `cooperatives.alamat` **tidak** dipublikasikan karena dapat memuat
        // nomor rumah/RT/RW di luar batas yang disetujui.
        if ($settings['show_region']) {
            $data['wilayah'] = $this->regionPath($cooperative->region);
        }

        $data['email'] = $settings['contact']['email']
            ? PiiMask::email($cooperative->email)
            : null;

        $data['telephone'] = $settings['contact']['telephone']
            ? PiiMask::phone($cooperative->telephone)
            : null;

        return $data;
    }

    /**
     * Jalur wilayah administratif dari provinsi sampai wilayah terdalam (desa/kelurahan).
     *
     * Data-driven: tidak ada nama wilayah yang dikarang. Bila `region_id` kosong atau
     * hierarki tidak lengkap, hasilnya `null` dan view menampilkan "Belum ditetapkan".
     *
     * Dibatasi kedalaman & mendeteksi siklus (`parent_id` melingkar) agar tidak pernah
     * berputar tanpa henti atau memuat tak terbatas.
     */
    private function regionPath(?Region $region): ?string
    {
        if ($region === null) {
            return null;
        }

        $names = [];
        $seen = [];
        $current = $region;

        while ($current !== null && count($names) < 8) {
            if (isset($seen[$current->id])) {
                break;
            }

            $seen[$current->id] = true;
            array_unshift($names, (string) $current->name);
            $current = $current->parent;
        }

        return $names === [] ? null : implode(', ', $names);
    }

    private function perPage(): int
    {
        return max(1, (int) config('koperasi.public.per_page', 12));
    }

    /**
     * Netralkan wildcard `LIKE` agar `%`/`_` yang diketik pengguna diperlakukan **literal**.
     *
     * Karakter escape ditulis eksplisit pada klausa `ESCAPE '!'` (SQL standar, didukung
     * SQLite & MySQL/MariaDB), sehingga perilaku **tidak** bergantung pada backslash bawaan
     * MySQL — yang mati bila `sql_mode` memuat `NO_BACKSLASH_ESCAPES`. `!` dipilih karena
     * jarang muncul pada nama koperasi; `!` itu sendiri tetap di-escape (`!!`) agar input
     * seperti `Satu!Dua` diperlakukan sebagai teks biasa.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /**
     * Hanya izinkan URL web absolut (http/https) untuk ditautkan dari halaman publik.
     *
     * Form admin sudah memakai `url:http,https`, tetapi data lama/import bisa saja belum
     * melewati validasi tersebut. Nilai yang tidak aman ditampilkan sebagai tidak ada.
     */
    private function safeWebsite(?string $website): ?string
    {
        $website = trim((string) $website);

        if ($website === '' || filter_var($website, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = strtolower((string) parse_url($website, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $website : null;
    }
}
