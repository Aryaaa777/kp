<?php

namespace App\Http\Controllers\Pengelola;

use App\Http\Controllers\Controller;
use App\Models\Cooperative;
use App\Models\Submission;
use App\Services\Reporting\CompletenessReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dashboard pengelola koperasi (FR-030 kelengkapan, FR-031 permintaan perbaikan).
 *
 * Semua data dibatasi pada koperasi milik akun (anti-IDOR, `AGENTS.md` §4.5). Akun tanpa
 * koperasi tertaut (atau koperasi yang sudah diarsipkan) ditangani sebagai halaman
 * informatif — bukan HTTP 500.
 */
class DashboardController extends Controller
{
    /**
     * Status pengajuan yang membutuhkan tindakan pengelola (FR-031).
     *
     * @var list<string>
     */
    public const REVISION_STATUSES = ['needs_revision', 'rejected'];

    public function __construct(private readonly CompletenessReport $completeness) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $cooperative = $user->koperasi;

        $completeness = null;
        $revisionRequests = new Collection;
        $revisionCount = 0;
        $latestSubmission = null;
        $submissionCounts = [];

        if ($cooperative instanceof Cooperative) {
            $completeness = $this->completeness->build($cooperative);
            $cooperativeId = (int) $cooperative->getKey();

            // Kartu "Butuh Perbaikan" (S-1) memakai definisi **actionable** yang sama dengan daftar
            // di bawahnya: `needs_revision` yang belum digantikan versi turunan + `rejected`.
            $revisionCount = $this->actionableRevisions($cooperativeId)->count();

            $revisionRequests = $this->actionableRevisions($cooperativeId)
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get();

            $latestSubmission = $this->submissionQuery($cooperativeId)
                ->orderByDesc('updated_at')
                ->first();

            $submissionCounts = $this->submissionQuery($cooperativeId)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->get()
                ->mapWithKeys(fn (Submission $row): array => [(string) $row->status => (int) $row->total])
                ->all();
        }

        return view('pengelola.dashboard', [
            'user' => $user,
            'cooperative' => $cooperative,
            'completeness' => $completeness,
            'revisionRequests' => $revisionRequests,
            'revisionCount' => $revisionCount,
            'latestSubmission' => $latestSubmission,
            'submissionCounts' => $submissionCounts,
        ]);
    }

    /**
     * Pengajuan yang benar-benar **membutuhkan tindakan** pengelola (FR-031, definisi S-1):
     * `needs_revision` yang **belum** digantikan versi turunan, ditambah `rejected` (terminal,
     * tetapi tetap ditampilkan sebagai riwayat keputusan). Definisi ini dipakai bersama oleh
     * kartu statistik dan daftar agar angkanya konsisten.
     *
     * @return Builder<Submission>
     */
    private function actionableRevisions(int $cooperativeId): Builder
    {
        return $this->submissionQuery($cooperativeId)
            ->whereIn('status', self::REVISION_STATUSES)
            ->where(function (Builder $query): void {
                $query->where('status', 'rejected')
                    ->orWhere(function (Builder $inner): void {
                        $inner->where('status', 'needs_revision')->whereDoesntHave('children');
                    });
            });
    }

    /**
     * Query dasar pengajuan milik satu koperasi (isolasi data sisi pengelola).
     *
     * @return Builder<Submission>
     */
    private function submissionQuery(int $cooperativeId): Builder
    {
        return Submission::query()->where('cooperative_id', $cooperativeId);
    }
}
