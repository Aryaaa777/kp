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
     * Status operasional koperasi — keputusan pemilik proyek (Tahap 106).
     *
     * Berbeda dari `status_verifikasi` (posisi dalam alur verifikasi) dan `is_published`
     * (tampil/tidak di direktori). Nilai `NULL` = belum ditetapkan; jangan dianggap aktif.
     *
     * @var list<string>
     */
    public const STATUS_OPERASIONAL = [
        'aktif',
        'tidak_aktif',
        'bubar',
    ];

    /**
     * Label Indonesia status operasional (satu-satunya sumber teks tampilan).
     *
     * @var array<string, string>
     */
    public const STATUS_OPERASIONAL_LABELS = [
        'aktif' => 'Aktif',
        'tidak_aktif' => 'Tidak Aktif',
        'bubar' => 'Bubar',
    ];

    /**
     * @return array<string, string>
     */
    public static function statusOperasionalLabels(): array
    {
        return self::STATUS_OPERASIONAL_LABELS;
    }

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
     * Label status verifikasi yang **boleh tampil publik** menurut konfigurasi
     * (`config('koperasi.public.visible_statuses')`) — sumber kelayakan yang sama dengan
     * direktori publik dan `Admin\CooperativeController::publicationBlocker()`.
     *
     * Status yang tidak memiliki label resmi (`STATUS_LABELS`) **diabaikan**, sehingga UI tidak
     * pernah menampilkan kode status mentah. Daftar kosong berarti tidak ada status yang dapat
     * dipublikasikan: pemanggil wajib memakai teks aman dan **tidak** mengklaim kelayakan apa pun.
     * Urutan label mengikuti `STATUS_LABELS` (urutan alur verifikasi resmi).
     *
     * @return list<string>
     */
    public static function publicStatusLabels(): array
    {
        $statuses = array_map('strval', array_values((array) config('koperasi.public.visible_statuses', [])));

        return array_values(array_intersect_key(self::STATUS_LABELS, array_flip($statuses)));
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
        'status_operasional',
        'is_published',
        'published_at',
        'published_by',
        'unpublished_at',
        'unpublished_by',
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
            'unpublished_at' => 'datetime',
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
     * Segmen URL **internal** di bawah prefix `/koperasi` yang TIDAK boleh dipakai sebagai slug
     * publik (Tahap 88). Bila sebuah koperasi memakai salah satu segmen ini, `/koperasi/{slug}`
     * akan ditangkap route internal (`auth` + `role:pengelola_koperasi`) sehingga profil publiknya
     * tidak dapat dijangkau.
     *
     * Daftar ini adalah **satu sumber** yang dipakai oleh `generateUniqueSlug()` (mencegah slug
     * baru) **dan** `publicSlugPattern()` (constraint route publik). Isinya diturunkan dari
     * inventaris route aktual di `routes/web.php` (bukan tebakan) — lihat `docs/DECISIONS.md` §26.
     *
     * @var list<string>
     */
    public const RESERVED_SLUGS = [
        'dashboard',
        'profil',
        'izin',
        'pengurus',
        'kesehatan',
        'tata-kelola',
        'aset',
        'anggota',
        'pengajuan',
        'notifikasi',
    ];

    /**
     * Apakah sebuah slug termasuk segmen tercadang. Pencocokan memakai normalisasi yang sama
     * dengan pembentukan slug (`Str::slug()`), sehingga variasi kapitalisasi/tanda hubung
     * (`Profil`, `TATA KELOLA`, `tata_kelola`) tetap terdeteksi.
     */
    public static function isReservedSlug(string $slug): bool
    {
        return in_array(Str::slug($slug), self::RESERVED_SLUGS, true);
    }

    /**
     * Pola constraint untuk route profil publik `/koperasi/{slug}`: **menolak** segmen tercadang.
     *
     * Hanya segmen yang **sama persis** yang ditolak; slug seperti `anggota-sejahtera` atau
     * `profil-koperasi` tetap diterima.
     */
    public static function publicSlugPattern(): string
    {
        $reserved = array_map(fn (string $segment): string => preg_quote($segment, '#'), self::RESERVED_SLUGS);

        // Grup non-capturing wajib: tanpa `(?:…)`, tanda `$` hanya menempel pada alternatif
        // terakhir sehingga `anggota-sejahtera`/`profil-2` ikut tertolak.
        return '(?!(?:'.implode('|', $reserved).')$)[^/]+';
    }

    /**
     * Slug unik yang stabil. Dibuat sekali saat pembuatan data; tidak diubah saat nama diperbarui
     * agar URL (direktori publik, F7) tidak berubah.
     *
     * Nama yang menghasilkan segmen tercadang (`Profil`, `Anggota`, …) diperlakukan seperti slug
     * yang sudah terpakai sehingga kandidat berikutnya memakai suffix numerik yang sama dengan
     * konvensi aplikasi (`profil-2`, `profil-3`, …) — Tahap 88.
     */
    public static function generateUniqueSlug(string $nama, ?int $ignoreId = null): string
    {
        $base = Str::slug($nama) ?: 'koperasi';

        if (static::isReservedSlug($base)) {
            // Segmen internal diperlakukan seperti slug yang sudah terpakai → mulai dari `-2`.
            $slug = $base.'-2';
            $suffix = 3;
        } else {
            $slug = $base;
            $suffix = 2;
        }

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
     * Susunan pengurus koperasi (FR-120/121).
     *
     * @return HasMany<Officer, $this>
     */
    public function officers(): HasMany
    {
        return $this->hasMany(Officer::class);
    }

    /**
     * Penilaian kesehatan koperasi (FR-080/081).
     *
     * @return HasMany<HealthAssessment, $this>
     */
    public function healthAssessments(): HasMany
    {
        return $this->hasMany(HealthAssessment::class);
    }

    /**
     * Data tata kelola koperasi (FR-090).
     *
     * @return HasMany<GovernanceRecord, $this>
     */
    public function governanceRecords(): HasMany
    {
        return $this->hasMany(GovernanceRecord::class);
    }

    /**
     * Aset koperasi per periode (FR-100/101).
     *
     * @return HasMany<CooperativeAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(CooperativeAsset::class);
    }

    /**
     * Riwayat keanggotaan koperasi (FR-110/111).
     *
     * @return HasMany<MemberHistory, $this>
     */
    public function memberHistories(): HasMany
    {
        return $this->hasMany(MemberHistory::class);
    }

    /**
     * Pengajuan perubahan data resmi (FR-140…143).
     *
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * Label status dalam bahasa Indonesia.
     */
    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status_verifikasi] ?? $this->status_verifikasi;
    }

    /**
     * Label status operasional; `null` bila belum ditetapkan **atau** nilainya tidak dikenal.
     *
     * `null` sengaja dikembalikan untuk nilai tak dikenal agar tampilan publik tidak pernah
     * mencetak kode mentah (mis. data lama/rusak).
     */
    public function statusOperasionalLabel(): ?string
    {
        $status = $this->status_operasional;

        return is_string($status)
            ? (self::STATUS_OPERASIONAL_LABELS[$status] ?? null)
            : null;
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

    /**
     * Aktor yang menarik publikasi terakhir (D-6).
     *
     * @return BelongsTo<User, $this>
     */
    public function unpublisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unpublished_by');
    }
}
