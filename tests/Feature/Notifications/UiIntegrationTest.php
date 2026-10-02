<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Models\User;
use App\Notifications\LawyerApplicationApproved;

class UiIntegrationTest extends NotificationTestCase
{
    public function test_navbar_shows_real_unread_count_on_protected_pages(): void
    {
        $user = User::factory()->create();
        $this->deliver($user, new LawyerApplicationApproved());
        $this->deliver($user, new LawyerApplicationApproved());

        $this->actingAs($user)->get(route('customer.appointments.index'))
            ->assertSee(route('notifications.index'), false)
            ->assertSee('Notifications, 2 unread', false);
    }

    public function test_navbar_bell_is_absent_for_guests(): void
    {
        $this->get(route('login'))->assertDontSee(route('notifications.index'), false);
    }

    public function test_public_site_header_shows_bell_for_authenticated_user_once(): void
    {
        $user = User::factory()->create();
        $this->deliver($user, new LawyerApplicationApproved());

        $html = $this->actingAs($user)->get(route('lawyers.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-label="Notifications, 1 unread"'));
    }

    public function test_dashboards_show_a_recent_notifications_panel(): void
    {
        $customer = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->deliver($customer, new LawyerApplicationApproved());

        $this->actingAs($customer)->get(route('customer.dashboard'))->assertOk()->assertSee('All notifications');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Nothing new yet');
    }

    public function test_dashboard_panel_is_limited_to_five_recent_items(): void
    {
        $user = User::factory()->lawyer()->create();
        foreach (range(1, 8) as $i) {
            $this->deliver($user, new LawyerApplicationApproved());
        }

        $this->actingAs($user)->get(route('lawyer.dashboard'))
            ->assertViewHas('recentNotifications', fn ($c) => $c->count() === 5);
    }

    public function test_category_mapping_covers_every_existing_notification_type(): void
    {
        $map = [
            'appointment_booked' => NotificationCategory::APPOINTMENT,
            'appointment_rescheduled' => NotificationCategory::APPOINTMENT,
            'new_message' => NotificationCategory::MESSAGE,
            'case_assigned' => NotificationCategory::CASE,
            'case_closed' => NotificationCategory::CASE,
            'case_document_uploaded' => NotificationCategory::DOCUMENT,
            'review_submitted' => NotificationCategory::REVIEW,
            'lawyer_application_submitted' => NotificationCategory::SYSTEM,
            'lawyer_application_approved' => NotificationCategory::SYSTEM,
            'lawyer_application_rejected' => NotificationCategory::SYSTEM,
            'something_unknown' => NotificationCategory::SYSTEM,
            null => NotificationCategory::SYSTEM,
        ];

        foreach ($map as $type => $expected) {
            $this->assertSame($expected, NotificationCategory::fromType($type === '' ? null : $type), (string) $type);
        }
    }

    public function test_notification_queries_do_not_grow_with_row_count(): void
    {
        $user = User::factory()->lawyer()->create();
        foreach (range(1, 15) as $i) {
            $this->deliver($user, new LawyerApplicationApproved());
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($user)->get(route('notifications.index'))->assertOk();
        $queries = count(\Illuminate\Support\Facades\DB::getQueryLog());

        $this->assertLessThanOrEqual(8, $queries, 'Notification center must not run per-row queries.');
    }
}
