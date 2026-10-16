<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\CooperativeAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CooperativeAsset>
 */
class CooperativeAssetFactory extends Factory
{
    protected $model = CooperativeAsset::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cooperative_id' => Cooperative::factory(),
            'period_year' => (int) date('Y') - fake()->numberBetween(0, 5),
            'asset_type' => fake()->randomElement(['Tanah', 'Bangunan', 'Kendaraan', 'Peralatan', 'Kas']),
            'asset_name' => fake()->optional(0.7)->words(3, true),
            'quantity' => fake()->numberBetween(1, 50),
            'value' => fake()->randomFloat(2, 100000, 5000000000),
            'acquisition_date' => fake()->optional(0.6)->dateTimeBetween('-15 years', 'now'),
            'condition' => fake()->optional(0.7)->randomElement(['baik', 'rusak_ringan', 'rusak_berat']),
            'description' => 'Keterangan aset contoh (data uji).',
            'recorded_by' => User::factory(),
        ];
    }

    public function forPeriod(int $year): static
    {
        return $this->state(fn (array $attributes) => ['period_year' => $year]);
    }
}
