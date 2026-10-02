<?php

namespace Tests\Feature\Reviews;

use App\Enums\ReviewStatus;
use App\Models\Appointment;
use App\Models\LawyerProfile;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Phase 9, requirement #19: deliberately narrow admin oversight --
 * reject/restore only, never edit, never hard-delete, admin-only.
 */
class ModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function review(): array
    {
        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);
        $customer = User::factory()->create();
        $appointment = Appointment::factory()->completed()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
        ]);
        $review = Review::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
            'appointment_id' => $appointment->id,
        ]);

        return [$review, $customer, $lawyerUser, $lawyer];
    }

    public function test_admin_can_reject_a_review(): void
    {
        [$review] = $this->review();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.reviews.reject', $review))
            ->assertRedirect(route('admin.reviews.show', $review));

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => ReviewStatus::REJECTED->value]);
    }

    public function test_admin_can_restore_a_rejected_review(): void
    {
        [$review] = $this->review();
        $review->update(['status' => ReviewStatus::REJECTED]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.reviews.restore', $review));

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => ReviewStatus::PUBLISHED->value]);
    }

    public function test_a_customer_cannot_moderate_reviews(): void
    {
        [$review, $customer] = $this->review();

        $this->actingAs($customer)
            ->post(route('admin.reviews.reject', $review))
            ->assertForbidden();
    }

    public function test_a_lawyer_cannot_moderate_reviews_even_about_themselves(): void
    {
        [$review, , $lawyerUser] = $this->review();

        $this->actingAs($lawyerUser)
            ->post(route('admin.reviews.reject', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'status' => ReviewStatus::PUBLISHED->value]);
    }

    public function test_a_guest_cannot_access_the_admin_review_queue(): void
    {
        $this->get(route('admin.reviews.index'))->assertRedirect(route('login'));
    }

    public function test_a_rejected_review_disappears_from_the_public_profile(): void
    {
        [$review, , , $lawyer] = $this->review();
        $review->update(['comment' => 'This will be hidden.', 'status' => ReviewStatus::REJECTED]);

        $this->get(route('lawyers.show', $lawyer))
            ->assertOk()
            ->assertDontSee('This will be hidden.');
    }

    public function test_admin_reject_action_never_edits_the_reviews_rating_or_comment(): void
    {
        [$review] = $this->review();
        $originalRating = $review->rating;
        $originalComment = $review->comment;
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.reviews.reject', $review));

        $review->refresh();
        $this->assertSame($originalRating, $review->rating);
        $this->assertSame($originalComment, $review->comment);
    }

    public function test_admin_review_queue_can_be_filtered_by_status(): void
    {
        [$publishedReview] = $this->review();
        [$rejectedReview] = $this->review();
        $rejectedReview->update(['status' => ReviewStatus::REJECTED]);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.reviews.index', ['status' => 'rejected']));

        $response->assertOk();
        $response->assertSee(route('admin.reviews.show', $rejectedReview));
        $response->assertDontSee(route('admin.reviews.show', $publishedReview));
    }

    public function test_submitting_a_review_notifies_the_reviewed_lawyer_without_leaking_the_comment(): void
    {
        Notification::fake();

        $lawyerUser = User::factory()->lawyer()->create();
        $lawyer = LawyerProfile::factory()->approved()->create(['user_id' => $lawyerUser->id]);
        $customer = User::factory()->create();
        $appointment = Appointment::factory()->completed()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $lawyer->id,
        ]);

        $this->actingAs($customer)->post(route('customer.appointments.review.store', $appointment), [
            'rating' => 5,
            'comment' => 'Very private and specific detail about my case.',
        ]);

        Notification::assertSentTo($lawyerUser, ReviewNotification::class, function ($notification) {
            $data = $notification->toArray($notification);

            return ! str_contains(json_encode($data), 'Very private and specific detail about my case.');
        });
    }
}
