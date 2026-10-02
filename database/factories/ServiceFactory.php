<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Criminal Law', 'Family Law', 'Divorce', 'Civil Law', 'Property Law',
            'Corporate Law', 'Immigration', 'Employment Law', 'Tax Law', 'Affidavit',
        ]).' '.fake()->unique()->numberBetween(1, 100000);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => 'fa-scale-balanced',
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
