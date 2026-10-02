<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get(route('register'))->assertOk();
    }

    public function test_a_customer_can_register(): void
    {
        Notification::fake();

        $response = $this->post(route('register'), [
            'name' => 'Samira Ahmed',
            'email' => 'new-customer@example.com',
            'phone' => '+92 300 1112233',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'role' => 'customer',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard.redirect'));

        $user = User::query()->where('email', 'new-customer@example.com')->firstOrFail();

        $this->assertSame(UserRole::CUSTOMER, $user->role);
        $this->assertSame(UserStatus::ACTIVE, $user->status);
        $this->assertTrue(Hash::check('a-strong-password', $user->password));
        $this->assertNotSame('a-strong-password', $user->password);
        $this->assertNull($user->lawyerProfile);
        $this->assertNull($user->lawyerVerification);

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_a_lawyer_can_register_with_no_profile_or_verification_record_created(): void
    {
        $this->post(route('register'), [
            'name' => 'Adv. New Lawyer',
            'email' => 'new-lawyer@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'role' => 'lawyer',
        ]);

        $user = User::query()->where('email', 'new-lawyer@example.com')->firstOrFail();

        $this->assertSame(UserRole::LAWYER, $user->role);
        $this->assertNull($user->lawyerProfile, 'Lawyer onboarding (profile + verification) is Phase 4, not Phase 3.');
        $this->assertNull($user->lawyerVerification);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Someone Else',
            'email' => 'taken@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'role' => 'customer',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame(1, User::query()->where('email', 'taken@example.com')->count());
    }

    public function test_invalid_data_is_rejected(): void
    {
        $response = $this->from(route('register'))->post(route('register'), [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
            'role' => 'customer',
        ]);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
        $this->assertGuest();
    }

    /**
     * The public registration form must never be able to create an
     * admin account, no matter what value is submitted for `role`.
     */
    public function test_role_cannot_be_escalated_to_admin_via_registration(): void
    {
        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Wannabe Admin',
            'email' => 'wannabe-admin@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertGuest();
        $this->assertSame(0, User::query()->where('email', 'wannabe-admin@example.com')->count());
    }

    public function test_role_cannot_be_an_arbitrary_unknown_value(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Someone',
            'email' => 'someone@example.com',
            'password' => 'a-strong-password',
            'password_confirmation' => 'a-strong-password',
            'role' => 'super-user',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertSame(0, User::query()->where('email', 'someone@example.com')->count());
    }
}
