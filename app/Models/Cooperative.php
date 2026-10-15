<?php

namespace App\Models;

use Database\Factories\CooperativeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Cooperative extends Model
{
    /** @use HasFactory<CooperativeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Status verifikasi resmi (PRD §12.1).
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
     * Status yang boleh ditetapkan langsung admin melalui form pada F2.
     *
     * `archived` DIKECUALIKAN: status itu hanya dicapai lewat aksi arsip (FR-043) yang sekaligus
     * melakukan soft delete. Ini mencegah data berstatus `archived` tetap aktif/terdaftar.
     * Penjaga matriks transisi resmi (PRD §12.2) menyusul pada F5.
     *
     * @return list<string>
     */
    public static function assignableStatuses(): array
    {
        return array_values(array_diff(self::STATUSES, ['archived']));
    }

    /**
     * @return array<string, string>
     */
    public static function assignableStatusLabels(): array
    {
        return array_intersect_key(self::STATUS_LABELS, array_flip(self::assignableStatuses()));
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'slug',
        'nomor_identitas',
        'jenis_koperasi',
        'bentuk_koperasi',
        'tanggal_berdiri',
        'region_id',
        'alamat',
        'telephone',
        'email',
        'website',
        'status_verifikasi',
        'is_published',
        'published_at',
        'published_by',
        'created_by',
        'updated_by',
        'archived_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_berdiri' => 'date',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Cooperative $cooperative): void {
            if (blank($cooperative->slug)) {
                $cooperative->slug = static::generateUniqueSlug($cooperative->nama);
            }
        });
    }

    /**
     * Slug unik yang stabil. Dibuat sekali saat pembuatan data; tidak diubah saat nama diperbarui
     * agar URL (direktori publik, F7) tidak berubah.
     */
    public static function generateUniqueSlug(string $nama, ?int $ignoreId = null): string
    {
        $base = Str::slug($nama) ?: 'koperasi';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Wilayah koperasi. **Opsional** pada F2 (`region_id` nullable) sampai referensi wilayah
     * resmi tersedia (Q-012) — lihat `docs/DECISIONS.md` F2-1.
     *
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Izin usaha & izin usaha simpan pinjam koperasi (FR-050/060).
     *
     * @return HasMany<CooperativeLicense, $this>
     */
    public function licenses(): HasMany
    {
        return $this->hasMany(CooperativeLicense::class);
    }

    /**
     * Dokumen pendukung koperasi (FR-070).
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Label status dalam bahasa Indonesia.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status_verifikasi] ?? $this->status_verifikasi;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
