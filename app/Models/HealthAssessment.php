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
 * - Formula lima komponen (FR-082) adalah **aturan sementara** yang dapat diubah lewat
 *   `config('koperasi.health_assessment')`; bukan formula resmi dinas (Q-007).
 * - Skor & predikat manual tetap dimungkinkan bila `auto_calculated = false`.
 * - `status = final` hanyalah penanda internal, bukan klasifikasi/pengesahan resmi dinas.
 *
 * @property int $id
 * @property int $cooperative_id
 * @property int $period_year
 * @property string $period
 * @property Carbon $assessment_date
 * @property string|null $component_permodalan
 * @property string|null $component_likuiditas
 * @property string|null $component_rentabilitas
 * @property string|null $component_kualitas_aset
 * @property string|null $component_efisiensi
 * @property string|null $score
 * @property string|null $predicate
 * @property bool $auto_calculated
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
        'component_permodalan',
        'component_likuiditas',
        'component_rentabilitas',
        'component_kualitas_aset',
        'component_efisiensi',
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
            'component_permodalan' => 'decimal:2',
            'component_likuiditas' => 'decimal:2',
            'component_rentabilitas' => 'decimal:2',
            'component_kualitas_aset' => 'decimal:2',
            'component_efisiensi' => 'decimal:2',
            'score' => 'decimal:2',
            'auto_calculated' => 'boolean',
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

    /**
     * Label komponen formula kesehatan (PROVISIONAL).
     *
     * @return array<string, string>
     */
    public static function componentLabels(): array
    {
        return (array) config('koperasi.health_assessment.components', []);
    }

    /**
     * Nama kolom komponen untuk setiap kunci komponen.
     *
     * @return array<string, string>
     */
    public static function componentFields(): array
    {
        $fields = [];

        foreach (array_keys(self::componentLabels()) as $key) {
            $fields[$key] = 'component_'.$key;
        }

        return $fields;
    }

    /**
     * Nilai komponen yang tersimpan.
     *
     * @return array<string, float|null>
     */
    public function componentScores(): array
    {
        $scores = [];

        foreach (self::componentFields() as $key => $field) {
            $value = $this->getAttribute($field);
            $scores[$key] = $value === null ? null : (float) $value;
        }

        return $scores;
    }

    /**
     * Hitung skor & predikat dari komponen (PROVISIONAL).
     *
     * Bobot dinormalisasi agar perubahan konfigurasi tidak menghasilkan skor > 100.
     * Mengembalikan `false` bila ada komponen yang belum diisi.
     */
    public function applyAutoCalculation(): bool
    {
        $scores = $this->componentScores();
        $weights = (array) config('koperasi.health_assessment.weights', []);

        if ($scores === [] || in_array(null, $scores, true)) {
            return false;
        }

        $weighted = 0.0;
        $totalWeight = 0.0;

        foreach ($scores as $key => $score) {
            $weight = (float) ($weights[$key] ?? 0);

            if ($weight <= 0) {
                continue;
            }

            $weighted += ((float) $score) * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return false;
        }

        $score = round($weighted / $totalWeight, 2);

        $this->score = (string) $score;
        $this->predicate = self::predicateForScore($score);
        $this->auto_calculated = true;

        return true;
    }

    /**
     * Predikat berdasarkan ambang konfigurasi (PROVISIONAL).
     */
    public static function predicateForScore(float $score): string
    {
        $predicates = (array) config('koperasi.health_assessment.predicates', []);

        usort($predicates, fn (array $a, array $b): int => ((float) ($b['min'] ?? 0)) <=> ((float) ($a['min'] ?? 0)));

        foreach ($predicates as $predicate) {
            if ($score >= (float) ($predicate['min'] ?? 0)) {
                return (string) ($predicate['label'] ?? 'Tidak Sehat');
            }
        }

        return 'Tidak Sehat';
    }
}
