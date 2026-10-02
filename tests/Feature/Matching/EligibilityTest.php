<?php

namespace Tests\Feature\Matching;

use App\Models\User;

/**
 * Eligibility is non-negotiable: only approved + active + public
 * lawyers (with an active owning account) can EVER be matched, no
 * matter how perfectly an ineligible profile fits the criteria.
 */
class EligibilityTest extends MatchingTestCase
{
    private function input(): array
    {
        return ['service' => $this->svc('family-law')->id];
    }

    public function test_an_approved_active_lawyer_can_match(): void
    {
        $lawyer = $this->lawyer([], ['family-law']);

        $this->assertSame([$lawyer->id], $this->ids($this->match($this->input())));
    }

    public function test_a_pending_lawyer_never_matches(): void
    {
        $this->lawyer(['verification_status' => 'pending'], ['family-law']);

        $this->assertSame([], $this->ids($this->match($this->input())));
    }

    public function test_a_rejected_lawyer_never_matches(): void
    {
        $this->lawyer(['verification_status' => 'rejected'], ['family-law']);

        $this->assertSame([], $this->ids($this->match($this->input())));
    }

    public function test_a_suspended_lawyer_never_matches(): void
    {
        $this->lawyer(['verification_status' => 'suspended'], ['family-law']);

        $this->assertSame([], $this->ids($this->match($this->input())));
    }

    public function test_an_inactive_lawyer_never_matches(): void
    {
        $this->lawyer(['is_active' => false], ['family-law']);

        $this->assertSame([], $this->ids($this->match($this->input())));
    }

    public function test_a_non_public_lawyer_never_matches(): void
    {
        $this->lawyer(['is_public' => false], ['family-law']);

        $this->assertSame([], $this->ids($this->match($this->input())));
    }

    public function test_a_lawyer_whose_user_account_is_suspended_never_matches(): void
    {
        $this->lawyer(['user_id' => User::factory()->lawyer()->suspended()], ['family-law']);

        $this->assertSame([], $this->ids($this->match($this->input())));
    }

    public function test_a_lawyer_whose_user_account_is_inactive_never_matches(): void
    {
        $this->lawyer(['user_id' => User::factory()->lawyer()->create(['status' => 'inactive'])], ['family-law']);

        $this->assertSame([], $this->ids($this->match($this->input())));
    }

    public function test_ineligible_lawyers_are_excluded_even_when_they_fit_every_criterion_perfectly(): void
    {
        $perfect = ['city' => 'Lahore', 'consultation_fee' => 2000, 'years_of_experience' => 20];
        $eligible = $this->lawyer($perfect + ['display_name' => 'Adv. Eligible'], ['family-law']);
        $this->lawyer($perfect + ['verification_status' => 'pending'], ['family-law']);
        $this->lawyer($perfect + ['verification_status' => 'rejected'], ['family-law']);
        $this->lawyer($perfect + ['verification_status' => 'suspended', 'is_active' => false], ['family-law']);
        $this->lawyer($perfect + ['is_active' => false], ['family-law']);

        $result = $this->match([
            'service' => $this->svc('family-law')->id,
            'city' => 'Lahore',
            'max_budget' => 3000,
            'min_experience' => 10,
        ]);

        $this->assertSame([$eligible->id], $this->ids($result));
        $this->assertSame(100, $this->first($result)->score);
    }

    public function test_eligibility_holds_for_every_criteria_shape(): void
    {
        $this->lawyer(['verification_status' => 'pending', 'city' => 'Lahore', 'consultation_fee' => 1000, 'years_of_experience' => 30], ['family-law', 'criminal-law'], ['child-custody']);

        $shapes = [
            ['city' => 'Lahore'],
            ['max_budget' => 5000],
            ['min_experience' => 10],
            ['problem' => 'divorce custody'],
            ['specialization' => $this->spec('child-custody')->id],
            ['city' => 'Lahore', 'strict' => ['location']],
        ];

        foreach ($shapes as $shape) {
            $this->assertSame([], $this->ids($this->match($shape)), 'Ineligible lawyer leaked for '.json_encode($shape));
        }
    }

    public function test_a_lawyer_who_becomes_ineligible_disappears_from_matches(): void
    {
        $lawyer = $this->lawyer([], ['family-law']);
        $this->assertSame([$lawyer->id], $this->ids($this->match($this->input())));

        $lawyer->update(['verification_status' => 'suspended']);

        $this->assertSame([], $this->ids($this->match($this->input())));
    }
}
