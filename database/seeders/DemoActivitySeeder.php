<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\LawyerAvailability;
use App\Models\LawyerProfile;
use App\Models\LegalCase;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample availability, appointments and cases connecting the demo
 * customer to the demo approved lawyer, so the future dashboards have
 * something real to render. Idempotent: guards on existing rows.
 */
class DemoActivitySeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->where('email', 'customer@legalconnect.test')->first();
        $lawyerUser = User::query()->where('email', 'lawyer.approved@legalconnect.test')->first();
        $service = Service::query()->where('slug', 'criminal-law')->first();

        if (! $customer || ! $lawyerUser || ! $service) {
            // Dependent seeders haven't run; nothing to attach demo activity to.
            return;
        }

        $lawyerProfile = LawyerProfile::query()->where('user_id', $lawyerUser->id)->first();

        if (! $lawyerProfile) {
            return;
        }

        if ($lawyerProfile->availabilities()->count() === 0) {
            foreach ([1, 2, 3, 4, 5] as $day) {
                LawyerAvailability::query()->create([
                    'lawyer_profile_id' => $lawyerProfile->id,
                    'day_of_week' => $day,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'is_recurring' => true,
                    'is_available' => true,
                ]);
            }
        }

        if (! Appointment::query()->where('reference', 'LC-2026-10245')->exists()) {
            Appointment::query()->create([
                'reference' => 'LC-2026-10245',
                'customer_id' => $customer->id,
                'lawyer_profile_id' => $lawyerProfile->id,
                'service_id' => $service->id,
                'appointment_date' => now()->addDay()->toDateString(),
                'start_time' => '14:00:00',
                'consultation_type' => 'online',
                'status' => 'pending',
                'reason' => 'Demo consultation request',
            ]);
        }

        if (! LegalCase::query()->where('case_number', 'LC-C-1024')->exists()) {
            LegalCase::query()->create([
                'case_number' => 'LC-C-1024',
                'customer_id' => $customer->id,
                'lawyer_profile_id' => $lawyerProfile->id,
                'service_id' => $service->id,
                'title' => 'Residential tenancy dispute',
                'description' => 'Demo case seeded for local development.',
                'status' => 'active',
                'opened_at' => now()->subDays(10)->toDateString(),
            ]);
        }
    }
}
