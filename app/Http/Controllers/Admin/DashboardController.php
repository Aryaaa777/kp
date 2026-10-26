<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cooperative;
use App\Models\Region;
use App\Services\AdminRegionScope;
use App\Services\Reporting\CooperativeFilters;
use App\Services\Reporting\DashboardStatistics;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard admin dinas (FR-020 statistik, FR-021 antrian, FR-050 izin kedaluwarsa).
 *
 * Seluruh angka berasal dari query agregat nyata (`DashboardStatistics`) — tidak ada
 * statistik hardcoded. Hanya baca (read-only); tidak ada aksi yang mengubah status.
 *
 * Filter wilayah/periode/status/jenis (FR-020 "Data Masukan") dinormalisasi lewat
 * {@see CooperativeFilters} — komponen yang sama dengan daftar koperasi & laporan, sehingga
 * angka kartu konsisten dengan hasil query daftar yang setara (FR-020 AC). Antrian pengajuan
 * (FR-021) dan izin kedaluwarsa (FR-050) memiliki cakupan sendiri dan tidak difilter di sini.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatistics $statistics,
        private readonly AdminRegionScope $regionScope,
    ) {}

    public function index(Request $request): View
    {
        $this->statistics->scopeToRegions($this->regionScope->regionIds($request->user()));

        $filters = CooperativeFilters::normalize([
            'region_id' => $request->query('region_id'),
            'jenis_koperasi' => $request->query('jenis_koperasi'),
            'status_verifikasi' => $request->query('status_verifikasi'),
            'tahun' => $request->query('tahun'),
        ]);

        return view('admin.dashboard', [
            'user' => $request->user(),
            'statusLabels' => Cooperative::STATUS_LABELS,
            'filters' => $filters,
            'hasFilters' => CooperativeFilters::hasAny($filters),
            'regionOptions' => Region::query()
                ->whereIn('type', ['regency', 'city'])
                ->orderBy('name')
                ->get(),
            'jenisOptions' => $this->statistics->availableJenis(),
            'yearOptions' => $this->statistics->availableYears(),
            'unassignedRegionLabel' => CooperativeFilters::REGION_UNASSIGNED_LABEL,
            'totalCooperatives' => $this->statistics->totalCooperatives($filters),
            'statusCounts' => $this->statistics->countByStatus($filters),
            'jenisCounts' => $this->statistics->countByJenis($filters),
            'regionCounts' => $this->statistics->countByRegion($filters),
            'periodCounts' => $this->statistics->countByPeriodYear($filters),
            'queueCount' => $this->statistics->queueCount(),
            'queueCounts' => $this->statistics->queueCounts(),
            'queue' => $this->statistics->queue(),
            'expiredLicenseCount' => $this->statistics->expiredLicenseCount(),
            'expiredLicenses' => $this->statistics->expiredLicenses(),
            'expiringLicenseCount' => $this->statistics->expiringLicenseCount(),
            'expiringLicenses' => $this->statistics->expiringLicenses(),
            'licenseExpiringDays' => (int) config('koperasi.license_expiring_days', 30),
        ]);
    }
}
