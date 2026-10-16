<?php

namespace App\Services\Submissions;

use InvalidArgumentException;

/**
 * Registry modul pengajuan (FR-140) berbasis `config('koperasi.submission_modules')`.
 *
 * Menyatukan resolusi handler, daftar label modul, dan aturan validasi field agar dipakai
 * bersama oleh FormRequest, controller, dan `SubmissionService`.
 */
class SubmissionModules
{
    /**
     * @return array<string, array{label: string, description?: string, handler: class-string}>
     */
    public static function all(): array
    {
        return (array) config('koperasi.submission_modules', []);
    }

    /**
     * @return array<string, string> module => label
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::all() as $module => $config) {
            $labels[$module] = $config['label'] ?? $module;
        }

        return $labels;
    }

    /**
     * Kunci modul pertama (modul default untuk form pengajuan).
     */
    public static function defaultModule(): ?string
    {
        $keys = array_keys(self::all());

        return $keys[0] ?? null;
    }

    public static function has(string $module): bool
    {
        return array_key_exists($module, self::all());
    }

    public static function handlerOrNull(string $module): ?SubmissionModuleHandler
    {
        $class = self::all()[$module]['handler'] ?? null;

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $handler = app($class);

        return $handler instanceof SubmissionModuleHandler ? $handler : null;
    }

    public static function handler(string $module): SubmissionModuleHandler
    {
        $handler = self::handlerOrNull($module);

        if ($handler === null) {
            throw new InvalidArgumentException("Modul pengajuan tidak dikenal: {$module}.");
        }

        return $handler;
    }

    /**
     * @return array<string, array{label: string, type: string, rules: array<int, mixed>}>
     */
    public static function fields(string $module): array
    {
        return self::handlerOrNull($module)?->fields() ?? [];
    }

    /**
     * Aturan validasi field untuk FormRequest (nilai kosong diperbolehkan; `required` mengikuti
     * definisi field).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function fieldRules(string $module): array
    {
        $rules = [];

        foreach (self::fields($module) as $field => $definition) {
            $rules[$field] = array_merge(['nullable'], $definition['rules'] ?? []);
        }

        return $rules;
    }
}
