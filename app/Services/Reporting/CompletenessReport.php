<?php

namespace App\Services\Reporting;

use App\Models\Cooperative;
use App\Models\Submission;
use App\Services\Submissions\SubmissionModules;

/**
 * Indikator kelengkapan data pengelola (FR-030) — **read-only**.
 *
 * **Rumus (keputusan pemilik Tahap 63 / D-2 opsi B).** Kelengkapan dihitung **per field** atas
 * **field yang benar-benar menjadi kewenangan pengelola**, yaitu whitelist modul pengajuan
 * (`config('koperasi.submission_modules')` → handler `koperasi_induk`). Sumber daftar field
 * adalah kode handler itu sendiri, sehingga rumus tidak dapat menyimpang dari aturan validasi
 * pengajuan (Q-006).
 *
 * - `percentage` = field wewenang pengelola yang terisi / seluruh field wewenang pengelola.
 * - Field **wajib** pada wewenang pengelola dilaporkan terpisah (`required_filled` /
 *   `required_total`) agar kesesuaian dengan AC FR-030 ("kelengkapan field wajib") tetap terlihat.
 * - Modul yang **dikelola admin dinas** (perizinan, pengurus, kesehatan, tata kelola, aset,
 *   anggota) **tidak** dihitung dalam persentase — hanya ditampilkan sebagai informasi
 *   (pengelola hanya membaca). Ini menggantikan asumsi lama "7 modul berbobot setara", yang
 *   berpotensi menimbulkan persepsi tanggung jawab yang keliru pada pengelola.
 *
 * [ASUMSI] PRD FR-030 tidak menetapkan rumus pembobotan; daftar field wewenang pengelola masih
 * menunggu konfirmasi (Q-006). Bila dinas menetapkan rumus/field wajib resmi, angka ini wajib
 * ditinjau ulang.
 *
 * @see docs/DECISIONS.md §17.2 (D-2)
 */
class CompletenessReport
{
    /**
     * Modul yang dikelola **admin dinas** (pengelola hanya membaca) — ditampilkan sebagai
     * informasi dan **tidak** dihitung dalam persentase kelengkapan pengelola.
     *
     * @var array<string, array{key: string, label: string, route: string, relation: string}>
     */
    public const ADMIN_MODULES = [
        'perizinan' => ['key' => 'perizinan', 'label' => 'Perizinan & Dokumen', 'route' => 'pengelola.izin.index', 'relation' => 'licenses'],
        'pengurus' => ['key' => 'pengurus', 'label' => 'Pengurus', 'route' => 'pengelola.pengurus.index', 'relation' => 'officers'],
        'kesehatan' => ['key' => 'kesehatan', 'label' => 'Penilaian Kesehatan', 'route' => 'pengelola.kesehatan.index', 'relation' => 'healthAssessments'],
        'tata_kelola' => ['key' => 'tata_kelola', 'label' => 'Tata Kelola', 'route' => 'pengelola.tata-kelola.index', 'relation' => 'governanceRecords'],
        'aset' => ['key' => 'aset', 'label' => 'Aset', 'route' => 'pengelola.aset.index', 'relation' => 'assets'],
        'anggota' => ['key' => 'anggota', 'label' => 'Riwayat Keanggotaan', 'route' => 'pengelola.anggota.index', 'relation' => 'memberHistories'],
    ];

    /**
     * Field yang dapat diisi/diusulkan pengelola (whitelist modul pengajuan — Q-006).
     *
     * @return array<string, array{label: string, type: string, rules: array<int, mixed>}>
     */
    public static function pengelolaFields(): array
    {
        $fields = [];

        foreach (array_keys(SubmissionModules::all()) as $module) {
            $fields += SubmissionModules::fields($module);
        }

        return $fields;
    }

    /**
     * Field **wajib** pada wewenang pengelola (subset `pengelolaFields()` yang ber-`required`).
     *
     * @return list<string>
     */
    public static function pengelolaRequiredFields(): array
    {
        $required = [];

        foreach (self::pengelolaFields() as $field => $definition) {
            if (in_array('required', $definition['rules'] ?? [], true)) {
                $required[] = $field;
            }
        }

        return $required;
    }

    /**
     * Susun indikator kelengkapan untuk satu koperasi (tanpa N+1: `loadCount`).
     *
     * @return array{
     *     percentage: int,
     *     filled: int,
     *     total: int,
     *     required_filled: int,
     *     required_total: int,
     *     modules: list<array{key: string, label: string, route: string, complete: bool, detail: string}>,
     *     read_only: list<array{key: string, label: string, route: string, count: int, complete: bool, detail: string}>
     * }
     */
    public function build(Cooperative $cooperative): array
    {
        $cooperative->loadCount(array_column(self::ADMIN_MODULES, 'relation'));

        $fields = self::pengelolaFields();
        $values = $this->pengelolaValues($cooperative);

        $filled = 0;

        foreach (array_keys($fields) as $field) {
            if (filled($values[$field] ?? null)) {
                $filled++;
            }
        }

        $total = count($fields);
        $required = self::pengelolaRequiredFields();
        $requiredFilled = count(array_filter(
            $required,
            static fn (string $field): bool => filled($values[$field] ?? null),
        ));

        return [
            'percentage' => $total > 0 ? (int) round($filled / $total * 100) : 0,
            'filled' => $filled,
            'total' => $total,
            'required_filled' => $requiredFilled,
            'required_total' => count($required),
            'modules' => [[
                'key' => 'data_induk',
                'label' => 'Data Induk Koperasi',
                'route' => 'pengelola.profil',
                'complete' => $total > 0 && $filled === $total,
                'detail' => $filled.'/'.$total.' field profil terisi',
            ]],
            'read_only' => $this->readOnlyModules($cooperative),
        ];
    }

    /**
     * Modul kewenangan admin dinas (informasi saja, tidak dihitung).
     *
     * @return list<array{key: string, label: string, route: string, count: int, complete: bool, detail: string}>
     */
    private function readOnlyModules(Cooperative $cooperative): array
    {
        $modules = [];

        foreach (self::ADMIN_MODULES as $module) {
            $count = (int) $cooperative->{$module['relation'].'_count'};

            $modules[] = [
                'key' => $module['key'],
                'label' => $module['label'],
                'route' => $module['route'],
                'count' => $count,
                'complete' => $count > 0,
                'detail' => $count.' catatan · dikelola admin dinas',
            ];
        }

        return $modules;
    }

    /**
     * Nilai resmi koperasi untuk field wewenang pengelola (via handler modul pengajuan).
     *
     * @return array<string, string|null>
     */
    private function pengelolaValues(Cooperative $cooperative): array
    {
        $values = [];

        foreach (array_keys(SubmissionModules::all()) as $module) {
            $handler = SubmissionModules::handlerOrNull($module);

            if ($handler === null) {
                continue;
            }

            $submission = new Submission(['module' => $module]);
            $submission->cooperative_id = $cooperative->getKey();
            $submission->setRelation('cooperative', $cooperative);

            $values += $handler->currentValues($submission);
        }

        return $values;
    }
}
