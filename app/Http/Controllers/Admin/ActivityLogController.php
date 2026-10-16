<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Audit log aktivitas (FR-151) — hanya baca.
 *
 * Hanya admin dinas yang dapat melihat (PRD §8.2). Log bersifat append-only: tidak ada jalur
 * UI untuk membuat/mengubah/menghapus (AC-AUD-02).
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ActivityLog::class);

        $action = (string) $request->query('action', '');
        $userId = $request->query('user_id');
        $q = trim((string) $request->query('q', ''));

        $logs = ActivityLog::query()
            ->with('user')
            ->when($action !== '', fn (Builder $query) => $query->where('action', $action))
            ->when(filled($userId), fn (Builder $query) => $query->where('user_id', $userId))
            ->when($q !== '', fn (Builder $query) => $query->where('description', 'like', "%{$q}%"))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-log.index', [
            'logs' => $logs,
            'filters' => [
                'action' => $action,
                'user_id' => $userId,
                'q' => $q,
            ],
            'actionOptions' => ActivityLog::query()
                ->select('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action', 'action'),
            'userOptions' => User::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
