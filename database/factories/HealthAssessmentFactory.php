<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\HealthAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthAssessment>
 */
class HealthAssessmentFactory extends Factory
{
    protected $model = HealthAssessment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) date('Y') - fake()->numberBetween(0, 5);

        return [
            'cooperative_id' => Cooperative::factory(),
            'period_year' => $year,
            'period' => 'annual',
            'assessment_date' => sprintf('%d-%02d-%02d', $year, fake()->numberBetween(1, 12), fake()->numberBetween(1, 28)),
            'score' => fake()->optional(0.8)->randomFloat(2, 40, 100),
            'predicate' => fake()->optional(0.6)->randomElement(['Sehat', 'Cukup Sehat', 'Kurang Sehat']),
            'assessor_id' => null,
            'notes' => 'Catatan contoh (data uji).',
            'status' => 'draft',
        ];
    }

    public function final(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'final']);
    }

    public function forPeriod(int $year, string $period = 'annual'): static
    {
        return $this->state(fn (array $attributes) => [
            'period_year' => $year,
            'period' => $period,
        ]);
    }
}
