<?php

namespace Database\Factories;

use App\Models\LawyerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => User::factory(),
            'lawyer_profile_id' => LawyerProfile::factory(),
            'status' => 'active',
            'last_message_at' => now(),
        ];
    }
}
