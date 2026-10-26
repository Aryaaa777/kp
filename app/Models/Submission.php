<?php

namespace App\Models;

use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Model `submissions` — pengajuan perubahan data resmi (FR-140…143).
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS:
 * - **Q-004** (prosedur verifikasi multi-tahap): MVP memakai verifikasi **satu tingkat** oleh
 *   `admin_dinas`. Bila dinas menetapkan verifikasi berjenjang, tambahkan tahap/approver.
 * - **Q-006** (field yang boleh diajukan pengelola): whitelist modul & field bersifat sementara
 *   (`config('koperasi.submission_modules')`).
 *
 * Status mengikuti matriks transisi PRD §12.2.
 *
 * **Versioning (FR-031/FR-142, keputusan Tahap 63 / D-1 opsi B).** Revisi atas pengajuan
 * `needs_revision` **tidak** menyunting record yang sama, melainkan membuat record pengajuan
 * **baru** (`status = draft`) yang menunjuk versi induk lewat `parent_submission_id`. Versi
 * lama tetap tersimpan utuh (termasuk `submission_items` & `submission_reviews`) sebagai
 * riwayat. Karena itu pengajuan `needs_revision` yang sudah punya versi turunan dianggap
 * **sudah digantikan** (`isSuperseded()`) dan tidak lagi aktif.
 *
 * @property int $id
 * @property int|null $parent_submission_id
 * @property int $cooperative_id
 * @property int $submitted_by
 * @property string $module
 * @property string|null $title
 * @property string $status
 * @property string|null $reason
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $applied_at
 *
 * @see docs/DATA_DICTIONARY.md §14 (submissions)
 * @see docs/PRD.md §12 (workflow pengajuan)
 * @see docs/DECISIONS.md §12 (keputusan F5)
 */
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Status pengajuan (PRD §12.1).
     *
     * @var list<string>
     */
    public const STATUSES = [
        'draft',
        'submitted',
        'under_review',
        'needs_revision',
        'approved',
        'rejected',
        'archived',
    ];

    /**
     * Label Indonesia untuk status (PRD §12.1).
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'draft' => 'Draft',
        'submitted' => 'Diajukan',
        'under_review' => 'Dalam Pemeriksaan',
        'needs_revision' => 'Perlu Perbaikan',
        'approved' => 'Disetujui',
        'rejected' => 'Ditolak',
        'archived' => 'Diarsipkan',
    ];

    /**
     * Matriks transisi status yang sah (PRD §12.2). Transisi di luar tabel ini ditolak.
     *
     * `needs_revision` **tidak** bertransisi ke `submitted` pada record yang sama: perbaikan
     * membuat **versi pengajuan baru** (`parent_submission_id`) — lihat `docs/DECISIONS.md` §17.1.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'draft' => ['submitted', 'archived'],
        'submitted' => ['under_review'],
        'under_review' => ['needs_revision', 'rejected', 'approved'],
        'needs_revision' => ['archived'],
        'approved' => ['archived'],
        'rejected' => ['archived'],
        'archived' => [],
    ];

    /**
     * Status yang masih **aktif** (belum selesai/terminal). Dipakai untuk mencegah beberapa
     * pengajuan yang saling bertentangan pada koperasi & modul yang sama. Pengajuan
     * `needs_revision` yang sudah digantikan versi turunan **tidak** aktif (lihat
     * `isActive()`).
     *
     * @var list<string>
     */
    public const ACTIVE_STATUSES = ['draft', 'submitted', 'under_review', 'needs_revision'];

    /**
     * Status yang masih dapat **diubah langsung** oleh pengelola.
     *
     * Sejak versioning (D-1), `needs_revision` **bukan** lagi dapat disunting di tempat:
     * perbaikan dilakukan dengan membuat versi pengajuan baru (`parent_submission_id`).
     *
     * @var list<string>
     */
    public const EDITABLE_STATUSES = ['draft'];

    /**
     * `cooperative_id` & `submitted_by` sengaja TIDAK fillable (ditetapkan server-side, anti-IDOR).
     *
     * @var list<string>
     */
    protected $fillable = [
        'module',
        'title',
        'status',
        'reason',
        'reviewed_by',
        'reviewed_at',
        'applied_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
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
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<SubmissionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SubmissionItem::class);
    }

    /**
     * @return HasMany<SubmissionReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(SubmissionReview::class);
    }

    /**
     * Versi induk (pengajuan yang diperbaiki oleh versi ini). `null` untuk versi awal.
     *
     * @return BelongsTo<Submission, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_submission_id');
    }

    /**
     * Versi turunan (perbaikan) yang menunjuk pengajuan ini sebagai induk.
     *
     * @return HasMany<Submission, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_submission_id');
    }

    /**
     * Apakah pengajuan ini merupakan versi perbaikan (punya induk).
     */
    public function isRevision(): bool
    {
        return $this->parent_submission_id !== null;
    }

    /**
     * Apakah pengajuan ini sudah digantikan oleh versi turunan (`needs_revision` → versi baru).
     */
    public function isSuperseded(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Apakah pengajuan masih aktif (belum selesai/terminal **dan** belum digantikan).
     */
    public function isActive(): bool
    {
        if (! in_array($this->status, self::ACTIVE_STATUSES, true)) {
            return false;
        }

        return ! ($this->status === 'needs_revision' && $this->isSuperseded());
    }

    /**
     * Apakah pengajuan ini dapat menjadi induk perbaikan (versi baru) — `needs_revision`
     * yang belum digantikan.
     */
    public function isRevisable(): bool
    {
        return $this->status === 'needs_revision' && ! $this->isSuperseded();
    }

    /**
     * Apakah transisi `$to` diizinkan dari status saat ini.
     */
    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /**
     * Apakah pengajuan masih dapat diedit/dikirim pengelola.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    /**
     * Label status dalam bahasa Indonesia.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    /**
     * Status yang dapat dipilih pada filter daftar (kecuali `archived`). Status `archived`
     * bersifat terminal/soft delete sehingga tidak muncul di daftar default.
     *
     * @return array<string, string>
     */
    public static function filterableStatusLabels(): array
    {
        return array_diff_key(self::STATUS_LABELS, ['archived' => true]);
    }
}
