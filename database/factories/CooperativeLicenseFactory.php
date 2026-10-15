<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\CooperativeLicense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CooperativeLicense>
 */
class CooperativeLicenseFactory extends Factory
{
    /**
     * @var class-string<CooperativeLicense>
     */
    protected $model = CooperativeLicense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cooperative_id' => Cooperative::factory(),
            'license_type' => CooperativeLicense::TYPE_BUSINESS,
            'nomor' => 'IZIN-'.fake()->unique()->numerify('######'),
            'tanggal_terbit' => now()->subYear()->toDateString(),
            'tanggal_berlaku_sampai' => now()->addYear()->toDateString(),
            'penerbit' => null,
            // Status awal: belum diverifikasi.
            'status' => 'pending',
            'verified_by' => null,
            'verified_at' => null,
            'catatan' => null,
        ];
    }

    /**
     * Izin usaha simpan pinjam.
     */
    public function savingsLoans(): static
    {
        return $this->state(['license_type' => CooperativeLicense::TYPE_SAVINGS_LOANS]);
    }

    /**
     * Izin yang sudah diverifikasi admin (status `active`).
     */
    public function verified(?User $admin = null): static
    {
        return $this->state([
            'status' => 'active',
            'verified_at' => now(),
            'verified_by' => $admin?->getKey() ?? User::factory(),
        ]);
    }

    /**
     * Izin yang secara tanggal sudah lewat masa berlaku.
     */
    public function past(): static
    {
        return $this->state([
            'tanggal_terbit' => now()->subYears(2)->toDateString(),
            'tanggal_berlaku_sampai' => now()->subYear()->toDateString(),
        ]);
    }
}
