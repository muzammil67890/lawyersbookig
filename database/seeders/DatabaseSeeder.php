<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for local development.
     *
     * Phase 1 shipped without demo data; Phase 2 adds real seeded
     * accounts and reference data now that the domain schema exists.
     * All credentials here are throwaway development-only values --
     * see README.md's "Development credentials" section -- and none
     * of the seeded people, cases or documents represent real users.
     */
    public function run(): void
    {
        $this->call([
            ServiceSeeder::class,
            SpecializationSeeder::class,
        ]);

        // Phase 11: the demo seeders create accounts (including an admin)
        // with the well-known password "password". They must never run
        // against a production database, so `db:seed` only loads the
        // reference data there. Run them explicitly with
        // `--class=DemoAccountSeeder` if that is genuinely intended.
        if (app()->isProduction()) {
            $this->command?->warn('Production environment: demo accounts and demo activity were NOT seeded.');

            return;
        }

        $this->call([
            DemoAccountSeeder::class,
            DemoActivitySeeder::class,
        ]);
    }
}
