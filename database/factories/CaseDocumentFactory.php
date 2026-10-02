<?php

namespace Database\Factories;

use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CaseDocument>
 */
class CaseDocumentFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->word().'.pdf';

        return [
            'case_id' => LegalCase::factory(),
            'uploaded_by' => User::factory(),
            'original_filename' => $name,
            'stored_path' => 'case-documents/'.fake()->uuid().'/'.$name,
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(10_000, 5_000_000),
            'category' => fake()->randomElement(['evidence', 'contract', 'identification', 'correspondence']),
        ];
    }
}
