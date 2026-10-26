<?php

namespace App\Models;

use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'name', 'type', 'parent_id'];

    /**
     * Wilayah induk (hierarki).
     *
     * @return BelongsTo<Region, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'parent_id');
    }

    /**
     * @return HasMany<Region, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Region::class, 'parent_id');
    }

    /**
     * @return HasMany<Cooperative, $this>
     */
    public function cooperatives(): HasMany
    {
        return $this->hasMany(Cooperative::class);
    }

    /**
     * Cari leluhur (termasuk dirinya sendiri) berdasarkan tipe wilayah.
     *
     * Dipakai formulir admin untuk memilih rantai provinsi → kabupaten/kota →
     * kecamatan → desa/kelurahan saat mengedit data lama.
     */
    public function ancestorOfType(string $type): ?self
    {
        $node = $this;

        while ($node !== null) {
            if ($node->type === $type) {
                return $node;
            }

            $node = $node->parent;
        }

        return null;
    }

    /**
     * Jalur wilayah dari provinsi sampai wilayah ini, mis. "Jawa Barat > Kota Bandung".
     *
     * Dibatasi kedalaman dan mendeteksi siklus `parent_id` agar tidak berputar
     * tanpa henti bila data referensi rusak.
     */
    public function fullPath(string $separator = ' > '): string
    {
        $names = [];
        $seen = [];
        $node = $this;

        while ($node !== null && count($names) < 8) {
            if (isset($seen[$node->id])) {
                break;
            }

            $seen[$node->id] = true;
            array_unshift($names, (string) $node->name);
            $node = $node->parent;
        }

        return implode($separator, $names);
    }

    /**
     * Semua ID wilayah di bawah (dan termasuk) wilayah ini.
     *
     * Dipakai filter agar memilih kabupaten/kota juga mencakup kecamatan dan
     * desa/kelurahan di bawahnya. Satu query untuk seluruh tabel; cukup untuk
     * skala referensi wilayah Indonesia.
     *
     * @return list<int>
     */
    public static function descendantIdsOf(int $id): array
    {
        /** @var array<int, list<int>> $childrenByParent */
        $childrenByParent = [];

        foreach (self::query()->get(['id', 'parent_id']) as $row) {
            $parentId = $row->parent_id === null ? 0 : (int) $row->parent_id;
            $childrenByParent[$parentId][] = (int) $row->id;
        }

        $visited = [];
        $stack = [$id];

        while ($stack !== []) {
            $current = array_pop($stack);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return array_keys($visited);
    }
}
