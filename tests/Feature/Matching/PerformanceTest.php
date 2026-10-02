<?php

namespace Tests\Feature\Matching;

use App\Models\LawyerAvailability;
use Illuminate\Support\Facades\DB;

/**
 * No N+1, and the candidate set is narrowed by the database rather
 * than by loading every lawyer and filtering in PHP.
 */
class PerformanceTest extends MatchingTestCase
{
    private function countQueries(array $input): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->match($input);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function seedLawyers(int $count): void
    {
        foreach (range(1, $count) as $i) {
            $lawyer = $this->lawyer([
                'city' => $i % 2 ? 'Karachi' : 'Lahore',
                'consultation_fee' => 1000 + $i * 100,
                'years_of_experience' => $i,
            ], ['family-law', 'divorce'], ['child-custody']);
            LawyerAvailability::factory()->create(['lawyer_profile_id' => $lawyer->id, 'day_of_week' => 1]);
        }
    }

    public function test_the_number_of_queries_does_not_grow_with_the_number_of_lawyers(): void
    {
        $input = [
            'problem' => 'divorce and custody',
            'service' => $this->svc('family-law')->id,
            'specialization' => $this->spec('child-custody')->id,
            'city' => 'Karachi',
            'max_budget' => 3000,
            'min_experience' => 3,
            'available_day' => 1,
        ];

        $this->seedLawyers(3);
        $small = $this->countQueries($input);

        $this->seedLawyers(40);
        $large = $this->countQueries($input);

        $this->assertSame($small, $large, 'Query count must be constant (no N+1)');
        $this->assertLessThanOrEqual(6, $large);
    }

    public function test_services_and_specializations_are_eager_loaded(): void
    {
        $this->seedLawyers(3);

        foreach ($this->match(['service' => $this->svc('family-law')->id])->matches() as $match) {
            $this->assertTrue($match->lawyer->relationLoaded('services'));
            $this->assertTrue($match->lawyer->relationLoaded('specializations'));
        }
    }

    public function test_the_database_narrows_candidates_so_unrelated_lawyers_are_never_loaded(): void
    {
        foreach (range(1, 20) as $i) {
            $this->lawyer([], ['criminal-law']);
        }
        foreach (range(1, 4) as $i) {
            $this->lawyer([], ['family-law']);
        }

        $meta = $this->match(['service' => $this->svc('family-law')->id])->meta();

        $this->assertSame(4, $meta['candidates_considered']);
    }

    public function test_the_candidate_cap_is_a_hard_safety_net_and_is_reported(): void
    {
        foreach (range(1, 8) as $i) {
            $this->lawyer([], ['family-law']);
        }
        $config = config('lawyer_matching');
        $config['limits']['candidate_cap'] = 5;

        $meta = $this->match(['service' => $this->svc('family-law')->id], $config)->meta();

        $this->assertSame(5, $meta['candidates_considered']);
        $this->assertTrue($meta['candidate_cap_reached']);
    }
}
