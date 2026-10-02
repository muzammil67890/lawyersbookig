<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Specialization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A small set of example specializations nested under services, so the
 * lawyer<->specialization pivot and search filters have real data to
 * exercise. Idempotent: upserts by (service_id, slug).
 */
class SpecializationSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'Criminal Law' => ['Bail Applications', 'Trial Defence'],
            'Family Law' => ['Child Custody', 'Domestic Matters'],
            'Corporate Law' => ['Business Formation', 'Contract Drafting'],
            'Property Law' => ['Tenancy Disputes', 'Title Transfer'],
            'Tax Law' => ['Tax Disputes', 'Corporate Tax Planning'],
            'Immigration' => ['Visa Applications', 'Residency'],
        ];

        foreach ($map as $serviceName => $names) {
            $service = Service::query()->where('slug', Str::slug($serviceName))->first();

            if (! $service) {
                continue;
            }

            foreach ($names as $name) {
                Specialization::query()->updateOrCreate(
                    ['service_id' => $service->id, 'slug' => Str::slug($name)],
                    ['name' => $name]
                );
            }
        }
    }
}
