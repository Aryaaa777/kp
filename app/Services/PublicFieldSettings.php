<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan field yang boleh tampil di direktori & profil publik (FR-191).
 *
 * Sumber batas maksimum tetap allowlist Q-008 di `config('koperasi.public.allowed_fields')`.
 * Admin hanya dapat memilih subset dari allowlist tersebut; nilai di luar allowlist
 * selalu dibuang, termasuk bila baris `settings` dimanipulasi langsung.
 *
 * Bila tabel `settings` belum ada (mis. migrasi belum dijalankan), service memakai
 * nilai default konfigurasi agar halaman publik tidak gagal.
 */
class PublicFieldSettings
{
    public const SETTING_KEY = 'public.fields';

    /**
     * Batas maksimum Q-008 yang ditanam di kode (defense-in-depth).
     *
     * Config `allowed_fields` hanya boleh mempersempit daftar ini; nilai di luar
     * daftar tetap ditolak walau config salah atau dimanipulasi. Menambah field
     * publik adalah keputusan kebijakan yang harus diubah di kode ini.
     *
     * @var list<string>
     */
    private const Q008_ALLOWED_FIELDS = [
        'nama',
        'nomor_identitas',
        'tanggal_berdiri',
        'jenis_koperasi',
        'status_operasional',
        'website',
    ];

    /**
     * Label ramah pengguna untuk UI admin.
     *
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'nama' => 'Nama koperasi',
        'nomor_identitas' => 'Nomor badan hukum',
        'tanggal_berdiri' => 'Tanggal berdiri',
        'jenis_koperasi' => 'Jenis koperasi',
        'status_operasional' => 'Status operasional',
        'website' => 'Situs web',
    ];

    /**
     * @return list<string>
     */
    public function allowedFields(): array
    {
        $configured = (array) config('koperasi.public.allowed_fields', self::Q008_ALLOWED_FIELDS);
        $allowed = [];

        foreach ($configured as $field) {
            if (
                is_string($field)
                && in_array($field, self::Q008_ALLOWED_FIELDS, true)
                && ! in_array($field, $allowed, true)
            ) {
                $allowed[] = $field;
            }
        }

        return $allowed === [] ? self::Q008_ALLOWED_FIELDS : $allowed;
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        $labels = [];

        foreach ($this->allowedFields() as $field) {
            $labels[$field] = self::FIELD_LABELS[$field] ?? $field;
        }

        return $labels;
    }

    /**
     * Pengaturan aktif: default konfigurasi, lalu ditimpa baris `settings` bila valid.
     *
     * @return array{fields: list<string>, show_region: bool, contact: array{email: bool, telephone: bool}}
     */
    public function current(): array
    {
        $defaults = $this->defaults();

        if (! Schema::hasTable((new Setting)->getTable())) {
            return $defaults;
        }

        $raw = Setting::query()->where('key', self::SETTING_KEY)->value('value');

        if (! is_string($raw) || trim($raw) === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return $defaults;
        }

        return $this->normalizeStored($decoded, $defaults);
    }

    /**
     * Simpan pilihan admin setelah dinormalisasi terhadap allowlist.
     *
     * @param  array<int, mixed>  $fields
     * @return array{fields: list<string>, show_region: bool, contact: array{email: bool, telephone: bool}}
     */
    public function save(array $fields, bool $showRegion, bool $contactEmail, bool $contactTelephone): array
    {
        $normalized = [
            'fields' => $this->normalizeFields($fields),
            'show_region' => $showRegion,
            'contact' => [
                'email' => $contactEmail,
                'telephone' => $contactTelephone,
            ],
        ];

        Setting::query()->updateOrCreate(
            ['key' => self::SETTING_KEY],
            ['value' => json_encode($normalized, JSON_THROW_ON_ERROR)],
        );

        return $normalized;
    }

    /**
     * @return array{fields: list<string>, show_region: bool, contact: array{email: bool, telephone: bool}}
     */
    private function defaults(): array
    {
        return [
            'fields' => $this->normalizeFields((array) config('koperasi.public.fields', [])),
            'show_region' => (bool) config('koperasi.public.show_region', true),
            'contact' => [
                'email' => (bool) config('koperasi.public.contact.email', true),
                'telephone' => (bool) config('koperasi.public.contact.telephone', true),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{fields: list<string>, show_region: bool, contact: array{email: bool, telephone: bool}}  $defaults
     * @return array{fields: list<string>, show_region: bool, contact: array{email: bool, telephone: bool}}
     */
    private function normalizeStored(array $data, array $defaults): array
    {
        $contact = is_array($data['contact'] ?? null) ? $data['contact'] : [];

        return [
            'fields' => array_key_exists('fields', $data)
                ? $this->normalizeFields((array) $data['fields'])
                : $defaults['fields'],
            'show_region' => array_key_exists('show_region', $data)
                ? $this->toBool($data['show_region'], $defaults['show_region'])
                : $defaults['show_region'],
            'contact' => [
                'email' => array_key_exists('email', $contact)
                    ? $this->toBool($contact['email'], $defaults['contact']['email'])
                    : $defaults['contact']['email'],
                'telephone' => array_key_exists('telephone', $contact)
                    ? $this->toBool($contact['telephone'], $defaults['contact']['telephone'])
                    : $defaults['contact']['telephone'],
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $fields
     * @return list<string>
     */
    private function normalizeFields(array $fields): array
    {
        $allowed = $this->allowedFields();
        $normalized = [];

        foreach ($allowed as $allowedField) {
            foreach ($fields as $field) {
                if (is_string($field) && $field === $allowedField && ! in_array($field, $normalized, true)) {
                    $normalized[] = $field;
                    break;
                }
            }
        }

        return $normalized;
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $parsed ?? $default;
        }

        return $default;
    }
}
