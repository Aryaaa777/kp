<?php

namespace App\Services;

use App\Models\Cooperative;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pembatasan data berdasarkan wilayah admin (FR-012, PROVISIONAL).
 *
 * Aturan ini **nonaktif secara default** (`config('koperasi.admin_region_scope.enabled')`)
 * karena pembagian kewenangan wilayah belum dikonfirmasi dinas (Q-005).
 *
 * Saat aktif:
 * - `admin_dinas` = super admin (tanpa batas);
 * - `admin_wilayah` hanya melihat koperasi pada wilayahnya **dan seluruh turunannya**;
 * - `admin_wilayah` tanpa `region_id` tidak dapat mengakses data koperasi.
 *
 * Pembatasan ini adalah lapisan tambahan di server; Policy tetap memeriksa peran.
 */
final class AdminRegionScope
{
    /**
     * Cache ID wilayah per pengguna agar satu request tidak mengulang traversal.
     *
     * @var array<int, list<int>>
     */
    private array $cache = [];

    public function enabled(): bool
    {
        return (bool) config('koperasi.admin_region_scope.enabled', false);
    }

    public function appliesTo(?User $user): bool
    {
        return $user !== null
            && $this->enabled()
            && $user->hasRole('admin_wilayah');
    }

    /**
     * ID wilayah yang boleh diakses.
     *
     * `null` = tidak ada pembatasan; `[]` = tidak ada wilayah yang boleh diakses.
     *
     * @return list<int>|null
     */
    public function regionIds(?User $user): ?array
    {
        if (! $this->appliesTo($user)) {
            return null;
        }

        $userId = (int) $user->getKey();

        if (array_key_exists($userId, $this->cache)) {
            return $this->cache[$userId];
        }

        $regionId = $user->region_id;

        return $this->cache[$userId] = $regionId === null
            ? []
            : Region::descendantIdsOf((int) $regionId);
    }

    /**
     * Apakah koperasi tertentu boleh diakses pengguna.
     */
    public function allowsCooperative(?User $user, ?int $cooperativeId): bool
    {
        $regionIds = $this->regionIds($user);

        if ($regionIds === null) {
            return true;
        }

        if ($regionIds === [] || $cooperativeId === null) {
            return false;
        }

        $regionId = Cooperative::withTrashed()
            ->whereKey($cooperativeId)
            ->value('region_id');

        return $regionId !== null && in_array((int) $regionId, $regionIds, true);
    }

    /**
     * Terapkan pembatasan pada query yang memiliki kolom wilayah langsung.
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public function applyToCooperatives(Builder $query, ?User $user, string $column = 'region_id'): Builder
    {
        $regionIds = $this->regionIds($user);

        if ($regionIds === null) {
            return $query;
        }

        if ($regionIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $regionIds);
    }

    /**
     * Terapkan pembatasan pada query yang terhubung ke koperasi lewat relasi.
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    public function applyToRelated(
        Builder $query,
        ?User $user,
        string $relation = 'cooperative',
        string $column = 'region_id',
    ): Builder {
        $regionIds = $this->regionIds($user);

        if ($regionIds === null) {
            return $query;
        }

        if ($regionIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas($relation, fn (Builder $related) => $related->whereIn($column, $regionIds));
    }
}
