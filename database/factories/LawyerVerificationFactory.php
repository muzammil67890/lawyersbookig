<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LawyerVerification>
 */
class LawyerVerificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->lawyer(),
            'cnic_image_path' => 'verifications/cnic/'.fake()->uuid().'.jpg',
            'face_image_path' => 'verifications/face/'.fake()->uuid().'.jpg',
            'status' => 'pending',
            'submitted_at' => now(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'reviewed_by' => User::factory()->admin(),
            'reviewed_at' => now(),
            'rejection_reason' => 'Submitted CNIC image was unclear. Please resubmit.',
        ]);
    }
}
