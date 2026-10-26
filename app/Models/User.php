<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'koperasi_id', 'region_id', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Peran yang dimiliki pengguna (many-to-many melalui tabel `role_user`).
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Koperasi yang tertaut pada akun pengelola (BR-017: satu akun = satu koperasi untuk MVP).
     *
     * @return BelongsTo<Cooperative, $this>
     */
    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Cooperative::class, 'koperasi_id');
    }

    /**
     * Wilayah kewenangan admin wilayah (FR-012, PROVISIONAL).
     *
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Apakah akun sudah tertaut ke koperasi.
     */
    public function hasKoperasi(): bool
    {
        return $this->koperasi_id !== null;
    }

    /**
     * Pengajuan yang dibuat pengguna (FR-140, sisi pengelola).
     *
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'submitted_by');
    }

    /**
     * Log audit aktivitas pengguna (FR-151).
     *
     * @return HasMany<ActivityLog, $this>
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Apakah pengguna memiliki peran tertentu.
     */
    public function hasRole(string $name): bool
    {
        return $this->roles->contains('name', $name);
    }

    /**
     * Apakah pengguna memiliki salah satu dari peran yang diberikan.
     *
     * @param  array<int, string>  $names
     */
    public function hasAnyRole(array $names): bool
    {
        return $this->roles->whereIn('name', $names)->isNotEmpty();
    }

    /**
     * Apakah akun aktif (dapat login).
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
