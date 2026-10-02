<?php

namespace Tests\Feature\Notifications;

use App\Models\User;

class AccessTest extends NotificationTestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->post(route('notifications.read-all'))->assertRedirect(route('login'));
    }

    public function test_suspended_user_cannot_reach_the_center(): void
    {
        $user = User::factory()->suspended()->create();

        $this->actingAs($user)->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_every_role_can_open_its_own_center(): void
    {
        foreach ([User::factory()->create(), User::factory()->lawyer()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get(route('notifications.index'))->assertOk();
        }
    }

    public function test_user_sees_only_their_own_notifications(): void
    {
        [$lawyerUser, $profile] = $this->lawyerWithProfile();
        $mine = User::factory()->create();
        $other = User::factory()->create();
        $this->appointmentNotification($mine, $lawyerUser, $profile);
        $this->deliver($other, new \App\Notifications\LawyerApplicationApproved());

        $response = $this->actingAs($mine)->get(route('notifications.index'))->assertOk();

        $response->assertSee('Your appointment request was confirmed.');
        $response->assertDontSee('verification application has been approved');
    }

    public function test_filters_and_pagination(): void
    {
        $user = User::factory()->lawyer()->create();
        foreach (range(1, 17) as $i) {
            $this->deliver($user, new \App\Notifications\LawyerApplicationApproved());
        }
        $user->notifications()->limit(2)->get()->each->markAsRead();

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertViewHas('notifications', fn ($p) => $p->total() === 17 && $p->count() === 15);
        $this->actingAs($user)->get(route('notifications.index', ['filter' => 'unread']))->assertViewHas('notifications', fn ($p) => $p->total() === 15);
        $this->actingAs($user)->get(route('notifications.index', ['filter' => 'read']))->assertViewHas('notifications', fn ($p) => $p->total() === 2);
        $this->actingAs($user)->get(route('notifications.index', ['filter' => 'bogus']))->assertViewHas('filter', 'all');
    }

    public function test_empty_states(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('notifications.index'))->assertSee('No notifications yet');
        $this->actingAs($user)->get(route('notifications.index', ['filter' => 'unread']))->assertSee("You're all caught up");
        $this->actingAs($user)->get(route('notifications.index', ['filter' => 'read']))->assertSee('No read notifications');
    }
}
