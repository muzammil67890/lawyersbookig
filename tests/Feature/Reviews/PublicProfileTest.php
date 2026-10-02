<?php

namespace Tests\Feature\Reviews;

use App\Models\LawyerProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9, requirement #13: the public lawyer profile must show
 * trustworthy rating/review information while never leaking a
 * reviewing customer's private contact details.
 */
class PublicProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reviewers_email_and_phone_are_never_present_on_the_public_profile(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();
        $customer = User::factory()->create([
            'email' => 'private-reviewer@example.com',
            'phone' => '+92 300 1234567',
        ]);
        Review::factory()->create([
            'lawyer_profile_id' => $lawyer->id,
            'customer_id' => $customer->id,
            'appointment_id' => null,
            'comment' => 'Great service, would recommend.',
        ]);

        $this->get(route('lawyers.show', $lawyer))
            ->assertOk()
            ->assertSee($customer->name)
            ->assertDontSee('private-reviewer@example.com')
            ->assertDontSee('+92 300 1234567');
    }

    public function test_a_guest_can_view_published_reviews_on_a_public_profile(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();
        Review::factory()->create([
            'lawyer_profile_id' => $lawyer->id,
            'appointment_id' => null,
            'comment' => 'Visible to everyone.',
        ]);

        $this->get(route('lawyers.show', $lawyer))
            ->assertOk()
            ->assertSee('Visible to everyone.');
    }

    public function test_a_non_searchable_lawyers_profile_and_reviews_are_not_reachable(): void
    {
        $lawyer = LawyerProfile::factory()->pending()->create();
        Review::factory()->create([
            'lawyer_profile_id' => $lawyer->id,
            'appointment_id' => null,
            'comment' => 'Should never be shown.',
        ]);

        $this->get(route('lawyers.show', $lawyer))->assertNotFound();
    }

    public function test_the_directory_listing_shows_aggregate_rating_without_per_lawyer_review_details(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();
        Review::factory()->create([
            'lawyer_profile_id' => $lawyer->id,
            'appointment_id' => null,
            'comment' => 'A specific written comment.',
            'rating' => 5,
        ]);

        $response = $this->get(route('lawyers.index'))->assertOk();
        $response->assertSee('5.0');
        // The directory card shows an aggregate, not individual comments.
        $response->assertDontSee('A specific written comment.');
    }
}
