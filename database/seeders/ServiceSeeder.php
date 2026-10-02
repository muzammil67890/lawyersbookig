<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the legal service/category directory shown in the Phase 1
 * prototype (services grid, filter dropdowns, admin "Manage services").
 * Idempotent: upserts by slug so re-running the seeder is safe.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Criminal Law', 'icon' => 'fa-gavel', 'description' => 'Defence, bail, hearings and criminal proceedings.'],
            ['name' => 'Family Law', 'icon' => 'fa-people-roof', 'description' => 'Family rights, custody and related legal matters.'],
            ['name' => 'Divorce', 'icon' => 'fa-heart-crack', 'description' => 'Guidance for separation, divorce and settlements.'],
            ['name' => 'Civil Law', 'icon' => 'fa-scale-balanced', 'description' => 'Representation for civil claims and disputes.'],
            ['name' => 'Property Law', 'icon' => 'fa-house', 'description' => 'Property ownership, tenancy and real estate matters.'],
            ['name' => 'Corporate Law', 'icon' => 'fa-building', 'description' => 'Business formation, contracts and compliance.'],
            ['name' => 'Immigration', 'icon' => 'fa-passport', 'description' => 'Visa, residency and immigration applications.'],
            ['name' => 'Employment Law', 'icon' => 'fa-briefcase', 'description' => 'Workplace rights, policies and disputes.'],
            ['name' => 'Tax Law', 'icon' => 'fa-file-invoice-dollar', 'description' => 'Tax planning, notices and compliance matters.'],
            ['name' => 'Affidavit', 'icon' => 'fa-file-signature', 'description' => 'Affidavits, attestations and legal documentation.'],
        ];

        foreach ($services as $service) {
            Service::query()->updateOrCreate(
                ['slug' => Str::slug($service['name'])],
                [
                    'name' => $service['name'],
                    'icon' => $service['icon'],
                    'description' => $service['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
