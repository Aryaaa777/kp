<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model `officers` — susunan pengurus koperasi.
 *
 * FR-120: CRUD susunan pengurus & periode jabatan.
 * FR-121: Status aktif/nonaktif pengurus.
 *
 * Periode jabatan disimpan sebagai date range (`period_start`, `period_end`), bukan
 * `period_year` seperti modul periodik lain (kesehatan, aset, anggota, tata kelola).
 * Ini karena pengurus dapat menjabat lintas tahun dan multiple jabatan simultan.
 *
 * @property int $id
 * @property int $cooperative_id
 * @property string $name Nama pengurus
 * @property string $position Jabatan
 * @property string|null $identity_number Nomor identitas (data pribadi, sensitif)
 * @property Carbon $period_start Tanggal mulai menjabat
 * @property Carbon|null $period_end Tanggal akhir menjabat (null = masih menjabat)
 * @property bool $is_active Status aktif (FR-121)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Cooperative $cooperative
 *
 * @see docs/DATA_DICTIONARY.md §13 (officers)
 * @see docs/PRD.md FR-120, FR-121
 */
class Officer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'position',
        'identity_number',
        'period_start',
        'period_end',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Koperasi pemilik pengurus.
     *
     * @return BelongsTo<Cooperative, $this>
     */
    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class);
    }

    /**
     * Apakah pengurus masih menjabat (period_end null atau di masa depan).
     */
    public function isCurrentlyServing(): bool
    {
        return $this->period_end === null || $this->period_end->isFuture();
    }

    /**
     * Durasi masa jabatan dalam hari (null jika masih menjabat).
     */
    public function tenureDays(): ?int
    {
        if ($this->period_end === null) {
            return null;
        }

        return $this->period_start->diffInDays($this->period_end);
    }

    /**
     * Format periode jabatan untuk tampilan UI.
     *
     * Contoh: "1 Jan 2020 – sekarang" atau "1 Jan 2020 – 31 Des 2023"
     */
    public function formattedPeriod(): string
    {
        $start = $this->period_start->translatedFormat('d M Y');
        $end = $this->period_end
            ? $this->period_end->translatedFormat('d M Y')
            : 'sekarang';

        return "{$start} – {$end}";
    }

    /**
     * Mask nomor identitas untuk keamanan (NFR-SEC-015).
     *
     * Tampilkan 4 digit pertama + '****' + 4 digit terakhir.
     * Contoh: "3201***********1234"
     */
    public function maskedIdentityNumber(): ?string
    {
        if (blank($this->identity_number)) {
            return null;
        }

        $length = strlen($this->identity_number);

        if ($length <= 8) {
            // Jika terlalu pendek, mask semua kecuali 2 digit terakhir
            return str_repeat('*', $length - 2).substr($this->identity_number, -2);
        }

        $first = substr($this->identity_number, 0, 4);
        $last = substr($this->identity_number, -4);
        $middle = str_repeat('*', $length - 8);

        return $first.$middle.$last;
    }
}
