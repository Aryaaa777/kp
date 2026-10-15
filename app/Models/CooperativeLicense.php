<?php

namespace App\Models;

use Database\Factories\CooperativeLicenseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Izin usaha & izin usaha simpan pinjam (FR-050/051/060/061).
 *
 * Satu tabel untuk dua jenis izin yang dibedakan kolom `license_type` (DATA_DICTIONARY §7).
 * Status verifikasi (`status`) hanya berubah melalui aksi verifikasi admin, bukan lewat form biasa.
 */
class CooperativeLicense extends Model
{
    /** @use HasFactory<CooperativeLicenseFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_BUSINESS = 'business';

    public const TYPE_SAVINGS_LOANS = 'savings_loans';

    /**
     * Jenis izin (discriminator).
     *
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_BUSINESS,
        self::TYPE_SAVINGS_LOANS,
    ];

    /**
     * @var array<string, string>
     */
    public const TYPE_LABELS = [
        self::TYPE_BUSINESS => 'Izin Usaha',
        self::TYPE_SAVINGS_LOANS => 'Izin Usaha Simpan Pinjam',
    ];

    /**
     * Status izin (PRD FR-051/061; data dictionary §7). Nilai valid **[NEEDS CONFIRMATION]** (Q-004).
     *
     * @var list<string>
     */
    public const STATUSES = [
        'active',
        'expired',
        'revoked',
        'pending',
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        'active' => 'Aktif',
        'expired' => 'Kadaluarsa',
        'revoked' => 'Dicabut',
        'pending' => 'Menunggu Verifikasi',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cooperative_id',
        'license_type',
        'nomor',
        'tanggal_terbit',
        'tanggal_berlaku_sampai',
        'penerbit',
        'status',
        'verified_by',
        'verified_at',
        'catatan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_terbit' => 'date',
            'tanggal_berlaku_sampai' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Status yang boleh ditetapkan lewat aksi verifikasi admin.
     *
     * `pending` dikecualikan karena merupakan status awal (belum diverifikasi); verifikasi hanya
     * memindahkan izin ke salah satu status hasil. Matriks transisi resmi menunggu konfirmasi (Q-004).
     *
     * @return list<string>
     */
    public static function verifiableStatuses(): array
    {
        return ['active', 'expired', 'revoked'];
    }

    /**
     * @return array<string, string>
     */
    public static function verifiableStatusLabels(): array
    {
        return array_intersect_key(self::STATUS_LABELS, array_flip(self::verifiableStatuses()));
    }

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return self::TYPE_LABELS;
    }

    /**
     * @return BelongsTo<Cooperative, $this>
     */
    public function cooperative(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class);
    }

    /**
     * Admin yang memverifikasi izin ini.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Apakah izin tidak lagi berlaku berdasarkan tanggal (FR-050: kadaluarsa teridentifikasi
     * dari tanggal). Tidak mengubah `status` secara otomatis — prosedur resmi belum dikonfirmasi (Q-004).
     */
    public function isExpired(): bool
    {
        return $this->tanggal_berlaku_sampai !== null
            && $this->tanggal_berlaku_sampai->isBefore(today());
    }

    /**
     * Apakah izin sudah diverifikasi admin (punya status hasil, bukan `pending`).
     */
    public function isVerified(): bool
    {
        return $this->status !== 'pending';
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->license_type] ?? $this->license_type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * @param  Builder<CooperativeLicense>  $query
     */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('license_type', $type);
    }
}
