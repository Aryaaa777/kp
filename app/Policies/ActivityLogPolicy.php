<?php

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * Otorisasi audit trail (FR-151).
 *
 * Hanya admin dinas yang dapat MELIHAT log. Tidak ada jalur UI untuk membuat/mengubah/menghapus
 * (AC-AUD-02: log tidak dapat diubah/dihapus dari UI; ditambah penegakan append-only pada model).
 */
class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function view(User $user, ActivityLog $log): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $log): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $log): bool
    {
        return false;
    }
}
