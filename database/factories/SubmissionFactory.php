<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Submission>
 */
class SubmissionFactory extends Factory
{
    /**
     * @var class-string<Submission>
     */
    protected $model = Submission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cooperative_id' => Cooperative::factory(),
            'submitted_by' => User::factory(),
            'module' => 'koperasi_induk',
            'title' => 'Pengajuan perubahan data induk',
            'status' => 'draft',
            'reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'applied_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => ['status' => 'submitted']);
    }

    public function underReview(): static
    {
        return $this->state(fn (): array => ['status' => 'under_review']);
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'reviewed_at' => now(),
            'applied_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => 'rejected',
            'reason' => 'Tidak sesuai',
            'reviewed_at' => now(),
        ]);
    }
}
