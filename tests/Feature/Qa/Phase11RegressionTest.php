<?php

namespace Tests\Feature\Qa;

use App\Enums\VerificationStatus;
use App\Models\LawyerProfile;
use App\Models\LawyerVerification;
use App\Models\User;
use App\Notifications\LawyerApplicationApproved;
use App\Notifications\LawyerApplicationRejected;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Phase 11 QA regression tests, one per defect fixed in this phase.
 * Written without a PHP runtime available, so they have NOT been executed
 * yet -- run `php artisan test --filter=Phase11RegressionTest` locally.
 */
class Phase11RegressionTest extends TestCase
{
    use RefreshDatabase;

    private function pendingVerification(): LawyerVerification
    {
        $lawyer = User::factory()->lawyer()->create(['email_verified_at' => now()]);
        LawyerProfile::factory()->for($lawyer, 'user')->pending()->create();

        return LawyerVerification::factory()->for($lawyer, 'user')->create(['status' => 'pending']);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create(['email_verified_at' => now()]);
    }

    public function test_directory_keyword_percent_is_matched_literally_not_as_a_wildcard(): void
    {
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Plain Name', 'bio' => 'Family law practice.']);
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Percent Name', 'bio' => 'Offers a 100% fixed fee.']);

        $response = $this->get(route('lawyers.index', ['q' => '%']));

        $response->assertOk();
        $response->assertSee('Adv. Percent Name');
        $response->assertDontSee('Adv. Plain Name');
    }

    public function test_directory_pages_do_not_repeat_or_drop_lawyers_when_sort_values_tie(): void
    {
        LawyerProfile::factory()->approved()->count(15)->create(['consultation_fee' => 5000]);

        $first = $this->get(route('lawyers.index', ['sort' => 'fee']))->assertOk();
        $second = $this->get(route('lawyers.index', ['sort' => 'fee', 'page' => 2]))->assertOk();

        $onFirst = $first->viewData('lawyers')->pluck('id')->all();
        $onSecond = $second->viewData('lawyers')->pluck('id')->all();

        $this->assertCount(12, $onFirst);
        $this->assertCount(3, $onSecond);
        $this->assertSame([], array_intersect($onFirst, $onSecond));
    }

    public function test_rejecting_an_already_rejected_application_does_not_renotify_or_overwrite_the_reason(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $verification = $this->pendingVerification();
        $url = route('admin.lawyer-verifications.reject', $verification);

        $this->actingAs($admin)->post($url, ['rejection_reason' => 'The CNIC image is blurry and unreadable.']);
        $this->actingAs($admin)->post($url, ['rejection_reason' => 'A completely different second reason.']);

        $verification->refresh();

        $this->assertSame(VerificationStatus::REJECTED, $verification->status);
        $this->assertSame('The CNIC image is blurry and unreadable.', $verification->rejection_reason);
        Notification::assertSentToTimes($verification->user, LawyerApplicationRejected::class, 1);
    }

    public function test_approving_twice_notifies_the_lawyer_only_once(): void
    {
        Notification::fake();

        $admin = $this->admin();
        $verification = $this->pendingVerification();
        $url = route('admin.lawyer-verifications.approve', $verification);

        $this->actingAs($admin)->post($url);
        $this->actingAs($admin)->post($url);

        $this->assertSame(VerificationStatus::APPROVED, $verification->fresh()->status);
        Notification::assertSentToTimes($verification->user, LawyerApplicationApproved::class, 1);
    }

    public function test_seeding_in_production_skips_the_known_password_demo_accounts(): void
    {
        $this->app['env'] = 'production';

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseMissing('users', ['email' => 'admin@legalconnect.test']);
        $this->assertDatabaseMissing('users', ['email' => 'customer@legalconnect.test']);
    }
}
