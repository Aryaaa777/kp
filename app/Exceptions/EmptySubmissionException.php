<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pengajuan tanpa perubahan field apa pun (tidak ada `submission_items`).
 *
 * Pengajuan kosong tidak bermakna dan ditolak (minimal satu field berbeda dari data resmi).
 */
class EmptySubmissionException extends RuntimeException
{
    public static function noChanges(): self
    {
        return new self('Tidak ada perubahan yang diajukan. Ubah minimal satu field sebelum menyimpan/mengirim.');
    }
}
