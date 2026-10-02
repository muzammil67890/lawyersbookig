<?php

namespace Database\Factories;

use App\Models\LawyerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'LC-'.now()->format('Y').'-'.fake()->unique()->numberBetween(10000, 99999),
            'customer_id' => User::factory(),
            'lawyer_profile_id' => LawyerProfile::factory(),
            'service_id' => Service::factory(),
            'appointment_date' => fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'start_time' => fake()->randomElement(['09:00:00', '10:30:00', '12:00:00', '14:00:00', '15:30:00']),
            'end_time' => null,
            'consultation_type' => fake()->randomElement(['online', 'office', 'phone']),
            'status' => 'pending',
            'reason' => fake()->sentence(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'confirmed']);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'completed']);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancellation_reason' => 'Customer requested cancellation.',
        ]);
    }
}
