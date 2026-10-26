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
 * - Indikator resmi tata kelola belum ada; checklist kepatuhan (FR-091) adalah
 *   **aturan sementara** yang dapat diubah lewat `config('koperasi.governance_compliance')`.
 * - `status = final` penanda internal, bukan pengesahan resmi.
 *
 * @property int $id
 * @property int $cooperative_id
 * @property int $period_year
 * @property string $period
 * @property int|null $meeting_count
 * @property bool|null $rat_on_time
 * @property bool|null $financial_report_available
 * @property bool|null $basic_documents_complete
 * @property string|null $governance_score
 * @property string|null $summary
 * @property string $status
 * @property string|null $compliance_status
 * @property string|null $compliance_notes
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
        'rat_on_time',
        'financial_report_available',
        'basic_documents_complete',
        'governance_score',
        'summary',
        'status',
        'compliance_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_count' => 'integer',
            'rat_on_time' => 'boolean',
            'financial_report_available' => 'boolean',
            'basic_documents_complete' => 'boolean',
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

    /**
     * Label status kepatuhan (PROVISIONAL).
     *
     * @return array<string, string>
     */
    public static function complianceStatusLabels(): array
    {
        return (array) config('koperasi.governance_compliance.statuses', [
            'patuh' => 'Patuh',
            'deviasi' => 'Deviasi',
        ]);
    }

    /**
     * Evaluasi checklist kepatuhan (PROVISIONAL FR-091).
     *
     * Semua checklist dan jumlah rapat wajib terisi agar status dapat dihitung.
     * Bila ada yang kosong, status dikosongkan (belum dapat dievaluasi).
     */
    public function evaluateCompliance(): bool
    {
        if ($this->rat_on_time === null
            || $this->financial_report_available === null
            || $this->basic_documents_complete === null
            || $this->meeting_count === null) {
            $this->compliance_status = null;

            return false;
        }

        $minimumMeetings = (int) config('koperasi.governance_compliance.min_meetings_per_year', 2);

        $compliant = $this->rat_on_time
            && $this->financial_report_available
            && $this->basic_documents_complete
            && $this->meeting_count >= $minimumMeetings;

        $this->compliance_status = $compliant ? 'patuh' : 'deviasi';

        return true;
    }

    public function complianceStatusLabel(): ?string
    {
        if ($this->compliance_status === null) {
            return null;
        }

        return self::complianceStatusLabels()[$this->compliance_status] ?? $this->compliance_status;
    }

    /**
     * Catatan wajib bila hasil evaluasi adalah deviasi.
     */
    public function requiresComplianceNotes(): bool
    {
        return $this->compliance_status === 'deviasi' && blank($this->compliance_notes);
    }
}
