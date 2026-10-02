<?php

namespace Tests\Feature\Notifications;

use App\Enums\CaseEvent;
use App\Models\User;
use App\Notifications\LawyerApplicationApproved;
use App\Notifications\LawyerApplicationSubmitted;

class IntegrationTest extends NotificationTestCase
{
    public function test_appointment_notification_appears_with_destination(): void
    {
        [$lawyerUser, $profile] = $this->lawyerWithProfile();
        $customer = User::factory()->create();
        [$appointment] = $this->appointmentNotification($customer, $lawyerUser, $profile);

        $this->actingAs($customer)->get(route('notifications.index'))
            ->assertSee('Appointment')
            ->assertSee('Your appointment request was confirmed.')
            ->assertSee(route('customer.appointments.show', $appointment), false);
    }

    public function test_message_notification_links_to_conversation_and_hides_body(): void
    {
        [$lawyerUser, $profile] = $this->lawyerWithProfile();
        $customer = User::factory()->create();
        [$conversation] = $this->messageNotification($customer, $lawyerUser, $profile);

        $this->actingAs($lawyerUser)->get(route('notifications.index'))
            ->assertSee('You have a new message from')
            ->assertSee(route('lawyer.messages.show', $conversation), false);
    }

    public function test_review_notification_is_surfaced_for_the_lawyer(): void
    {
        [$lawyerUser, $profile] = $this->lawyerWithProfile();
        $this->reviewNotification($lawyerUser, $profile);

        $this->actingAs($lawyerUser)->get(route('notifications.index'))
            ->assertSee('Review')
            ->assertSee('-star review')
            ->assertSee(route('lawyer.reviews.index'), false);
    }

    public function test_case_and_document_notifications_use_separate_categories_and_link_to_the_case(): void
    {
        [$lawyerUser, $profile] = $this->lawyerWithProfile();
        $customer = User::factory()->create();
        $case = $this->caseFor($customer, $profile);
        $this->caseNotification($customer, $case, CaseEvent::ACTIVATED);
        $this->caseNotification($customer, $case, CaseEvent::DOCUMENT_UPLOADED);

        $response = $this->actingAs($customer)->get(route('notifications.index'))->assertOk();

        $response->assertSee('Your case is now active.');
        $response->assertSee('A new document was uploaded to your case.');
        $response->assertSee('Document');
        $response->assertSee(route('customer.cases.show', $case), false);
        $response->assertDontSee('documents/', false);
        $response->assertDontSee('stored_path', false);
    }

    public function test_lawyer_application_notifications_are_system_and_link_correctly(): void
    {
        $admin = User::factory()->admin()->create();
        $lawyer = User::factory()->lawyer()->create();
        $this->deliver($admin, new LawyerApplicationSubmitted($lawyer));
        $this->deliver($lawyer, new LawyerApplicationApproved());

        $this->actingAs($admin)->get(route('notifications.index'))
            ->assertSee('System')
            ->assertSee(route('admin.lawyer-verifications.index'), false);
        $this->actingAs($lawyer)->get(route('notifications.index'))
            ->assertSee(route('lawyer.dashboard'), false);
    }

    public function test_real_message_flow_creates_a_notification_visible_in_the_center(): void
    {
        [$lawyerUser, $profile] = $this->lawyerWithProfile();
        $customer = User::factory()->create();
        $conversation = \App\Models\Conversation::factory()->create(['customer_id' => $customer->id, 'lawyer_profile_id' => $profile->id]);
        $secret = 'Confidential settlement figure 500000';

        $this->actingAs($customer)->post(route('customer.messages.store', $conversation), ['body' => $secret]);

        $this->actingAs($lawyerUser)->get(route('notifications.index'))
            ->assertSee('You have a new message from')
            ->assertDontSee($secret);
    }

    public function test_notifications_for_other_roles_do_not_generate_cross_role_links(): void
    {
        [$lawyerUser, $profile] = $this->lawyerWithProfile();
        $customer = User::factory()->create();
        $case = $this->caseFor($customer, $profile);
        $this->caseNotification($lawyerUser, $case, CaseEvent::ASSIGNED);

        $this->actingAs($lawyerUser)->get(route('notifications.index'))
            ->assertSee(route('lawyer.cases.show', $case), false)
            ->assertDontSee(route('customer.cases.show', $case), false);
    }
}
