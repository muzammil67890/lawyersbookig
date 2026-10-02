<?php

namespace Tests\Feature\Lawyers;

use App\Models\LawyerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 Half 2 tightened LawyerProfile::scopeSearchable()/isEligible()
 * so a lawyer whose OWNING USER ACCOUNT is suspended/inactive is
 * ineligible everywhere -- directory, profile page, and matching --
 * even if the profile row itself still reads approved + active.
 */
class EligibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_lawyer_with_a_suspended_user_account_is_not_searchable_or_eligible(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => User::factory()->lawyer()->suspended()]);

        $this->assertFalse($lawyer->fresh()->isEligible());
        $this->assertFalse(LawyerProfile::searchable()->whereKey($lawyer->id)->exists());
    }

    public function test_a_lawyer_with_an_active_user_account_is_searchable_and_eligible(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();

        $this->assertTrue($lawyer->fresh()->isEligible());
        $this->assertTrue(LawyerProfile::searchable()->whereKey($lawyer->id)->exists());
    }

    public function test_the_directory_and_profile_page_hide_a_lawyer_whose_account_is_suspended(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create([
            'display_name' => 'Adv. Suspended Account',
            'user_id' => User::factory()->lawyer()->suspended(),
        ]);

        $this->get(route('lawyers.index'))->assertDontSee('Adv. Suspended Account');
        $this->get(route('lawyers.show', $lawyer->id))->assertNotFound();
    }

    public function test_the_directory_max_fee_filter_works_without_a_min_fee(): void
    {
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Cheap One', 'consultation_fee' => 1000]);
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Pricey One', 'consultation_fee' => 9000]);

        $this->get(route('lawyers.index', ['max_fee' => 2000]))
            ->assertOk()
            ->assertSee('Adv. Cheap One')
            ->assertDontSee('Adv. Pricey One');
    }
}
