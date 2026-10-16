<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Penulis audit trail (FR-150/151) ke tabel `activity_logs` (append-only).
 *
 * Dipakai oleh: workflow pengajuan (create/submit/review/approve), CRUD data induk koperasi,
 * proses login, dan unduh dokumen. Nilai lama→baru disimpan pada kolom `properties`.
 *
 * **Tidak** mencatat data pribadi sensitif (mis. NIK pengurus) — properti audit dibatasi
 * pada field yang relevan dengan aksi.
 *
 * @see docs/DATA_DICTIONARY.md §17 (activity_logs)
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?User $actor = null,
        array $properties = [],
        ?string $description = null,
    ): ActivityLog {
        $request = request();

        return ActivityLog::create([
            'user_id' => ($actor ?? auth()->user())?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
