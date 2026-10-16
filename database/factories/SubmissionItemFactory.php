<?php

namespace Database\Factories;

use App\Models\Submission;
use App\Models\SubmissionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubmissionItem>
 */
class SubmissionItemFactory extends Factory
{
    /**
     * @var class-string<SubmissionItem>
     */
    protected $model = SubmissionItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'field' => 'nama',
            'old_value' => 'Nama Lama',
            'new_value' => 'Nama Baru',
        ];
    }
}
