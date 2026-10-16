<?php

namespace App\Http\Controllers\Pengelola;

use App\Http\Controllers\Controller;
use App\Models\HealthAssessment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengelola melihat riwayat kesehatan koperasinya sendiri (read-only, FR-081).
 *
 * BR-010: pengelola tidak boleh mengubah hasil penilaian resmi (kewenangan admin).
 * Write access (via pengajuan) menyusul pada F5.
 */
class KesehatanController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', HealthAssessment::class);

        $user = $request->user();

        if ($user->koperasi_id === null) {
            abort(403, 'Akun Anda belum terhubung ke koperasi. Hubungi admin dinas.');
        }

        $assessments = HealthAssessment::where('cooperative_id', $user->koperasi_id)
            ->orderByDesc('period_year')
            ->orderByDesc('assessment_date')
            ->paginate(15);

        return view('pengelola.kesehatan.index', [
            'assessments' => $assessments,
        ]);
    }

    public function show(Request $request, HealthAssessment $healthAssessment): View
    {
        $this->authorize('view', $healthAssessment);

        return view('pengelola.kesehatan.show', [
            'assessment' => $healthAssessment,
        ]);
    }
}
