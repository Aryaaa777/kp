<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => 'active',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Akun nonaktif (tidak dapat login).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Tautkan peran tertentu ke akun yang dibuat.
     */
    public function withRole(string $roleName): static
    {
        return $this->afterCreating(function (User $user) use ($roleName) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['display_name' => str($roleName)->replace('_', ' ')->title()->value()],
            );

            $user->roles()->syncWithoutDetaching([$role->id]);
        });
    }

    /**
     * Akun admin dinas.
     */
    public function admin(): static
    {
        return $this->state(['koperasi_id' => null])->withRole('admin_dinas');
    }

    /**
     * Akun pengelola koperasi, opsional tertaut ke ID koperasi.
     */
    public function pengelola(?int $koperasiId = null): static
    {
        return $this->state(['koperasi_id' => $koperasiId])->withRole('pengelola_koperasi');
    }
}
