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
}
