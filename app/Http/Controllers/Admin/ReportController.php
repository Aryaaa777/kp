<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cooperative;
use App\Models\Region;
use App\Services\ActivityLogger;
use App\Services\AdminRegionScope;
use App\Services\Reporting\CooperativeFilters;
use App\Services\Reporting\CooperativeReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan & rekapitulasi koperasi (FR-170) — **read-only**.
 *
 * Hanya koperasi terverifikasi (`status_verifikasi = 'approved'`) yang dihitung
 * (AC-REP-01 / keputusan F6-2). Ekspor CSV (FR-171) memakai filter yang sama dan
 * dicatat ke audit. Pembatasan wilayah admin (FR-012) diterapkan lewat
 * {@see AdminRegionScope} saat scope aktif.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly CooperativeReport $report,
        private readonly AdminRegionScope $regionScope,
        private readonly ActivityLogger $logger,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Cooperative::class);
        $this->report->scopeToRegions($this->regionScope->regionIds($request->user()));

        $filters = $this->report->normalizeFilters([
            'region_id' => $request->query('region_id'),
            'jenis_koperasi' => $request->query('jenis_koperasi'),
            'status_verifikasi' => $request->query('status_verifikasi'),
            'tahun' => $request->query('tahun'),
        ]);

        return view('admin.laporan.index', [
            'filters' => $filters,
            'total' => $this->report->total($filters),
            'byRegion' => $this->report->byRegion($filters),
            'byJenis' => $this->report->byJenis($filters),
            'byPeriod' => $this->report->byPeriod($filters),
            'regionOptions' => Region::query()
                ->whereIn('type', ['regency', 'city'])
                ->orderBy('name')
                ->get(),
            'jenisOptions' => $this->report->availableJenis(),
            'yearOptions' => $this->report->availableYears(),
            'statusOptions' => Cooperative::STATUS_LABELS,
            'verifiedStatus' => CooperativeReport::STATUS_VERIFIED,
            'unassignedRegionLabel' => CooperativeFilters::REGION_UNASSIGNED_LABEL,
        ]);
    }

    /**
     * Ekspor laporan terverifikasi ke CSV (FR-171).
     *
     * Tanpa paket tambahan; memakai stream agar data besar tidak dimuat sekaligus.
     * Filter dan aturan `approved` sama dengan laporan di layar. Setiap ekspor
     * dicatat ke audit trail (FR-151).
     */
    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', Cooperative::class);
        $this->report->scopeToRegions($this->regionScope->regionIds($request->user()));

        $filters = $this->report->normalizeFilters([
            'region_id' => $request->query('region_id'),
            'jenis_koperasi' => $request->query('jenis_koperasi'),
            'status_verifikasi' => $request->query('status_verifikasi'),
            'tahun' => $request->query('tahun'),
        ]);

        $this->logger->log(
            'export_report',
            null,
            $request->user(),
            ['format' => 'csv', 'filters' => $filters],
            'Laporan koperasi diekspor ke CSV.',
        );

        $filename = 'laporan-koperasi-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filters): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // BOM UTF-8 agar karakter Indonesia terbaca benar di Excel.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Nama',
                'Nomor Identitas',
                'Jenis Koperasi',
                'Wilayah',
                'Status Operasional',
                'Tahun Terdaftar',
            ]);

            foreach ($this->report->detailQuery($filters)->lazy(500) as $cooperative) {
                fputcsv($out, [
                    $this->csvValue($cooperative->nama),
                    $this->csvValue($cooperative->nomor_identitas),
                    $this->csvValue($cooperative->jenis_koperasi),
                    $this->csvValue($cooperative->region?->fullPath(' > ') ?? CooperativeFilters::REGION_UNASSIGNED_LABEL),
                    $this->csvValue(
                        Cooperative::STATUS_OPERASIONAL_LABELS[$cooperative->status_operasional] ?? '',
                    ),
                    $cooperative->created_at?->format('Y') ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Cegah formula injection saat CSV dibuka di spreadsheet.
     */
    private function csvValue(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)
            ? "'".$value
            : $value;
    }
}
