<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LawyerProfile>
 */
class LawyerProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->lawyer(),
            'display_name' => 'Adv. '.fake()->name(),
            'bio' => fake()->paragraph(),
            'phone' => fake()->numerify('+92 3## #######'),
            'city' => fake()->randomElement(['Karachi', 'Lahore', 'Islamabad', 'Rawalpindi', 'Peshawar']),
            'location' => fake()->streetName(),
            'address' => fake()->address(),
            'years_of_experience' => fake()->numberBetween(1, 25),
            'consultation_fee' => fake()->randomElement([2500, 3000, 3500, 4000, 5000, 6000]),
            'starting_fee' => fake()->randomElement([2000, 2500, 3000]),
            'experience_summary' => fake()->paragraph(),
            'education' => 'LL.B., '.fake()->company().' Law School',
            'bar_council' => fake()->randomElement(['Sindh Bar Council', 'Punjab Bar Council', 'Islamabad Bar Council']),
            'license_number' => strtoupper(fake()->bothify('BC-####-???')),
            'is_public' => true,
            'is_active' => true,
            'verification_status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['verification_status' => 'approved']);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => ['verification_status' => 'pending']);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => ['verification_status' => 'rejected']);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['verification_status' => 'suspended', 'is_active' => false]);
    }
}
