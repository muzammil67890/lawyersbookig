<?php

namespace Tests\Feature\Notifications;

use App\Enums\AppointmentEvent;
use App\Enums\CaseEvent;
use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\LawyerProfile;
use App\Models\LegalCase;
use App\Models\Review;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use App\Notifications\CaseNotification;
use App\Notifications\NewMessageNotification;
use App\Notifications\ReviewNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

/** Phase 10 shared fixtures: real notification classes, delivered through the real database channel. */
abstract class NotificationTestCase extends TestCase
{
    use RefreshDatabase;

    protected function lawyerWithProfile(): array
    {
        $user = User::factory()->lawyer()->create();
        $profile = LawyerProfile::factory()->approved()->create(['user_id' => $user->id]);

        return [$user, $profile];
    }

    /** Sends a real, stored notification and returns its DatabaseNotification row. */
    protected function deliver(User $user, $notification): DatabaseNotification
    {
        $user->notify($notification);

        return $user->notifications()->reorder()->latest('created_at')->firstOrFail();
    }

    protected function appointmentNotification(User $customer, User $lawyerUser, LawyerProfile $profile, AppointmentEvent $event = AppointmentEvent::CONFIRMED): array
    {
        $appointment = Appointment::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $profile->id,
        ]);

        return [$appointment, $this->deliver($customer, new AppointmentNotification($appointment, $event))];
    }

    protected function caseFor(User $customer, LawyerProfile $profile): LegalCase
    {
        return LegalCase::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $profile->id,
        ]);
    }

    protected function messageNotification(User $customer, User $lawyerUser, LawyerProfile $profile): array
    {
        $conversation = Conversation::factory()->create([
            'customer_id' => $customer->id,
            'lawyer_profile_id' => $profile->id,
        ]);

        return [$conversation, $this->deliver($lawyerUser, new NewMessageNotification($conversation, $customer))];
    }

    protected function reviewNotification(User $lawyerUser, LawyerProfile $profile): DatabaseNotification
    {
        $review = Review::factory()->create(['lawyer_profile_id' => $profile->id]);

        return $this->deliver($lawyerUser, new ReviewNotification($review));
    }

    protected function caseNotification(User $recipient, LegalCase $case, CaseEvent $event): DatabaseNotification
    {
        return $this->deliver($recipient, new CaseNotification($case, $event));
    }
}
