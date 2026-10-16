<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model `member_histories` — riwayat keanggotaan berbasis kejadian (FR-110/111).
 *
 * PROVISIONAL — MENUNGGU KONFIRMASI DINAS:
 * - Pencatatan **per kejadian/perubahan**, bukan rekap tahunan (menyimpang dari usulan awal
 *   Data Dictionary §12). Lihat `docs/DECISIONS.md` §10.
 * - TIDAK menyimpan data anggota individual / NIK / data pribadi anggota.
 *
 * @property int $id
 * @property int $cooperative_id
 * @property Carbon $event_date
 * @property string $change_type
 * @property int|null $members_added
 * @property int|null $members_removed
 * @property string|null $notes
 * @property int $recorded_by
 *
 * @see docs/DATA_DICTIONARY.md §12 (member_histories)
 * @see docs/DECISIONS.md §10 (aturan provisional)
 */
class MemberHistory extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * `cooperative_id` dan `recorded_by` TIDAK fillable: ditetapkan di server.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_date',
        'change_type',
        'members_added',
        'members_removed',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'members_added' => 'integer',
            'members_removed' => 'integer',
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
     * Opsi jenis perubahan (PROVISIONAL) dari konfigurasi.
     *
     * @return array<string, string>
     */
    public static function changeTypeOptions(): array
    {
        return config('koperasi.member_change_type_options', []);
    }

    public function changeTypeLabel(): string
    {
        return self::changeTypeOptions()[$this->change_type] ?? $this->change_type;
    }

    /**
     * Selisih bersih (bertambah - berkurang) untuk tampilan tren.
     */
    public function netChange(): int
    {
        return (int) $this->members_added - (int) $this->members_removed;
    }
}
