<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;
use App\Policies\Concerns\ChecksAdminRegion;

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
    use ChecksAdminRegion;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']) || $user->hasRole('pengelola_koperasi');
    }

    public function view(User $user, Submission $submission): bool
    {
        return $this->adminCanAccess($user, $submission) || $this->ownsCooperative($user, $submission);
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
     * Buat versi perbaikan dari pengajuan `needs_revision` milik koperasinya (FR-031/FR-142).
     * Pengajuan yang sudah digantikan versi turunan tidak dapat diperbaiki lagi.
     */
    public function revise(User $user, Submission $submission): bool
    {
        return $this->ownsCooperative($user, $submission) && $submission->isRevisable();
    }

    /**
     * Arsipkan pengajuan (bukan hapus permanen, BR-011).
     */
    public function archive(User $user, Submission $submission): bool
    {
        if ($this->adminCanAccess($user, $submission)) {
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
        return $this->adminCanAccess($user, $submission);
    }

    public function restore(User $user, Submission $submission): bool
    {
        return false;
    }

    public function forceDelete(User $user, Submission $submission): bool
    {
        return false;
    }

    private function adminCanAccess(User $user, Submission $submission): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $submission->cooperative_id);
    }

    private function ownsCooperative(User $user, Submission $submission): bool
    {
        return $user->hasRole('pengelola_koperasi')
            && $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $submission->cooperative_id;
    }
}
