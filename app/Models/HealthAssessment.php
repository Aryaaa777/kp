<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model `health_assessments` — penilaian kesehatan koperasi (FR-080/081).
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS:
 * - Periodisitas dibatasi **tahunan** (Q-010).
 * - Skor & predikat adalah **input manual opsional**; tidak ada formula resmi (Q-007).
 * - `status = final` hanyalah penanda internal, bukan klasifikasi/pengesahan resmi dinas.
 *
 * @property int $id
 * @property int $cooperative_id
 * @property int $period_year
 * @property string $period
 * @property Carbon $assessment_date
 * @property string|null $score
 * @property string|null $predicate
 * @property int|null $assessor_id
 * @property string|null $notes
 * @property string $status
 *
 * @see docs/DATA_DICTIONARY.md §9 (health_assessments)
 * @see docs/DECISIONS.md §10 (aturan provisional)
 */
class HealthAssessment extends Model
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
     * `cooperative_id` dan `assessor_id` sengaja TIDAK fillable: ditetapkan di server,
     * bukan dari input klien (mencegah mass assignment / IDOR).
     *
     * @var list<string>
     */
    protected $fillable = [
        'period_year',
        'period',
        'assessment_date',
        'score',
        'predicate',
        'notes',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'score' => 'decimal:2',
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
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessor_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Opsi periode (PROVISIONAL) dari konfigurasi.
     *
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
