<?php

namespace App\Exceptions;

use App\Models\Submission;
use RuntimeException;

/**
 * Transisi status pengajuan yang tidak sah (PRD §12.2, AC-WF-05).
 *
 * Contoh: `draft` → `approved` tanpa melalui `submitted`/`under_review`.
 */
class SubmissionTransitionException extends RuntimeException
{
    public static function invalid(Submission $submission, string $to): self
    {
        return new self(sprintf(
            'Transisi status pengajuan tidak sah: %s → %s.',
            $submission->status,
            $to,
        ));
    }

    public static function notEditable(Submission $submission): self
    {
        return new self(sprintf(
            'Pengajuan berstatus "%s" tidak dapat diubah/dikirim.',
            $submission->status,
        ));
    }

    /**
     * Status di basis data sudah berubah (stale) ketika transisi hendak ditulis — penjaga
     * konkurensi (optimistic concurrency) untuk mencegah keputusan ganda.
     */
    public static function stale(Submission $submission, string $expected): self
    {
        return new self(sprintf(
            'Pengajuan telah berubah (status bukan "%s" lagi). Muat ulang halaman lalu coba kembali.',
            $expected,
        ));
    }

    /**
     * Koperasi target pengajuan tidak tersedia (mis. ter-soft delete) — perubahan tidak
     * boleh diterapkan (FR-143).
     */
    public static function targetUnavailable(Submission $submission): self
    {
        return new self('Koperasi target pengajuan tidak tersedia; perubahan tidak dapat diterapkan.');
    }
}
