<?php

namespace Database\Factories;

use App\Models\LawyerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'lawyer_profile_id' => LawyerProfile::factory(),
            'appointment_id' => null,
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->paragraph(1),
            'status' => 'published',
        ];
    }
}
