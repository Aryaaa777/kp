<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Services\AdminRegionScope;

/**
 * Pemeriksaan wilayah untuk Policy admin (FR-012, PROVISIONAL).
 *
 * Policy tetap menjadi lapisan otorisasi utama; middleware `admin-region` hanya
 * lapisan tambahan untuk route yang membawa resource. Saat scope nonaktif
 * (default), `allowsCooperative()` selalu `true` sehingga perilaku lama tidak
 * berubah.
 */
trait ChecksAdminRegion
{
    /**
     * Apakah admin boleh mengakses koperasi tertentu menurut wilayahnya.
     */
    protected function adminCanAccessCooperative(User $user, ?int $cooperativeId): bool
    {
        if (! $user->hasAnyRole(['admin_dinas', 'admin_wilayah'])) {
            return false;
        }

        return app(AdminRegionScope::class)->allowsCooperative($user, $cooperativeId);
    }
}
