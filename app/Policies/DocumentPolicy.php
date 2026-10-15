<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Otorisasi dokumen pendukung (FR-070/071).
 *
 * - Unduh/lihat: admin dinas untuk semua dokumen; pengelola hanya dokumen koperasinya (anti-IDOR,
 *   AC-DOC-03). Berkas ada di storage privat sehingga hanya dapat diakses lewat controller ber-Policy.
 * - Unggah: admin dinas (FLOW-ADM-05). Pengelola mengunggah dokumen sebagai bagian dari pengajuan
 *   (FLOW-PGL-03), yang dibangun pada F5 — lihat `docs/DECISIONS.md` §5.
 */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function view(User $user, Document $document): bool
    {
        return $this->adminOrOwner($user, $document);
    }

    /**
     * Unduh berkas privat (FR-071).
     */
    public function download(User $user, Document $document): bool
    {
        return $this->adminOrOwner($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->hasRole('admin_dinas');
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return $user->hasRole('admin_dinas');
    }

    private function adminOrOwner(User $user, Document $document): bool
    {
        if ($user->hasRole('admin_dinas')) {
            return true;
        }

        return $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $document->cooperative_id;
    }
}
