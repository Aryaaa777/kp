<?php

namespace Database\Factories;

use App\Models\Submission;
use App\Models\SubmissionReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubmissionReview>
 */
class SubmissionReviewFactory extends Factory
{
    /**
     * @var class-string<SubmissionReview>
     */
    protected $model = SubmissionReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            'user_id' => User::factory(),
            'from_status' => 'draft',
            'to_status' => 'submitted',
            'note' => null,
        ];
    }
}
