<?php

namespace App\Models;

use Database\Factories\SubmissionItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model `submission_items` — detail perubahan field-level (FR-150).
 *
 * Menyimpan `field`, `old_value`, `new_value` agar data resmi tidak tertimpa sebelum
 * pengajuan disetujui (BR-014, PRD §12.3).
 *
 * @property int $id
 * @property int $submission_id
 * @property string $field
 * @property string|null $old_value
 * @property string|null $new_value
 *
 * @see docs/DATA_DICTIONARY.md §15 (submission_items)
 */
class SubmissionItem extends Model
{
    /** @use HasFactory<SubmissionItemFactory> */
    use HasFactory;

    /**
     * `submission_id` ditetapkan via relasi (server-side).
     *
     * @var list<string>
     */
    protected $fillable = [
        'field',
        'old_value',
        'new_value',
    ];

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
