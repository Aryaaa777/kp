<?php

namespace App\Services\Submissions\Handlers;

use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\User;
use App\Services\Submissions\SubmissionModuleHandler;

/**
 * Handler modul `koperasi_induk` (FR-042) — usul perubahan data induk koperasi oleh pengelola.
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS (Q-006): whitelist field di bawah bersifat sementara.
 * Field yang **tidak** boleh diajukan pengelola (kewenangan admin/otoritatif):
 * `nomor_identitas` (identitas unik), `region_id` (referensi wilayah belum tersedia — Q-012),
 * `status_verifikasi`, `is_published`, `slug` (dibuat sistem).
 *
 * @see docs/DECISIONS.md §12 (keputusan F5)
 */
class KoperasiIndukHandler implements SubmissionModuleHandler
{
    public function module(): string
    {
        return 'koperasi_induk';
    }

    public function fields(): array
    {
        return [
            'nama' => [
                'label' => 'Nama koperasi',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:200'],
            ],
            'jenis_koperasi' => [
                'label' => 'Jenis koperasi',
                'type' => 'text',
                'rules' => ['required', 'string', 'max:100'],
            ],
            'bentuk_koperasi' => [
                'label' => 'Bentuk koperasi',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'max:100'],
            ],
            'tanggal_berdiri' => [
                'label' => 'Tanggal berdiri',
                'type' => 'date',
                'rules' => ['nullable', 'date', 'before_or_equal:today'],
            ],
            'alamat' => [
                'label' => 'Alamat',
                'type' => 'textarea',
                'rules' => ['nullable', 'string', 'max:1000'],
            ],
            'telephone' => [
                'label' => 'Telepon',
                'type' => 'text',
                'rules' => ['nullable', 'string', 'max:30'],
            ],
            'email' => [
                'label' => 'Email',
                'type' => 'email',
                'rules' => ['nullable', 'email', 'max:150'],
            ],
            'website' => [
                'label' => 'Website',
                'type' => 'url',
                'rules' => ['nullable', 'url', 'max:200'],
            ],
        ];
    }

    public function currentValues(Submission $submission): array
    {
        $cooperative = $submission->cooperative;

        if (! $cooperative instanceof Cooperative) {
            return [];
        }

        $values = [];

        foreach (array_keys($this->fields()) as $field) {
            $values[$field] = $this->normalize($cooperative->{$field});
        }

        return $values;
    }

    public function apply(Submission $submission, array $values, User $actor): void
    {
        $cooperative = $submission->cooperative;

        if (! $cooperative instanceof Cooperative) {
            return;
        }

        // Defense-in-depth (Q-006): hanya field whitelist yang boleh diterapkan, walau
        // pemanggil sudah menyaring. Field tak berizin diabaikan secara aman.
        $allowed = array_keys($this->fields());
        $safe = array_intersect_key($values, array_flip($allowed));

        // `updated_by` bukan bagian dari usulan — ditetapkan server-side.
        $cooperative->update($safe + ['updated_by' => $actor->id]);
    }

    private function normalize(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }
}
