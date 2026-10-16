<?php

namespace App\Policies;

use App\Models\MemberHistory;
use App\Models\User;

/**
 * Otorisasi riwayat keanggotaan (FR-110/111).
 *
 * - Admin dinas: kelola penuh.
 * - Pengelola koperasi: hanya MELIHAT riwayat keanggotaan koperasinya sendiri (read-only).
 * - Guest: tidak ada akses.
 *
 * PROVISIONAL: penulisan hanya admin; pengelola write via workflow pengajuan menyusul (F5).
 */
class MemberHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin_dinas') || $user->hasRole('pengelola_koperasi');
    }

    public function view(User $user, MemberHistory $history): bool
    {
        if ($user->hasRole('admin_dinas')) {
            return true;
        }

        return $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $history->cooperative_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function update(User $user, MemberHistory $history): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function delete(User $user, MemberHistory $history): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function restore(User $user, MemberHistory $history): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function forceDelete(User $user, MemberHistory $history): bool
    {
        return false;
    }
}
