<?php

namespace Tests\Feature\Matching;

use App\Models\LawyerAvailability;

/**
 * Scoring maths, one criterion at a time. Default weights (config):
 * service 25, specialization 20, problem 20, location 15, budget 10,
 * experience 7, availability 3; score = earned / requested-weight * 100.
 */
class ScoringTest extends MatchingTestCase
{
    // ---------------------------------------------------------------- service

    public function test_exact_service_match_scores_100_when_only_service_is_requested(): void
    {
        $this->lawyer([], ['family-law']);

        $match = $this->first($this->match(['service' => $this->svc('family-law')->id]));

        $this->assertSame(100, $match->score);
        $this->assertTrue($match->matches['service']);
        $this->assertContains('Offers the requested service: Family Law', $match->reasons);
    }

    public function test_a_lawyer_who_does_not_offer_the_service_is_not_returned_or_scored_as_a_match(): void
    {
        $this->lawyer(['city' => 'Karachi'], ['criminal-law']);

        $result = $this->match(['service' => $this->svc('family-law')->id, 'city' => 'Karachi']);

        // Right city but wrong area of law must never surface.
        $this->assertSame([], $this->ids($result));
    }

    public function test_service_is_reported_false_not_true_for_a_lawyer_returned_for_other_reasons(): void
    {
        // Matches the requested specialization but not the requested service id.
        $lawyer = $this->lawyer([], ['criminal-law'], ['child-custody']);

        $match = $this->first($this->match([
            'service' => $this->svc('criminal-law')->id,
            'specialization' => $this->spec('bail-applications')->id,
        ]));

        $this->assertSame($lawyer->id, $match->lawyer->id);
        $this->assertTrue($match->matches['service']);
        $this->assertFalse($match->matches['specialization']);
    }

    // --------------------------------------------------------- specialization

    public function test_exact_specialization_match_works(): void
    {
        $lawyer = $this->lawyer([], ['family-law'], ['child-custody']);
        $this->lawyer([], ['family-law'], ['domestic-matters']);

        $result = $this->match(['specialization' => $this->spec('child-custody')->id]);

        $this->assertSame([$lawyer->id], $this->ids($result));
        $this->assertSame(100, $this->first($result)->score);
        $this->assertTrue($this->first($result)->matches['specialization']);
        $this->assertContains('Specializes in the requested area: Child Custody', $this->first($result)->reasons);
    }

    public function test_service_only_lawyer_gets_partial_score_when_specialization_also_requested(): void
    {
        $this->lawyer([], ['family-law']);

        $match = $this->first($this->match([
            'service' => $this->svc('family-law')->id,
            'specialization' => $this->spec('child-custody')->id,
        ]));

        // 25 of 45 requested weight => 55.56 => 56
        $this->assertSame(56, $match->score);
        $this->assertTrue($match->matches['service']);
        $this->assertFalse($match->matches['specialization']);
        $this->assertContains('Does not list the requested specialization', $match->gaps);
    }

    // --------------------------------------------------------------- location

    public function test_same_city_receives_a_location_match(): void
    {
        $lawyer = $this->lawyer(['city' => 'Lahore']);

        $match = $this->first($this->match(['city' => 'Lahore']));

        $this->assertSame($lawyer->id, $match->lawyer->id);
        $this->assertTrue($match->matches['location']);
        $this->assertSame(100, $match->score);
        $this->assertContains('Based in Lahore', $match->reasons);
    }

    public function test_city_matching_ignores_case_and_surrounding_whitespace(): void
    {
        $this->lawyer(['city' => 'Lahore']);

        $result = $this->match(['city' => '  lAHORE ']);

        $this->assertCount(1, $result->matches());
    }

    public function test_an_unrelated_city_never_receives_a_location_match(): void
    {
        $this->lawyer(['city' => 'Lahore'], ['family-law']);

        // City-only request: the Lahore lawyer has nothing in common -> not returned.
        $this->assertSame([], $this->ids($this->match(['city' => 'Karachi'])));

        // With a service also requested, the lawyer is returned but the city is NOT matched.
        $match = $this->first($this->match(['service' => $this->svc('family-law')->id, 'city' => 'Karachi']));
        $this->assertFalse($match->matches['location']);
        $this->assertSame(63, $match->score); // 25 / 40
        $this->assertNotContains('Based in Lahore', $match->reasons);
        $this->assertContains('Based in Lahore, not Karachi', $match->gaps);
    }

    public function test_similar_city_names_are_not_treated_as_nearby(): void
    {
        $this->lawyer(['city' => 'Rawalpindi']);
        $this->lawyer(['city' => 'Islamabad']);

        // No geographic intelligence: neighbouring cities are simply different cities.
        $this->assertSame([], $this->ids($this->match(['city' => 'Islamabad Capital'])));
        $this->assertCount(1, $this->match(['city' => 'Islamabad'])->matches());
    }

    // ----------------------------------------------------------------- budget

    public function test_a_fee_within_budget_is_a_full_budget_match(): void
    {
        $this->lawyer(['consultation_fee' => 2500], ['family-law']);

        $match = $this->first($this->match(['service' => $this->svc('family-law')->id, 'max_budget' => 3000]));

        $this->assertTrue($match->matches['budget']);
        $this->assertSame('within', $match->breakdown['budget']['fit']);
        $this->assertSame(100, $match->score);
        $this->assertContains('Consultation fee (Rs. 2,500) is within your budget', $match->reasons);
    }

    public function test_a_fee_near_the_budget_is_a_partial_match_and_not_reported_as_within(): void
    {
        $this->lawyer(['consultation_fee' => 3400], ['family-law']); // 3000 + 20% = 3600

        $match = $this->first($this->match(['service' => $this->svc('family-law')->id, 'max_budget' => 3000]));

        $this->assertFalse($match->matches['budget']);
        $this->assertSame('near', $match->breakdown['budget']['fit']);
        $this->assertSame(0.5, $match->breakdown['budget']['fraction']);
        $this->assertSame(86, $match->score); // (25 + 5) / 35
        $this->assertNotContains('Consultation fee (Rs. 3,400) is within your budget', $match->reasons);
    }

    public function test_a_fee_far_outside_budget_is_kept_but_scores_no_budget_points_in_flexible_mode(): void
    {
        $this->lawyer(['consultation_fee' => 9000], ['family-law']);

        $match = $this->first($this->match(['service' => $this->svc('family-law')->id, 'max_budget' => 3000]));

        $this->assertNotNull($match, 'Flexible mode must not empty the result set just because of budget');
        $this->assertFalse($match->matches['budget']);
        $this->assertSame('outside', $match->breakdown['budget']['fit']);
        $this->assertSame(71, $match->score); // 25 / 35
        $this->assertContains('Consultation fee (Rs. 9,000) is outside your budget', $match->gaps);
    }

    public function test_strict_budget_excludes_lawyers_outside_the_range_including_near_ones(): void
    {
        $within = $this->lawyer(['consultation_fee' => 2500, 'display_name' => 'Adv. Within'], ['family-law']);
        $this->lawyer(['consultation_fee' => 3400, 'display_name' => 'Adv. Near'], ['family-law']);
        $this->lawyer(['consultation_fee' => 9000, 'display_name' => 'Adv. Far'], ['family-law']);

        $result = $this->match(['service' => $this->svc('family-law')->id, 'max_budget' => 3000, 'strict' => ['budget']]);

        $this->assertSame([$within->id], $this->ids($result));
    }

    public function test_strict_budget_also_honours_a_minimum_and_excludes_unlisted_fees(): void
    {
        $ok = $this->lawyer(['consultation_fee' => 4000], ['family-law']);
        $this->lawyer(['consultation_fee' => 1000], ['family-law']);
        $this->lawyer(['consultation_fee' => null], ['family-law']);

        $result = $this->match(['service' => $this->svc('family-law')->id, 'min_budget' => 3000, 'max_budget' => 5000, 'strict' => ['budget']]);

        $this->assertSame([$ok->id], $this->ids($result));
    }

    public function test_a_lawyer_with_no_listed_fee_is_never_reported_as_a_budget_match(): void
    {
        $this->lawyer(['consultation_fee' => null], ['family-law']);

        $match = $this->first($this->match(['service' => $this->svc('family-law')->id, 'max_budget' => 3000]));

        $this->assertFalse($match->matches['budget']);
        $this->assertSame('unknown', $match->breakdown['budget']['fit']);
        $this->assertContains('Consultation fee not listed', $match->gaps);
    }

    public function test_budget_alone_finds_lawyers_within_or_near_budget_only(): void
    {
        $within = $this->lawyer(['consultation_fee' => 2000]);
        $near = $this->lawyer(['consultation_fee' => 3500]);
        $this->lawyer(['consultation_fee' => 9000]);

        $ids = $this->ids($this->match(['max_budget' => 3000]));

        $this->assertSame([$within->id, $near->id], $ids); // within (100) before near (50)
    }

    // ------------------------------------------------------------- experience

    public function test_meeting_the_minimum_experience_matches_and_exceeding_it_scores_higher(): void
    {
        $meets = $this->lawyer(['years_of_experience' => 5, 'display_name' => 'Adv. Meets'], ['family-law']);
        $exceeds = $this->lawyer(['years_of_experience' => 15, 'display_name' => 'Adv. Exceeds'], ['family-law']);

        $result = $this->match(['service' => $this->svc('family-law')->id, 'min_experience' => 5]);

        $this->assertSame([$exceeds->id, $meets->id], $this->ids($result));
        $this->assertSame(100, $result->matches()[0]->score);
        $this->assertSame(93, $result->matches()[1]->score); // (25 + 7*0.7) / 32
        $this->assertTrue($result->matches()[1]->matches['experience']);
        $this->assertContains('5 years of experience (meets your minimum of 5)', $result->matches()[1]->reasons);
        $this->assertContains('15 years of experience (exceeds your minimum of 5)', $result->matches()[0]->reasons);
    }

    public function test_below_the_minimum_experience_earns_no_experience_points_and_no_match(): void
    {
        $this->lawyer(['years_of_experience' => 3], ['family-law']);

        $match = $this->first($this->match(['service' => $this->svc('family-law')->id, 'min_experience' => 5]));

        $this->assertFalse($match->matches['experience']);
        $this->assertSame(0.0, $match->breakdown['experience']['fraction']);
        $this->assertSame(78, $match->score); // 25 / 32
        $this->assertContains('3 years of experience is below your minimum of 5', $match->gaps);
    }

    public function test_strict_experience_excludes_lawyers_below_the_minimum(): void
    {
        $ok = $this->lawyer(['years_of_experience' => 8], ['family-law']);
        $this->lawyer(['years_of_experience' => 2], ['family-law']);

        $result = $this->match(['service' => $this->svc('family-law')->id, 'min_experience' => 5, 'strict' => ['experience']]);

        $this->assertSame([$ok->id], $this->ids($result));
    }

    public function test_experience_is_not_evaluated_when_not_requested(): void
    {
        $this->lawyer(['years_of_experience' => 30], ['family-law']);

        $match = $this->first($this->match(['service' => $this->svc('family-law')->id]));

        $this->assertNull($match->matches['experience']);
        $this->assertArrayNotHasKey('experience', $match->breakdown);
    }

    // ----------------------------------------------------------- availability

    public function test_recurring_availability_on_the_requested_day_matches(): void
    {
        $available = $this->lawyer([], ['family-law']);
        LawyerAvailability::factory()->create(['lawyer_profile_id' => $available->id, 'day_of_week' => 1]);

        $other = $this->lawyer([], ['family-law']);
        LawyerAvailability::factory()->create(['lawyer_profile_id' => $other->id, 'day_of_week' => 4]);

        $result = $this->match(['service' => $this->svc('family-law')->id, 'available_day' => 1]);

        $this->assertSame($available->id, $result->matches()[0]->lawyer->id);
        $this->assertTrue($result->matches()[0]->matches['availability']);
        $this->assertContains('Has recurring availability on Monday', $result->matches()[0]->reasons);
        $this->assertFalse($result->matches()[1]->matches['availability']);
    }

    public function test_unavailable_and_one_off_slots_do_not_count_as_recurring_availability(): void
    {
        $lawyer = $this->lawyer([], ['family-law']);
        LawyerAvailability::factory()->create(['lawyer_profile_id' => $lawyer->id, 'day_of_week' => 1, 'is_available' => false]);
        LawyerAvailability::factory()->create(['lawyer_profile_id' => $lawyer->id, 'day_of_week' => 1, 'is_recurring' => false, 'specific_date' => now()->addDay()->toDateString()]);

        $match = $this->first($this->match(['service' => $this->svc('family-law')->id, 'available_day' => 1]));

        $this->assertFalse($match->matches['availability']);
    }

    // ----------------------------------------------------- score & normalising

    public function test_a_lawyer_satisfying_every_requested_criterion_scores_exactly_100(): void
    {
        $lawyer = $this->lawyer(['city' => 'Lahore', 'consultation_fee' => 2500, 'years_of_experience' => 25], ['divorce'], ['child-custody']);
        LawyerAvailability::factory()->create(['lawyer_profile_id' => $lawyer->id, 'day_of_week' => 2]);

        $match = $this->first($this->match([
            'problem' => 'divorce', // service_direct is 0.9, so the problem alone caps below 100; drop it below
            'service' => $this->svc('divorce')->id,
        ]));
        $this->assertLessThan(100, $match->score);

        $match = $this->first($this->match([
            'service' => $this->svc('divorce')->id,
            'specialization' => $this->spec('child-custody')->id,
            'city' => 'Lahore',
            'min_budget' => 2000,
            'max_budget' => 3000,
            'min_experience' => 10,
            'available_day' => 2,
        ]));

        $this->assertSame(100, $match->score);
        $this->assertSame(
            ['service' => true, 'specialization' => true, 'problem' => null, 'location' => true, 'budget' => true, 'experience' => true, 'availability' => true],
            $match->matches
        );
    }

    public function test_scores_are_integers_within_0_to_100_for_many_shapes(): void
    {
        foreach (range(1, 12) as $i) {
            $this->lawyer([
                'city' => ['Karachi', 'Lahore', 'Islamabad'][$i % 3],
                'consultation_fee' => $i * 900,
                'years_of_experience' => $i * 2,
            ], [['family-law', 'criminal-law', 'divorce'][$i % 3]]);
        }

        $inputs = [
            ['service' => $this->svc('family-law')->id, 'city' => 'Lahore', 'max_budget' => 4000, 'min_experience' => 6],
            ['city' => 'Karachi', 'min_budget' => 1000, 'max_budget' => 20000],
            ['service' => $this->svc('divorce')->id, 'min_experience' => 70],
            ['problem' => 'divorce and custody', 'city' => 'Islamabad'],
        ];

        foreach ($inputs as $input) {
            foreach ($this->match($input)->matches() as $match) {
                $this->assertIsInt($match->score);
                $this->assertGreaterThanOrEqual(1, $match->score);
                $this->assertLessThanOrEqual(100, $match->score);
                $this->assertEqualsWithDelta(
                    $match->score,
                    array_sum(array_column($match->breakdown, 'points')) / array_sum(array_column($match->breakdown, 'weight')) * 100,
                    0.5,
                    'Score must be re-derivable from its breakdown'
                );
            }
        }
    }

    public function test_score_is_deterministic_and_identical_input_gives_identical_ranking(): void
    {
        foreach (range(1, 15) as $i) {
            $this->lawyer([
                'display_name' => 'Adv. Person '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'city' => $i % 2 ? 'Karachi' : 'Lahore',
                'consultation_fee' => 1000 + $i * 400,
                'years_of_experience' => $i % 7,
            ], [$i % 2 ? 'family-law' : 'divorce']);
        }

        $input = ['service' => $this->svc('family-law')->id, 'city' => 'Karachi', 'max_budget' => 4000, 'min_experience' => 3, 'problem' => 'divorce'];

        $snapshot = fn () => $this->match($input)->matches()->map(fn ($m) => [$m->lawyer->id, $m->score, $m->reasons])->all();

        $this->assertSame($snapshot(), $snapshot());
        $this->assertNotEmpty($snapshot());
    }

    // ------------------------------------------------------------------ ranking

    public function test_a_higher_scoring_lawyer_ranks_above_a_lower_scoring_one(): void
    {
        $weak = $this->lawyer(['city' => 'Lahore', 'display_name' => 'Adv. A Weak'], ['family-law']);
        $strong = $this->lawyer(['city' => 'Karachi', 'display_name' => 'Adv. Z Strong'], ['family-law']);

        $result = $this->match(['service' => $this->svc('family-law')->id, 'city' => 'Karachi']);

        $this->assertSame([$strong->id, $weak->id], $this->ids($result));
        $this->assertGreaterThan($result->matches()[1]->score, $result->matches()[0]->score);
    }

    public function test_ties_break_by_experience_then_name_then_id(): void
    {
        $b = $this->lawyer(['display_name' => 'Adv. B', 'years_of_experience' => 5], ['family-law']);
        $a = $this->lawyer(['display_name' => 'Adv. A', 'years_of_experience' => 5], ['family-law']);
        $senior = $this->lawyer(['display_name' => 'Adv. Zed', 'years_of_experience' => 20], ['family-law']);

        $result = $this->match(['service' => $this->svc('family-law')->id]);

        $this->assertSame([$senior->id, $a->id, $b->id], $this->ids($result));
    }

    public function test_the_limit_is_respected_and_capped_by_config(): void
    {
        foreach (range(1, 6) as $i) {
            $this->lawyer(['display_name' => 'Adv. '.$i], ['family-law']);
        }

        $this->assertCount(3, $this->match(['service' => $this->svc('family-law')->id, 'limit' => 3])->matches());
        $this->assertCount(6, $this->match(['service' => $this->svc('family-law')->id])->matches());
        $this->assertSame(50, \App\Services\Matching\MatchCriteria::fromArray(['limit' => 9999])->limit);
    }

    // ------------------------------------------------------------------ reasons

    public function test_reasons_only_describe_conditions_that_actually_held(): void
    {
        $this->lawyer(['city' => 'Lahore', 'consultation_fee' => 9000, 'years_of_experience' => 2], ['family-law']);

        $match = $this->first($this->match([
            'service' => $this->svc('family-law')->id,
            'city' => 'Karachi',
            'max_budget' => 3000,
            'min_experience' => 10,
        ]));

        $this->assertSame(['Offers the requested service: Family Law'], $match->reasons);
        $this->assertTrue($match->matches['service']);
        $this->assertFalse($match->matches['location']);
        $this->assertFalse($match->matches['budget']);
        $this->assertFalse($match->matches['experience']);
        $this->assertCount(3, $match->gaps);
    }

    public function test_every_true_match_has_a_reason_and_every_reason_has_a_true_or_near_match(): void
    {
        $this->lawyer(['city' => 'Karachi', 'consultation_fee' => 2500, 'years_of_experience' => 12], ['family-law'], ['child-custody']);

        $match = $this->first($this->match([
            'service' => $this->svc('family-law')->id,
            'specialization' => $this->spec('child-custody')->id,
            'city' => 'Karachi',
            'max_budget' => 3000,
            'min_experience' => 10,
        ]));

        $trueCount = count(array_filter($match->matches, fn ($v) => $v === true));
        $this->assertSame($trueCount, count($match->reasons));
        $this->assertSame([], $match->gaps);
    }

    // -------------------------------------------------------------- config

    public function test_weights_are_configurable_and_change_the_ranking(): void
    {
        $cityFirst = $this->lawyer(['city' => 'Lahore', 'consultation_fee' => 9000, 'display_name' => 'Adv. City'], ['family-law']);
        $budgetFirst = $this->lawyer(['city' => 'Karachi', 'consultation_fee' => 2000, 'display_name' => 'Adv. Budget'], ['family-law']);
        $input = ['service' => $this->svc('family-law')->id, 'city' => 'Lahore', 'max_budget' => 3000];

        $default = config('lawyer_matching');

        $cityHeavy = $default;
        $cityHeavy['weights']['location'] = 90;
        $budgetHeavy = $default;
        $budgetHeavy['weights']['budget'] = 90;

        $this->assertSame($cityFirst->id, $this->ids($this->match($input, $cityHeavy))[0]);
        $this->assertSame($budgetFirst->id, $this->ids($this->match($input, $budgetHeavy))[0]);
    }

    public function test_a_zero_weight_disables_that_component(): void
    {
        $this->lawyer(['city' => 'Lahore'], ['family-law']);
        $config = config('lawyer_matching');
        $config['weights']['location'] = 0;

        $match = $this->first($this->match(['service' => $this->svc('family-law')->id, 'city' => 'Karachi'], $config));

        $this->assertArrayNotHasKey('location', $match->breakdown);
        $this->assertSame(100, $match->score);
    }

    public function test_criteria_value_object_rejects_inconsistent_input(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new \App\Services\Matching\MatchCriteria(minBudget: 5000, maxBudget: 1000);
    }

    public function test_no_criteria_yields_an_empty_result_instead_of_every_lawyer(): void
    {
        $this->lawyer([], ['family-law']);

        $result = $this->match([]);

        $this->assertCount(0, $result->matches());
        $this->assertSame([], $result->meta()['applied_components']);
    }
}
