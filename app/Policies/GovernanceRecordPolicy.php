<?php

namespace App\Policies;

use App\Models\GovernanceRecord;
use App\Models\User;
use App\Policies\Concerns\ChecksAdminRegion;

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
    use ChecksAdminRegion;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']) || $user->hasRole('pengelola_koperasi');
    }

    public function view(User $user, GovernanceRecord $record): bool
    {
        if ($user->hasAnyRole(['admin_dinas', 'admin_wilayah'])) {
            return $this->adminCanAccessCooperative($user, (int) $record->cooperative_id);
        }

        return $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $record->cooperative_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']);
    }

    public function update(User $user, GovernanceRecord $record): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $record->cooperative_id);
    }

    public function delete(User $user, GovernanceRecord $record): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $record->cooperative_id);
    }

    public function restore(User $user, GovernanceRecord $record): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $record->cooperative_id);
    }

    public function forceDelete(User $user, GovernanceRecord $record): bool
    {
        return false;
    }
}
