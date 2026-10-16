<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model `cooperative_assets` — aset koperasi per periode (FR-100/101).
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS:
 * - Snapshot **tahunan** (Q-010).
 * - `quantity`, `acquisition_date`, `condition` adalah field minimum tambahan (belum
 *   ditetapkan Data Dictionary §11), ditandai provisional.
 * - Tidak ada integrasi akuntansi/perhitungan keuangan otomatis.
 *
 * @property int $id
 * @property int $cooperative_id
 * @property int $period_year
 * @property string $asset_type
 * @property string|null $asset_name
 * @property int|null $quantity
 * @property string $value
 * @property Carbon|null $acquisition_date
 * @property string|null $condition
 * @property string|null $description
 * @property int $recorded_by
 *
 * @see docs/DATA_DICTIONARY.md §11 (cooperative_assets)
 * @see docs/DECISIONS.md §10 (aturan provisional)
 */
class CooperativeAsset extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * `cooperative_id` dan `recorded_by` TIDAK fillable: ditetapkan di server.
     *
     * @var list<string>
     */
    protected $fillable = [
        'period_year',
        'asset_type',
        'asset_name',
        'quantity',
        'value',
        'acquisition_date',
        'condition',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_year' => 'integer',
            'quantity' => 'integer',
            'value' => 'decimal:2',
            'acquisition_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Cooperative, $this>
     */
    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Saran jenis aset (PROVISIONAL) dari konfigurasi. Kosong = input teks bebas.
     *
     * @return array<int, string>
     */
    public static function typeOptions(): array
    {
        return config('koperasi.asset_type_options', []);
    }

    /**
     * @return array<string, string>
     */
    public static function conditionOptions(): array
    {
        return config('koperasi.asset_condition_options', []);
    }

    public function conditionLabel(): ?string
    {
        if (blank($this->condition)) {
            return null;
        }

        return self::conditionOptions()[$this->condition] ?? $this->condition;
    }
}
