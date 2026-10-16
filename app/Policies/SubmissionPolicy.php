<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

/**
 * Otorisasi workflow pengajuan (FR-140…143, PRD §8.2 & §12).
 *
 * - Pengelola koperasi: membuat/mengirim/mengubah pengajuan **koperasinya sendiri**; melihat
 *   riwayat pengajuan koperasinya.
 * - Admin dinas: melihat & memutuskan (review) semua pengajuan; TIDAK membuat pengajuan
 *   (admin mengelola data resmi langsung). Lihat `docs/PRD.md` §8.2 (baris "Workflow pengajuan").
 *
 * Penyembunyian tombol di Blade BUKAN pengamanan — semua aksi diverifikasi di server.
 */
class SubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin_dinas') || $user->hasRole('pengelola_koperasi');
    }

    public function view(User $user, Submission $submission): bool
    {
        return $this->isAdmin($user) || $this->ownsCooperative($user, $submission);
    }

    /**
     * Hanya pengelola (yang tertaut koperasi) yang membuat pengajuan.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('pengelola_koperasi') && $user->koperasi_id !== null;
    }

    public function update(User $user, Submission $submission): bool
    {
        return $this->ownsCooperative($user, $submission) && $submission->isEditable();
    }

    public function submit(User $user, Submission $submission): bool
    {
        return $this->ownsCooperative($user, $submission) && $submission->isEditable();
    }

    /**
     * Arsipkan pengajuan (bukan hapus permanen, BR-011).
     */
    public function archive(User $user, Submission $submission): bool
    {
        if ($this->isAdmin($user)) {
            return in_array($submission->status, ['approved', 'rejected'], true);
        }

        return $this->ownsCooperative($user, $submission)
            && in_array($submission->status, ['draft', 'needs_revision', 'rejected'], true);
    }

    /**
     * Meninjau/memutuskan pengajuan — kewenangan admin dinas (FR-141, BR-005).
     */
    public function review(User $user, Submission $submission): bool
    {
        return $this->isAdmin($user);
    }

    public function restore(User $user, Submission $submission): bool
    {
        return false;
    }

    public function forceDelete(User $user, Submission $submission): bool
    {
        return false;
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin_dinas');
    }

    private function ownsCooperative(User $user, Submission $submission): bool
    {
        return $user->hasRole('pengelola_koperasi')
            && $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $submission->cooperative_id;
    }
}
