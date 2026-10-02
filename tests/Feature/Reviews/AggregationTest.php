<?php

namespace Tests\Feature\Reviews;

use App\Enums\ReviewStatus;
use App\Models\LawyerProfile;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 9, requirements #14-#16, #27, #31: rating aggregation
 * correctness (average/count/breakdown) and query-count discipline
 * for the public reviews listing.
 */
class AggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_average_and_count_are_computed_correctly(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();
        Review::factory()->create(['lawyer_profile_id' => $lawyer->id, 'rating' => 5, 'appointment_id' => null]);
        Review::factory()->create(['lawyer_profile_id' => $lawyer->id, 'rating' => 3, 'appointment_id' => null]);
        Review::factory()->create(['lawyer_profile_id' => $lawyer->id, 'rating' => 4, 'appointment_id' => null]);

        $reputation = app(ReviewService::class)->reputation($lawyer);

        $this->assertSame(3, $reputation['count']);
        $this->assertSame(4.0, $reputation['average']);
    }

    public function test_reputation_is_null_average_with_zero_count_when_there_are_no_reviews(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();

        $reputation = app(ReviewService::class)->reputation($lawyer);

        $this->assertNull($reputation['average']);
        $this->assertSame(0, $reputation['count']);
    }

    public function test_rejected_reviews_are_excluded_from_the_average_and_count(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();
        Review::factory()->create(['lawyer_profile_id' => $lawyer->id, 'rating' => 5, 'appointment_id' => null]);
        Review::factory()->create([
            'lawyer_profile_id' => $lawyer->id,
            'rating' => 1,
            'appointment_id' => null,
            'status' => ReviewStatus::REJECTED->value,
        ]);

        $reputation = app(ReviewService::class)->reputation($lawyer);

        $this->assertSame(1, $reputation['count']);
        $this->assertSame(5.0, $reputation['average']);
    }

    public function test_the_star_breakdown_matches_the_actual_reviews(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();
        Review::factory()->count(2)->create(['lawyer_profile_id' => $lawyer->id, 'rating' => 5, 'appointment_id' => null]);
        Review::factory()->create(['lawyer_profile_id' => $lawyer->id, 'rating' => 3, 'appointment_id' => null]);

        $reputation = app(ReviewService::class)->reputation($lawyer);

        $this->assertSame([1 => 0, 2 => 0, 3 => 1, 4 => 0, 5 => 2], $reputation['breakdown']);
    }

    public function test_the_public_lawyer_profile_shows_the_real_average_and_count(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();
        Review::factory()->create(['lawyer_profile_id' => $lawyer->id, 'rating' => 5, 'appointment_id' => null]);
        Review::factory()->create(['lawyer_profile_id' => $lawyer->id, 'rating' => 3, 'appointment_id' => null]);

        $this->get(route('lawyers.show', $lawyer))
            ->assertOk()
            ->assertSee('4.0')
            ->assertSee('2 reviews');
    }

    public function test_public_reviews_are_paginated_and_do_not_load_every_review_at_once(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();
        Review::factory()->count(15)->create(['lawyer_profile_id' => $lawyer->id, 'appointment_id' => null]);

        $page = app(ReviewService::class)->publicReviews($lawyer, 10);

        $this->assertSame(10, $page->count());
        $this->assertSame(15, $page->total());
    }

    public function test_public_reviews_are_ordered_newest_first(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create();
        $older = Review::factory()->create(['lawyer_profile_id' => $lawyer->id, 'appointment_id' => null, 'created_at' => now()->subDays(3)]);
        $newer = Review::factory()->create(['lawyer_profile_id' => $lawyer->id, 'appointment_id' => null, 'created_at' => now()]);

        $page = app(ReviewService::class)->publicReviews($lawyer, 10);

        $this->assertSame($newer->id, $page->first()->id);
        $this->assertSame($older->id, $page->last()->id);
    }

    public function test_loading_the_directory_with_many_lawyers_and_reviews_does_not_explode_query_count(): void
    {
        foreach (range(1, 5) as $i) {
            $lawyer = LawyerProfile::factory()->approved()->create();
            Review::factory()->count(3)->create(['lawyer_profile_id' => $lawyer->id, 'appointment_id' => null]);
        }

        DB::enableQueryLog();
        $this->get(route('lawyers.index'))->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // A handful of fixed queries (lawyers page + services/specializations
        // eager loads + rating aggregates as correlated subqueries + cities
        // list) regardless of how many lawyers are on the page -- nowhere
        // near "one query per lawyer", which would be 15+ here.
        $this->assertLessThan(15, $queryCount, 'Directory listing appears to run one review query per lawyer (N+1).');
    }
}
