<?php

namespace App\Policies;

use App\Models\HealthAssessment;
use App\Models\User;

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
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin_dinas') || $user->hasRole('pengelola_koperasi');
    }

    public function view(User $user, HealthAssessment $assessment): bool
    {
        if ($user->hasRole('admin_dinas')) {
            return true;
        }

        return $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $assessment->cooperative_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function update(User $user, HealthAssessment $assessment): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function delete(User $user, HealthAssessment $assessment): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function restore(User $user, HealthAssessment $assessment): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function forceDelete(User $user, HealthAssessment $assessment): bool
    {
        return false;
    }
}
