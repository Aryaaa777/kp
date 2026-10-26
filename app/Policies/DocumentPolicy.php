<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use App\Policies\Concerns\ChecksAdminRegion;

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
    use ChecksAdminRegion;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']);
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
        return $user->hasAnyRole(['admin_dinas', 'admin_wilayah']);
    }

    /**
     * Unggah versi baru (FR-072).
     */
    public function update(User $user, Document $document): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $document->cooperative_id);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $document->cooperative_id);
    }

    public function restore(User $user, Document $document): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $document->cooperative_id);
    }

    public function forceDelete(User $user, Document $document): bool
    {
        return $this->adminCanAccessCooperative($user, (int) $document->cooperative_id);
    }

    private function adminOrOwner(User $user, Document $document): bool
    {
        if ($user->hasAnyRole(['admin_dinas', 'admin_wilayah'])) {
            return $this->adminCanAccessCooperative($user, (int) $document->cooperative_id);
        }

        return $user->koperasi_id !== null
            && (int) $user->koperasi_id === (int) $document->cooperative_id;
    }
}
