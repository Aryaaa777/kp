<?php

namespace App\Policies;

use App\Models\GovernanceRecord;
use App\Models\User;

/**
 * Otorisasi data tata kelola (FR-090).
 *
 * - Admin dinas: kelola penuh.
 * - Pengelola koperasi: hanya MELIHAT tata kelola koperasinya sendiri (read-only).
 * - Guest: tidak ada akses.
 *
 * PROVISIONAL: penulisan hanya admin; pengelola write via workflow pengajuan menyusul (F5).
 */
class GovernanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin_dinas') || $user->hasRole('pengelola_koperasi');
    }

    public function view(User $user, GovernanceRecord $record): bool
    {
        if ($user->hasRole('admin_dinas')) {
            return true;
        }

        return $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $record->cooperative_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function update(User $user, GovernanceRecord $record): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function delete(User $user, GovernanceRecord $record): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function restore(User $user, GovernanceRecord $record): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function forceDelete(User $user, GovernanceRecord $record): bool
    {
        return false;
    }
}
