<?php

namespace App\Policies;

use App\Models\CooperativeAsset;
use App\Models\User;

/**
 * Otorisasi aset koperasi (FR-100/101).
 *
 * - Admin dinas: kelola penuh.
 * - Pengelola koperasi: hanya MELIHAT aset koperasinya sendiri (read-only).
 * - Guest: tidak ada akses.
 *
 * PROVISIONAL: penulisan hanya admin; pengelola write via workflow pengajuan menyusul (F5).
 */
class CooperativeAssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin_dinas') || $user->hasRole('pengelola_koperasi');
    }

    public function view(User $user, CooperativeAsset $asset): bool
    {
        if ($user->hasRole('admin_dinas')) {
            return true;
        }

        return $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $asset->cooperative_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function update(User $user, CooperativeAsset $asset): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function delete(User $user, CooperativeAsset $asset): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function restore(User $user, CooperativeAsset $asset): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function forceDelete(User $user, CooperativeAsset $asset): bool
    {
        return false;
    }
}
