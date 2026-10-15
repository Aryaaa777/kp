<?php

namespace App\Policies;

use App\Models\Cooperative;
use App\Models\User;

/**
 * Otorisasi data induk koperasi (FR-040, FR-041, FR-043).
 *
 * - Admin dinas: kelola penuh.
 * - Pengelola koperasi: hanya boleh MELIHAT koperasi yang tertaut pada akunnya (anti-IDOR).
 *   Perubahan oleh pengelola dilakukan lewat pengajuan (FR-042, fase F5), bukan langsung.
 */
class CooperativePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function view(User $user, Cooperative $cooperative): bool
    {
        if ($user->hasRole('admin_dinas')) {
            return true;
        }

        return $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $cooperative->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function update(User $user, Cooperative $cooperative): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function delete(User $user, Cooperative $cooperative): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function restore(User $user, Cooperative $cooperative): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function forceDelete(User $user, Cooperative $cooperative): bool
    {
        return $user->hasRole('admin_dinas');
    }
}
