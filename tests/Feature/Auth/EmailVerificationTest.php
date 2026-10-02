<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function signedVerificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );
    }

    public function test_unverified_user_is_redirected_to_the_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('dashboard.redirect'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_verification_notice_is_shown_to_an_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    public function test_an_already_verified_user_visiting_the_notice_is_sent_onward(): void
    {
        $user = User::factory()->create(); // email_verified_at set by the default factory state

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertRedirect(route('dashboard.redirect'));
    }

    public function test_a_valid_signed_link_verifies_the_email(): void
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get($this->signedVerificationUrl($user));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect();
        Event::assertDispatched(Verified::class);
    }

    public function test_an_invalid_hash_does_not_verify_the_email(): void
    {
        $user = User::factory()->unverified()->create();

        $badUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('not-the-right-email')]
        );

        $this->actingAs($user)->get($badUrl)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_an_expired_signed_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $expiredUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinutes(5),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($expiredUrl)->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_already_verified_user_hitting_the_verify_link_again_is_handled_gracefully(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get($this->signedVerificationUrl($user));

        $response->assertRedirect(route('dashboard.redirect', ['verified' => 1]));
    }

    public function test_resend_verification_is_rate_limited(): void
    {
        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->post(route('verification.send'));
        }

        // The 7th resend within the same minute is throttled.
        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertStatus(429);
    }
}
