<?php

namespace App\Models;

use Database\Factories\SubmissionReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model `submission_reviews` — log transisi & catatan tinjauan (FR-141/142, PRD §12.4).
 *
 * Append-only: hanya memiliki `created_at`.
 *
 * @property int $id
 * @property int $submission_id
 * @property int $user_id
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $note
 * @property Carbon|null $created_at
 *
 * @see docs/DATA_DICTIONARY.md §16 (submission_reviews)
 */
class SubmissionReview extends Model
{
    /** @use HasFactory<SubmissionReviewFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * `submission_id` & `user_id` ditetapkan service (server-side), bukan dari input pengguna.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'from_status',
        'to_status',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Label status bahasa Indonesia untuk tampilan.
     */
    public function toStatusLabel(): string
    {
        return Submission::STATUS_LABELS[$this->to_status] ?? (string) $this->to_status;
    }

    public function fromStatusLabel(): string
    {
        return $this->from_status === null
            ? '—'
            : (Submission::STATUS_LABELS[$this->from_status] ?? $this->from_status);
    }
}
