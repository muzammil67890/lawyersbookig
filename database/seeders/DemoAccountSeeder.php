<?php

namespace Database\Seeders;

use App\Models\LawyerProfile;
use App\Models\LawyerVerification;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds a small set of clearly-fake development accounts: one customer,
 * one admin, and three lawyers covering each verification state
 * (approved, pending, rejected) so every admin-review path in Phase 3
 * has real data to work against.
 *
 * These are throwaway local/demo credentials only -- see README.md for
 * the full list -- and are never real personal information.
 * Idempotent: upserts by email.
 */
class DemoAccountSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->updateOrCreate(
            ['email' => 'customer@legalconnect.test'],
            [
                'name' => 'Samira Ahmed',
                'phone' => '+92 300 5550123',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@legalconnect.test'],
            [
                'name' => 'Platform Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // Phase 3: a deliberately suspended account, so the account-status
        // enforcement (login-time and mid-session) has something real to
        // demonstrate against without needing to suspend a working account
        // by hand.
        User::query()->updateOrCreate(
            ['email' => 'suspended.customer@legalconnect.test'],
            [
                'name' => 'Suspended Demo Account',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'status' => 'suspended',
                'email_verified_at' => now(),
            ]
        );

        $criminalLaw = Service::query()->where('slug', 'criminal-law')->first();
        $familyLaw = Service::query()->where('slug', 'family-law')->first();
        $propertyLaw = Service::query()->where('slug', 'property-law')->first();

        $lawyers = [
            [
                'email' => 'lawyer.approved@legalconnect.test',
                'name' => 'Adv. Alex Morgan',
                'profile' => [
                    'display_name' => 'Adv. Alex Morgan',
                    'title' => 'Criminal & Civil Advocate',
                    'bio' => 'Trial advocate with over a decade of experience in criminal defence and complex civil litigation.',
                    'city' => 'Karachi',
                    'years_of_experience' => 12,
                    'consultation_fee' => 3000,
                    'verification_status' => 'approved',
                    'is_active' => true,
                ],
                'verification' => 'approved',
                'service' => $criminalLaw,
            ],
            [
                'email' => 'lawyer.pending@legalconnect.test',
                'name' => 'Adv. Bilal Siddiqui',
                'profile' => [
                    'display_name' => 'Adv. Bilal Siddiqui',
                    'title' => 'Criminal Defence Advocate',
                    'bio' => 'Represents individuals in criminal proceedings and advises on procedural rights.',
                    'city' => 'Peshawar',
                    'years_of_experience' => 7,
                    'consultation_fee' => 2500,
                    'verification_status' => 'pending',
                    'is_active' => true,
                ],
                'verification' => 'pending',
                'service' => $criminalLaw,
            ],
            [
                'email' => 'lawyer.rejected@legalconnect.test',
                'name' => 'Adv. Kamran Shah',
                'profile' => [
                    'display_name' => 'Adv. Kamran Shah',
                    'title' => 'Tax Dispute Counsel',
                    'bio' => 'Represents individuals and businesses in tax and civil disputes.',
                    'city' => 'Peshawar',
                    'years_of_experience' => 12,
                    'consultation_fee' => 4500,
                    'verification_status' => 'rejected',
                    'is_active' => true,
                ],
                'verification' => 'rejected',
                'service' => $familyLaw,
            ],
        ];

        foreach ($lawyers as $entry) {
            $user = User::query()->updateOrCreate(
                ['email' => $entry['email']],
                [
                    'name' => $entry['name'],
                    'password' => Hash::make('password'),
                    'role' => 'lawyer',
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            $profile = LawyerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                $entry['profile']
            );

            if ($entry['service']) {
                $profile->services()->syncWithoutDetaching([$entry['service']->id]);
            }

            $verificationAttributes = [
                'cnic_image_path' => 'verifications/cnic/'.$user->id.'.jpg',
                'face_image_path' => 'verifications/face/'.$user->id.'.jpg',
                'status' => $entry['verification'],
                'submitted_at' => now()->subDays(3),
            ];

            if ($entry['verification'] !== 'pending') {
                $verificationAttributes['reviewed_by'] = $admin->id;
                $verificationAttributes['reviewed_at'] = now();
            }

            if ($entry['verification'] === 'rejected') {
                $verificationAttributes['rejection_reason'] = 'Submitted CNIC image was unclear. Please resubmit.';
            }

            LawyerVerification::query()->updateOrCreate(
                ['user_id' => $user->id],
                $verificationAttributes
            );
        }

        $this->command?->info('Demo accounts ready (password for all: "password"): '.
            'customer@legalconnect.test, admin@legalconnect.test, '.
            'lawyer.approved@legalconnect.test, lawyer.pending@legalconnect.test, lawyer.rejected@legalconnect.test, '.
            'suspended.customer@legalconnect.test (cannot log in -- demonstrates account-status blocking)');
    }
}
