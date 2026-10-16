<?php

namespace App\Services\Submissions;

use App\Models\Submission;
use App\Models\User;

/**
 * Kontrak handler modul pengajuan (FR-140/143).
 *
 * Setiap modul mendefinisikan: daftar field yang boleh diajukan (whitelist Q-006 + aturan
 * validasinya), nilai resmi saat ini, dan cara menerapkan perubahan ke data resmi.
 */
interface SubmissionModuleHandler
{
    /**
     * Kunci modul (harus sama dengan kunci pada `config('koperasi.submission_modules')`).
     */
    public function module(): string;

    /**
     * Field yang boleh diajukan beserta label, tipe input, dan aturan validasi.
     *
     * @return array<string, array{label: string, type: string, rules: array<int, mixed>}>
     */
    public function fields(): array;

    /**
     * Nilai resmi saat ini untuk tiap field (key = nama field).
     *
     * @return array<string, string|null>
     */
    public function currentValues(Submission $submission): array;

    /**
     * Terapkan nilai usulan ke data resmi (dipanggil di dalam DB transaction saat approve).
     *
     * @param  array<string, string|null>  $values
     */
    public function apply(Submission $submission, array $values, User $actor): void;
}
