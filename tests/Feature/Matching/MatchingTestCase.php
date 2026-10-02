<?php

namespace Tests\Feature\Matching;

use App\Models\LawyerProfile;
use App\Models\Service;
use App\Models\Specialization;
use App\Services\LawyerMatchingService;
use App\Services\Matching\MatchCriteria;
use App\Services\Matching\MatchResultSet;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\SpecializationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared fixtures for the Phase 5 Half 2 matching tests. Uses the REAL
 * seeded service/specialization catalogue (Criminal Law, Family Law,
 * Divorce, ... and their specializations) so keyword tests run against
 * the same data production uses.
 */
abstract class MatchingTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ServiceSeeder::class);
        $this->seed(SpecializationSeeder::class);
    }

    protected function svc(string $slug): Service
    {
        return Service::query()->where('slug', $slug)->firstOrFail();
    }

    protected function spec(string $slug): Specialization
    {
        return Specialization::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * An APPROVED, active, public lawyer with neutral defaults (so
     * random factory text can never cause an accidental keyword hit).
     * Override any attribute, including eligibility flags, via $attrs.
     *
     * @param  list<string>  $services  service slugs
     * @param  list<string>  $specializations  specialization slugs
     */
    protected function lawyer(array $attrs = [], array $services = [], array $specializations = []): LawyerProfile
    {
        $lawyer = LawyerProfile::factory()->approved()->create(array_merge([
            'display_name' => 'Adv. '.Str::random(10),
            'title' => 'Advocate',
            'bio' => 'Experienced advocate serving clients.',
            'experience_summary' => null,
            'city' => 'Karachi',
            'years_of_experience' => 5,
            'consultation_fee' => 3000,
        ], $attrs));

        $lawyer->services()->sync(array_map(fn (string $slug) => $this->svc($slug)->id, $services));
        $lawyer->specializations()->sync(array_map(fn (string $slug) => $this->spec($slug)->id, $specializations));

        return $lawyer;
    }

    protected function matcher(?array $config = null): LawyerMatchingService
    {
        return $config === null
            ? app(LawyerMatchingService::class)
            : new LawyerMatchingService(app(\App\Services\Matching\ProblemKeywordResolver::class), $config);
    }

    /** @param  array<string, mixed>  $input  same keys as the HTTP query string */
    protected function match(array $input, ?array $config = null): MatchResultSet
    {
        return $this->matcher($config)->match(MatchCriteria::fromArray($input));
    }

    /** @return list<int> lawyer profile IDs, best first */
    protected function ids(MatchResultSet $result): array
    {
        return $result->matches()->map(fn ($m) => $m->lawyer->id)->all();
    }

    protected function first(MatchResultSet $result)
    {
        return $result->matches()->first();
    }
}
