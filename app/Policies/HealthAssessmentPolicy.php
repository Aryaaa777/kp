<?php

namespace App\Policies;

use App\Models\HealthAssessment;
use App\Models\User;
use App\Policies\Concerns\ChecksAdminRegion;

/**
 * Otorisasi penilaian kesehatan (FR-080/081).
 *
 * - Admin dinas: kelola penuh (BR-010 — kewenangan resmi).
 * - Pengelola koperasi: hanya MELIHAT kesehatan koperasinya sendiri (read-only).
 * - Guest: tidak ada akses.
 *
 * PROVISIONAL: penulisan hanya admin; pengelola write via workflow pengajuan menyusul (F5).
 */
class HealthAssessmentPolicy
{
    use ChecksAdminRegion;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']) || $user->hasRole('pengelola_koperasi');
    }

    public function view(User $user, HealthAssessment $assessment): bool
    {
        if ($user->hasAnyRole(['admin_dinas', 'admin_wilayah'])) {
            return $this->adminCanAccessCooperative($user, (int) $assessment->cooperative_id);
        }

        return $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $assessment->cooperative_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']);
    }

    public function update(User $user, HealthAssessment $assessment): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $assessment->cooperative_id);
    }

    public function delete(User $user, HealthAssessment $assessment): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $assessment->cooperative_id);
    }

    public function restore(User $user, HealthAssessment $assessment): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $assessment->cooperative_id);
    }

    public function forceDelete(User $user, HealthAssessment $assessment): bool
    {
        return false;
    }
}
