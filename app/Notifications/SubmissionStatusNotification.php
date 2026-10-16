<?php

namespace App\Notifications;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi in-app status pengajuan (FR-201).
 *
 * Kanal: `database` saja (email/WA ditunda — Q-011).
 *
 * @see docs/DECISIONS.md §12 (notifikasi in-app)
 */
class SubmissionStatusNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Submission $submission) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $status = $this->submission->status;

        return [
            'submission_id' => $this->submission->id,
            'status' => $status,
            'status_label' => $this->submission->statusLabel(),
            'title' => $this->title($status),
            'message' => $this->message($status),
            'reason' => $this->submission->reason,
            'url' => route('pengelola.pengajuan.show', $this->submission),
        ];
    }

    private function title(string $status): string
    {
        return match ($status) {
            'approved' => 'Pengajuan disetujui',
            'needs_revision' => 'Pengajuan perlu perbaikan',
            'rejected' => 'Pengajuan ditolak',
            default => 'Status pengajuan diperbarui',
        };
    }

    private function message(string $status): string
    {
        $module = $this->submission->title ?? 'Pengajuan';

        return match ($status) {
            'approved' => "{$module} telah disetujui dan perubahan diterapkan ke data resmi.",
            'needs_revision' => "{$module} memerlukan perbaikan sebelum dapat disetujui.",
            'rejected' => "{$module} ditolak. Lihat alasan pada detail pengajuan.",
            default => "Status {$module} diperbarui menjadi {$this->submission->statusLabel()}.",
        };
    }
}
