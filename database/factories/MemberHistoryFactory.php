<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\MemberHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberHistory>
 */
class MemberHistoryFactory extends Factory
{
    protected $model = MemberHistory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['penambahan', 'pengurangan', 'lainnya']);

        return [
            'cooperative_id' => Cooperative::factory(),
            'event_date' => fake()->dateTimeBetween('-3 years', 'now'),
            'change_type' => $type,
            'members_added' => $type === 'pengurangan' ? null : fake()->numberBetween(1, 25),
            'members_removed' => $type === 'penambahan' ? null : fake()->numberBetween(1, 10),
            'notes' => 'Catatan perubahan keanggotaan contoh (data uji).',
            'recorded_by' => User::factory(),
        ];
    }

    public function penambahan(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => 'penambahan',
            'members_added' => fake()->numberBetween(1, 25),
            'members_removed' => null,
        ]);
    }

    public function pengurangan(): static
    {
        return $this->state(fn (array $attributes) => [
            'change_type' => 'pengurangan',
            'members_added' => null,
            'members_removed' => fake()->numberBetween(1, 10),
        ]);
    }
}
