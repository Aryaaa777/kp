<?php

namespace App\Policies;

use App\Models\Cooperative;
use App\Models\User;
use App\Policies\Concerns\ChecksAdminRegion;

/**
 * Otorisasi data induk koperasi (FR-040, FR-041, FR-043).
 *
 * - Admin dinas: kelola penuh.
 * - Pengelola koperasi: hanya boleh MELIHAT koperasi yang tertaut pada akunnya (anti-IDOR).
 *   Perubahan oleh pengelola dilakukan lewat pengajuan (FR-042, fase F5), bukan langsung.
 */
class CooperativePolicy
{
    use ChecksAdminRegion;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']);
    }

    public function view(User $user, Cooperative $cooperative): bool
    {
        if ($user->hasAnyRole(['admin_dinas', 'admin_wilayah'])) {
            return $this->adminCanAccessCooperative($user, (int) $cooperative->getKey());
        }

        return $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $cooperative->getKey();
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']);
    }

    public function update(User $user, Cooperative $cooperative): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $cooperative->getKey());
    }

    /**
     * Mengelola publikasi direktori publik (FR-190) — kewenangan admin dinas.
     *
     * Dipakai untuk **menerbitkan** maupun **menarik** publikasi: keduanya satu kelas
     * pengelolaan status publikasi. Kelayakan terbitkan (status verifikasi resmi) diperiksa
     * terpisah di controller, bukan di policy — policy hanya menjawab "siapa".
     */
    public function publish(User $user, Cooperative $cooperative): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $cooperative->getKey());
    }

    public function delete(User $user, Cooperative $cooperative): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $cooperative->getKey());
    }

    public function restore(User $user, Cooperative $cooperative): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $cooperative->getKey());
    }

    public function forceDelete(User $user, Cooperative $cooperative): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $cooperative->getKey());
    }
}
