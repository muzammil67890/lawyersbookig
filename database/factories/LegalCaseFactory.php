<?php

namespace Database\Factories;

use App\Models\LawyerProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LegalCase>
 */
class LegalCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'case_number' => 'LC-C-'.fake()->unique()->numberBetween(1000, 9999),
            'customer_id' => User::factory(),
            'lawyer_profile_id' => LawyerProfile::factory(),
            'service_id' => Service::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => 'pending',
            'opened_at' => now()->toDateString(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'active']);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'closed_at' => now()->toDateString(),
        ]);
    }
}
