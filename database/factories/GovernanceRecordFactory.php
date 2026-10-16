<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\GovernanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GovernanceRecord>
 */
class GovernanceRecordFactory extends Factory
{
    protected $model = GovernanceRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cooperative_id' => Cooperative::factory(),
            'period_year' => (int) date('Y') - fake()->numberBetween(0, 5),
            'period' => 'annual',
            'meeting_count' => fake()->numberBetween(1, 12),
            'governance_score' => fake()->optional(0.7)->randomFloat(2, 40, 100),
            'summary' => 'Ringkasan tata kelola contoh (data uji).',
            'status' => 'draft',
            'recorded_by' => User::factory(),
        ];
    }

    public function forPeriod(int $year, string $period = 'annual'): static
    {
        return $this->state(fn (array $attributes) => [
            'period_year' => $year,
            'period' => $period,
        ]);
    }
}
