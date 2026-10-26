<?php

namespace App\Policies;

use App\Models\MemberHistory;
use App\Models\User;
use App\Policies\Concerns\ChecksAdminRegion;

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
    use ChecksAdminRegion;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']) || $user->hasRole('pengelola_koperasi');
    }

    public function view(User $user, MemberHistory $history): bool
    {
        if ($user->hasAnyRole(['admin_dinas', 'admin_wilayah'])) {
            return $this->adminCanAccessCooperative($user, (int) $history->cooperative_id);
        }

        return $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $history->cooperative_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']);
    }

    public function update(User $user, MemberHistory $history): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $history->cooperative_id);
    }

    public function delete(User $user, MemberHistory $history): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $history->cooperative_id);
    }

    public function restore(User $user, MemberHistory $history): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $history->cooperative_id);
    }

    public function forceDelete(User $user, MemberHistory $history): bool
    {
        return false;
    }
}
