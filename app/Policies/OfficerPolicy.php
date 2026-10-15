<?php

namespace App\Policies;

use App\Models\Officer;
use App\Models\User;

/**
 * Policy untuk otorisasi akses data pengurus koperasi (officers).
 *
 * FR-120/121: CRUD pengurus & periode jabatan.
 *
 * Aturan otorisasi:
 * - Admin dinas: akses penuh ke semua pengurus semua koperasi.
 * - Pengelola koperasi: hanya lihat pengurus koperasinya sendiri (read-only di F4).
 *   Write access untuk pengelola akan dibuka via workflow pengajuan (F5).
 * - Guest: tidak ada akses.
 *
 * Mencegah IDOR: pengelola koperasi A tidak dapat melihat pengurus koperasi B.
 *
 * @see docs/PRD.md FR-120, FR-121
 * @see docs/ROADMAP_REMAINING.md F4 (pengelola read-only di F4, write via workflow F5)
 */
class OfficerPolicy
{
    /**
     * Menentukan apakah pengguna dapat melihat daftar pengurus.
     *
     * Admin: semua pengurus.
     * Pengelola: hanya pengurus koperasinya (filter di controller).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin_dinas') || $user->hasRole('pengelola_koperasi');
    }

    /**
     * Menentukan apakah pengguna dapat melihat detail pengurus tertentu.
     *
     * Admin: semua pengurus.
     * Pengelola: hanya pengurus koperasinya.
     */
    public function view(User $user, Officer $officer): bool
    {
        if ($user->hasRole('admin_dinas')) {
            return true;
        }

        if ($user->hasRole('pengelola_koperasi')) {
            return $officer->cooperative_id === $user->koperasi_id;
        }

        return false;
    }

    /**
     * Menentukan apakah pengguna dapat membuat pengurus baru.
     *
     * Admin: ya (semua koperasi).
     * Pengelola: tidak (di F4) — akan dibuka via workflow F5.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin_dinas');
    }

    /**
     * Menentukan apakah pengguna dapat mengubah pengurus tertentu.
     *
     * Admin: ya (semua pengurus).
     * Pengelola: tidak (di F4) — akan dibuka via workflow F5.
     */
    public function update(User $user, Officer $officer): bool
    {
        return $user->hasRole('admin_dinas');
    }

    /**
     * Menentukan apakah pengguna dapat menghapus (soft delete) pengurus tertentu.
     *
     * Admin: ya (semua pengurus).
     * Pengelola: tidak (di F4) — akan dibuka via workflow F5.
     */
    public function delete(User $user, Officer $officer): bool
    {
        return $user->hasRole('admin_dinas');
    }

    /**
     * Menentukan apakah pengguna dapat memulihkan pengurus yang dihapus.
     *
     * Admin only.
     */
    public function restore(User $user, Officer $officer): bool
    {
        return $user->hasRole('admin_dinas');
    }

    /**
     * Menentukan apakah pengguna dapat menghapus permanen pengurus.
     *
     * Tidak ada (force delete tidak diekspos ke UI sesuai BR-011).
     */
    public function forceDelete(User $user, Officer $officer): bool
    {
        return false;
    }
}
