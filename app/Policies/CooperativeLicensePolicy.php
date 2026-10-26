<?php

namespace App\Policies;

use App\Models\CooperativeLicense;
use App\Models\User;
use App\Policies\Concerns\ChecksAdminRegion;

/**
 * Otorisasi izin usaha & izin usaha simpan pinjam (FR-050/051/060/061).
 *
 * - Admin dinas: kelola penuh (CRUD + verifikasi).
 * - Pengelola koperasi: hanya boleh MELIHAT izin koperasi yang tertaut pada akunnya (anti-IDOR).
 *   Pengajuan izin oleh pengelola dilakukan lewat workflow pengajuan (FR-140/141, fase F5),
 *   bukan penulisan langsung (BR-014, PRD §12.3) — lihat `docs/DECISIONS.md` §5.
 */
class CooperativeLicensePolicy
{
    use ChecksAdminRegion;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']);
    }

    public function view(User $user, CooperativeLicense $license): bool
    {
        if ($user->hasAnyRole(['admin_dinas', 'admin_wilayah'])) {
            return $this->adminCanAccessCooperative($user, (int) $license->cooperative_id);
        }

        return $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $license->cooperative_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']);
    }

    public function update(User $user, CooperativeLicense $license): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $license->cooperative_id);
    }

    public function delete(User $user, CooperativeLicense $license): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $license->cooperative_id);
    }

    /**
     * Verifikasi izin (FR-051/061): mengubah status hasil + catatan.
     */
    public function verify(User $user, CooperativeLicense $license): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $license->cooperative_id);
    }

    public function restore(User $user, CooperativeLicense $license): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $license->cooperative_id);
    }

    public function forceDelete(User $user, CooperativeLicense $license): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $license->cooperative_id);
    }
}
