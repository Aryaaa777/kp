<?php

namespace App\Exceptions;

use App\Models\Cooperative;
use App\Models\Submission;
use App\Services\Submissions\SubmissionModules;

/**
 * Konflik versioning & pengajuan ganda (FR-031/FR-142).
 *
 * Dipakai untuk menolak: (a) pembuatan pengajuan baru saat masih ada pengajuan aktif yang
 * bertentangan pada koperasi & modul yang sama, (b) perbaikan atas pengajuan yang bukan
 * `needs_revision`, dan (c) perbaikan atas pengajuan yang sudah digantikan versi turunan.
 *
 * Mewarisi `SubmissionTransitionException` agar penanganan di controller (yang sudah menangkap
 * tipe tersebut) tetap sederhana, tanpa menyembunyikan makna pesan.
 *
 * @see docs/DECISIONS.md §17 (versioning pengajuan)
 */
class SubmissionVersionException extends SubmissionTransitionException
{
    /**
     * Pengajuan aktif lain sudah ada untuk koperasi & modul yang sama.
     */
    public static function activeExists(Cooperative $cooperative, string $module): self
    {
        $label = SubmissionModules::labels()[$module] ?? $module;

        return new self(sprintf(
            'Masih ada pengajuan aktif untuk modul "%s" pada koperasi ini. Selesaikan atau arsipkan pengajuan tersebut sebelum membuat pengajuan baru.',
            $label,
        ));
    }

    /**
     * Pengajuan yang hendak dijadikan induk perbaikan bukan `needs_revision`.
     */
    public static function parentNotRevisable(Submission $submission): self
    {
        return new self(sprintf(
            'Pengajuan #%d berstatus "%s" tidak dapat diperbaiki. Hanya pengajuan berstatus "Perlu Perbaikan" yang dapat dibuatkan versi baru.',
            $submission->getKey(),
            $submission->statusLabel(),
        ));
    }

    /**
     * Pengajuan induk tidak lagi tersedia (mis. diarsipkan/di-soft-delete di tengah transaksi).
     */
    public static function parentUnavailable(Submission $submission): self
    {
        return new self(sprintf(
            'Pengajuan induk #%d tidak lagi tersedia sehingga tidak dapat diperbaiki.',
            $submission->getKey(),
        ));
    }

    /**
     * Pengajuan sudah memiliki versi turunan (sudah digantikan).
     */
    public static function alreadySuperseded(Submission $submission): self
    {
        return new self(sprintf(
            'Pengajuan #%d sudah memiliki versi perbaikan. Buka versi terbaru untuk melanjutkan.',
            $submission->getKey(),
        ));
    }
}
