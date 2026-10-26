<?php

namespace App\Rules;

use App\Models\User;
use App\Services\AdminRegionScope;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Memastikan admin wilayah hanya memilih wilayah dalam kewenangannya (FR-012).
 *
 * Saat pembatasan wilayah nonaktif (default), aturan ini tidak membatasi apa pun.
 */
class WithinAdminRegion implements ValidationRule
{
    public function __construct(private readonly ?User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $scope = app(AdminRegionScope::class);

        if (! $scope->appliesTo($this->user)) {
            return;
        }

        $allowed = $scope->regionIds($this->user);

        if ($allowed === null) {
            return;
        }

        if ($value === null || $value === '' || ! in_array((int) $value, $allowed, true)) {
            $fail('Wilayah yang dipilih berada di luar kewenangan Admin Wilayah.');
        }
    }
}
