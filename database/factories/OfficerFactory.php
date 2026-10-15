<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\Officer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory untuk model Officer (pengurus koperasi).
 *
 * @extends Factory<Officer>
 */
class OfficerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodStart = fake()->dateTimeBetween('-5 years', '-1 year');
        $periodEnd = fake()->boolean(70) ? null : fake()->dateTimeBetween($periodStart, 'now');

        return [
            'cooperative_id' => Cooperative::factory(),
            'name' => fake()->name(),
            'position' => fake()->randomElement([
                'Ketua',
                'Wakil Ketua',
                'Sekretaris',
                'Bendahara',
                'Pengawas',
                'Anggota',
            ]),
            'identity_number' => fake()->optional(0.7)->numerify('32########'),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'is_active' => $periodEnd === null ? true : fake()->boolean(80),
        ];
    }

    /**
     * Pengurus yang masih menjabat (period_end null).
     */
    public function currentlyServing(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_end' => null,
            'is_active' => true,
        ]);
    }

    /**
     * Pengurus yang sudah selesai masa jabatan.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $periodStart = fake()->dateTimeBetween('-5 years', '-1 year');

            return [
                'period_start' => $periodStart,
                'period_end' => fake()->dateTimeBetween($periodStart, 'now'),
                'is_active' => fake()->boolean(30),
            ];
        });
    }

    /**
     * Pengurus dengan jabatan tertentu.
     */
    public function position(string $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }

    /**
     * Pengurus aktif.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Pengurus nonaktif.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
