<?php

namespace Database\Factories;

use App\Models\LawyerProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LawyerAvailability>
 */
class LawyerAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lawyer_profile_id' => LawyerProfile::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'specific_date' => null,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_recurring' => true,
            'is_available' => true,
        ];
    }
}
