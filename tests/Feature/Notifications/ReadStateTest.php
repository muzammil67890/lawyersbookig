<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\LawyerApplicationApproved;

class ReadStateTest extends NotificationTestCase
{
    public function test_user_can_mark_own_notification_read_and_unread(): void
    {
        $user = User::factory()->lawyer()->create();
        $n = $this->deliver($user, new LawyerApplicationApproved());

        $this->actingAs($user)->post(route('notifications.read', $n->id))->assertRedirect();
        $this->assertNotNull($n->fresh()->read_at);

        $this->actingAs($user)->post(route('notifications.unread', $n->id))->assertRedirect();
        $this->assertNull($n->fresh()->read_at);
    }

    public function test_mark_all_read_only_touches_own_notifications_and_updates_count(): void
    {
        $me = User::factory()->lawyer()->create();
        $other = User::factory()->lawyer()->create();
        foreach (range(1, 3) as $i) {
            $this->deliver($me, new LawyerApplicationApproved());
        }
        $this->deliver($other, new LawyerApplicationApproved());
        $this->assertSame(3, $me->unreadNotifications()->count());

        $this->actingAs($me)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $me->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $other->fresh()->unreadNotifications()->count());
    }

    public function test_unread_count_is_shown_and_changes(): void
    {
        $user = User::factory()->lawyer()->create();
        $a = $this->deliver($user, new LawyerApplicationApproved());
        $this->deliver($user, new LawyerApplicationApproved());

        $this->actingAs($user)->get(route('notifications.index'))->assertViewHas('unreadCount', 2);
        $this->actingAs($user)->post(route('notifications.read', $a->id));
        $this->actingAs($user)->get(route('notifications.index'))->assertViewHas('unreadCount', 1);
    }

    public function test_read_state_actions_reject_non_uuid_ids(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/notifications/1/read')->assertNotFound();
    }
}
