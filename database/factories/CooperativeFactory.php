<?php

namespace Database\Factories;

use App\Models\Cooperative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cooperative>
 */
class CooperativeFactory extends Factory
{
    /**
     * @var class-string<Cooperative>
     */
    protected $model = Cooperative::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => 'Koperasi '.fake()->unique()->company(),
            // Slug sengaja null: dibuat otomatis oleh model (unik & stabil).
            'slug' => null,
            'nomor_identitas' => fake()->unique()->numerify('KOP-########'),
            'jenis_koperasi' => 'Koperasi Uji',
            'bentuk_koperasi' => null,
            'tanggal_berdiri' => fake()->dateTimeBetween('-30 years', '-1 year'),
            'region_id' => null,
            'alamat' => fake()->address(),
            'telephone' => fake()->numerify('08##########'),
            'email' => fake()->unique()->safeEmail(),
            'website' => null,
            'status_verifikasi' => 'draft',
            'is_published' => false,
        ];
    }
}
