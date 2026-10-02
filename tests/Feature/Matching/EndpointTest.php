<?php

namespace Tests\Feature\Matching;

use App\Models\Service;
use App\Models\Specialization;

class EndpointTest extends MatchingTestCase
{
    public function test_the_match_page_loads_without_parameters_and_shows_no_results(): void
    {
        $this->lawyer(['display_name' => 'Adv. Hidden Until Asked'], ['family-law']);

        $this->get(route('lawyers.match'))
            ->assertOk()
            ->assertSee('Tell us what you need')
            ->assertDontSee('Adv. Hidden Until Asked');
    }

    public function test_the_match_route_does_not_collide_with_the_lawyer_profile_route(): void
    {
        $lawyer = $this->lawyer(['display_name' => 'Adv. Profile Page'], ['family-law']);

        $this->get('/lawyers/match')->assertOk()->assertSee('Match with the right lawyer');
        $this->get(route('lawyers.show', $lawyer->id))->assertOk()->assertSee('Adv. Profile Page');
        $this->get('/lawyers')->assertOk();
    }

    public function test_the_html_page_shows_ranked_matches_with_score_and_reasons(): void
    {
        $this->lawyer(['display_name' => 'Adv. Best Match', 'city' => 'Lahore', 'consultation_fee' => 2500], ['family-law']);

        $this->get(route('lawyers.match', ['service' => $this->svc('family-law')->id, 'city' => 'Lahore', 'max_budget' => 3000]))
            ->assertOk()
            ->assertSee('Adv. Best Match')
            ->assertSee('MATCH 100/100')
            ->assertSee('Offers the requested service: Family Law')
            ->assertSee('Based in Lahore')
            ->assertSee('is within your budget')
            ->assertSee('Rs. 2,500');
    }

    public function test_the_json_response_has_the_documented_structure(): void
    {
        $lawyer = $this->lawyer(['display_name' => 'Adv. Json', 'city' => 'Lahore'], ['family-law'], ['child-custody']);

        $response = $this->getJson(route('lawyers.match', [
            'service' => $this->svc('family-law')->id,
            'specialization' => $this->spec('child-custody')->id,
            'city' => 'Lahore',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lawyer.id', $lawyer->id)
            ->assertJsonPath('data.0.lawyer.name', 'Adv. Json')
            ->assertJsonPath('data.0.score', 100)
            ->assertJsonPath('data.0.matches.service', true)
            ->assertJsonPath('data.0.matches.specialization', true)
            ->assertJsonPath('data.0.matches.location', true)
            ->assertJsonPath('data.0.matches.budget', null)
            ->assertJsonPath('meta.max_score', 100)
            ->assertJsonPath('meta.returned', 1)
            ->assertJsonStructure([
                'data' => [['lawyer' => ['id', 'name', 'title', 'city', 'years_of_experience', 'consultation_fee', 'bio_excerpt', 'profile_url', 'services', 'specializations'], 'score', 'reasons', 'gaps', 'matches', 'breakdown']],
                'meta' => ['max_score', 'method', 'eligibility', 'criteria', 'applied_components', 'candidates_considered', 'candidate_cap_reached', 'returned'],
            ]);
    }

    public function test_an_empty_result_is_a_valid_response_not_an_error(): void
    {
        $this->getJson(route('lawyers.match', ['city' => 'Nowhere']))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.returned', 0);
    }

    public function test_json_without_any_criterion_is_rejected(): void
    {
        $this->getJson(route('lawyers.match'))->assertUnprocessable()->assertJsonValidationErrors('criteria');
        $this->getJson(route('lawyers.match', ['limit' => 5, 'strict' => ['budget']]))->assertUnprocessable();
    }

    public function test_the_html_form_redirects_back_with_errors_on_invalid_input(): void
    {
        $this->get(route('lawyers.match', ['min_experience' => 999]))
            ->assertRedirect(route('lawyers.match'))
            ->assertSessionHasErrors('min_experience');
    }

    public function test_invalid_parameters_are_rejected(): void
    {
        $service = $this->svc('family-law');
        $custody = $this->spec('child-custody');            // belongs to family-law
        $bail = $this->spec('bail-applications');           // belongs to criminal-law
        $inactive = Service::factory()->create(['is_active' => false]);

        $bad = [
            'service' => [['service' => 999999], 'service'],
            'inactive service' => [['service' => $inactive->id], 'service'],
            'non-numeric service' => [['service' => 'family-law'], 'service'],
            'unknown specialization' => [['specialization' => 999999], 'specialization'],
            'specialization of another service' => [['service' => $service->id, 'specialization' => $bail->id], 'specialization'],
            'array problem' => [['problem' => ['x']], 'problem'],
            'too long problem' => [['problem' => str_repeat('a', 501)], 'problem'],
            'array city' => [['city' => ['x']], 'city'],
            'too long city' => [['city' => str_repeat('a', 101)], 'city'],
            'negative min budget' => [['min_budget' => -1], 'min_budget'],
            'non-numeric budget' => [['max_budget' => 'cheap'], 'max_budget'],
            'huge budget' => [['max_budget' => 99999999999], 'max_budget'],
            'max below min' => [['min_budget' => 5000, 'max_budget' => 1000], 'max_budget'],
            'negative experience' => [['min_experience' => -3], 'min_experience'],
            'huge experience' => [['min_experience' => 500], 'min_experience'],
            'fractional experience' => [['min_experience' => 2.5], 'min_experience'],
            'day 7' => [['available_day' => 7], 'available_day'],
            'day -1' => [['available_day' => -1], 'available_day'],
            'unknown strict option' => [['service' => $service->id, 'strict' => ['everything']], 'strict.0'],
            'zero limit' => [['service' => $service->id, 'limit' => 0], 'limit'],
            'huge limit' => [['service' => $service->id, 'limit' => 1000], 'limit'],
        ];

        foreach ($bad as $label => [$query, $errorKey]) {
            $response = $this->getJson(route('lawyers.match', $query));

            $this->assertSame(422, $response->getStatusCode(), "Expected 422 for: {$label}");
            $response->assertJsonValidationErrors($errorKey);
        }
    }

    public function test_max_budget_alone_is_valid(): void
    {
        $this->lawyer(['consultation_fee' => 2000], ['family-law']);

        $this->getJson(route('lawyers.match', ['max_budget' => 3000]))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_matching_specialization_and_service_pair_is_accepted(): void
    {
        $this->getJson(route('lawyers.match', [
            'service' => $this->svc('family-law')->id,
            'specialization' => $this->spec('child-custody')->id,
        ]))->assertOk();
    }

    public function test_strict_can_be_sent_as_a_single_value(): void
    {
        $ok = $this->lawyer(['consultation_fee' => 2000], ['family-law']);
        $this->lawyer(['consultation_fee' => 9000], ['family-law']);

        $this->getJson(route('lawyers.match', ['service' => $this->svc('family-law')->id, 'max_budget' => 3000, 'strict' => 'budget']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lawyer.id', $ok->id);
    }

    public function test_the_endpoint_is_read_only(): void
    {
        $this->post('/lawyers/match')->assertStatus(405);
        $this->put('/lawyers/match')->assertStatus(405);
        $this->delete('/lawyers/match')->assertStatus(405);
    }

    public function test_the_endpoint_is_rate_limited(): void
    {
        $last = null;
        foreach (range(1, 31) as $i) {
            $last = $this->getJson(route('lawyers.match', ['city' => 'Karachi']));
        }

        $last->assertStatus(429);
    }

    public function test_the_criteria_echoed_in_meta_reflect_what_was_applied(): void
    {
        $this->getJson(route('lawyers.match', ['city' => 'Lahore', 'max_budget' => '3000', 'strict' => ['location']]))
            ->assertOk()
            ->assertJsonPath('meta.criteria.city', 'Lahore')
            ->assertJsonPath('meta.criteria.max_budget', 3000)
            ->assertJsonPath('meta.criteria.strict', ['location'])
            ->assertJsonPath('meta.applied_components.location', 15)
            ->assertJsonPath('meta.applied_components.budget', 10);
    }
}
