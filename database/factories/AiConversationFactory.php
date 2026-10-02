<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiConversation>
 */
class AiConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'service_id' => Service::factory(),
            'status' => 'active',
            'location' => fake()->randomElement(['Karachi', 'Lahore', 'Islamabad']),
            'budget_min' => 2000,
            'budget_max' => 5000,
        ];
    }
}
