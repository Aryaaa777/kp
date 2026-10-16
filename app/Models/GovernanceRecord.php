<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model `governance_records` — data tata kelola koperasi (FR-090).
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS:
 * - Periodisitas dibatasi **tahunan** (Q-010).
 * - Indikator resmi tata kelola belum ada; hanya `meeting_count` (contoh) & `summary`.
 * - `status = final` penanda internal, bukan pengesahan resmi.
 *
 * @property int $id
 * @property int $cooperative_id
 * @property int $period_year
 * @property string $period
 * @property int|null $meeting_count
 * @property string|null $governance_score
 * @property string|null $summary
 * @property string $status
 * @property int $recorded_by
 *
 * @see docs/DATA_DICTIONARY.md §10 (governance_records)
 * @see docs/DECISIONS.md §10 (aturan provisional)
 */
class GovernanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    public const STATUSES = ['draft', 'final'];

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'draft' => 'Draft',
        'final' => 'Final (internal)',
    ];

    /**
     * `cooperative_id` dan `recorded_by` TIDAK fillable: ditetapkan di server.
     *
     * @var list<string>
     */
    protected $fillable = [
        'period_year',
        'period',
        'meeting_count',
        'governance_score',
        'summary',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_count' => 'integer',
            'governance_score' => 'decimal:2',
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

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * @return array<string, string>
     */
    public static function periodOptions(): array
    {
        return config('koperasi.period_options', ['annual' => 'Tahunan']);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return self::STATUS_LABELS;
    }
}
