<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dokumen pendukung yang tersimpan di storage privat (FR-070/071).
 *
 * `file_path` menunjuk berkas di disk privat dan TIDAK boleh diakses lewat URL publik
 * tanpa otorisasi (NFR-SEC-009/010).
 */
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Contoh kategori (diambil dari `docs/DATA_DICTIONARY.md` §8) untuk saran pada form.
     *
     * Ini **bukan** daftar resmi — kategori disimpan sebagai teks bebas dan tidak dibatasi
     * daftar sampai dinas menetapkan taksonomi resmi (lihat `docs/DECISIONS.md` §5).
     *
     * @var list<string>
     */
    public const CATEGORY_SUGGESTIONS = [
        'akta_pendirian',
        'izin_usaha',
        'laporan_aset',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cooperative_id',
        'documentable_type',
        'documentable_id',
        'category',
        'original_name',
        'file_path',
        'mime_type',
        'size_bytes',
        'checksum',
        'version',
        'previous_version_id',
        'superseded_at',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'version' => 'integer',
            'superseded_at' => 'datetime',
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
     * Entitas pemilik dokumen (F3: izin).
     *
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Versi sebelumnya dalam rantai versi (FR-072).
     *
     * @return BelongsTo<Document, $this>
     */
    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_version_id');
    }

    /**
     * Versi berikutnya yang menggantikan dokumen ini.
     *
     * @return HasMany<Document, $this>
     */
    public function nextVersions(): HasMany
    {
        return $this->hasMany(self::class, 'previous_version_id');
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null;
    }

    /**
     * Ukuran berkas dalam satuan yang mudah dibaca.
     */
    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / (1024 * 1024), 2).' MB';
    }
}
