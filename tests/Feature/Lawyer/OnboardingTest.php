<?php

namespace Tests\Feature\Lawyer;

use App\Enums\VerificationStatus;
use App\Models\LawyerProfile;
use App\Models\LawyerVerification;
use App\Models\Service;
use App\Models\Specialization;
use App\Models\User;
use App\Notifications\LawyerApplicationSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function validPayload(array $overrides = []): array
    {
        $service = Service::factory()->create();

        return array_merge([
            'title' => 'Family & Divorce Lawyer',
            'bio' => 'A short professional bio describing my legal practice.',
            'years_of_experience' => 8,
            'city' => 'Karachi',
            'location' => 'Clifton',
            'consultation_fee' => 3500,
            'phone' => '+92 300 1112233',
            'bar_council' => 'Sindh Bar Council',
            'license_number' => 'BC-1234-XYZ',
            'education' => 'LL.B.',
            'services' => [$service->id],
            'cnic_image' => UploadedFile::fake()->image('cnic.jpg'),
            'face_image' => UploadedFile::fake()->image('face.jpg'),
        ], $overrides);
    }

    protected function lawyer(): User
    {
        return User::factory()->lawyer()->create(['email_verified_at' => now()]);
    }

    public function test_onboarding_form_can_be_rendered_by_a_lawyer_with_no_profile(): void
    {
        $lawyer = $this->lawyer();

        $this->actingAs($lawyer)->get(route('lawyer.onboarding.create'))->assertOk();
    }

    public function test_onboarding_form_redirects_away_once_a_profile_already_exists(): void
    {
        $lawyer = $this->lawyer();
        LawyerProfile::factory()->for($lawyer, 'user')->create();

        $this->actingAs($lawyer)->get(route('lawyer.onboarding.create'))
            ->assertRedirect(route('lawyer.dashboard'));
    }

    public function test_a_customer_cannot_reach_lawyer_onboarding(): void
    {
        $customer = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($customer)->get(route('lawyer.onboarding.create'))->assertForbidden();
    }

    public function test_a_lawyer_can_submit_a_complete_onboarding_application(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Notification::fake();

        $lawyer = $this->lawyer();
        $service = Service::factory()->create();
        $specialization = Specialization::factory()->for($service)->create();

        $response = $this->actingAs($lawyer)->post(route('lawyer.onboarding.store'), $this->validPayload([
            'services' => [$service->id],
            'specializations' => [$specialization->id],
            'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
        ]));

        $response->assertRedirect(route('lawyer.dashboard'));

        $lawyer->refresh();
        $this->assertNotNull($lawyer->lawyerProfile);
        $this->assertNotNull($lawyer->lawyerVerification);

        $this->assertSame(VerificationStatus::PENDING, $lawyer->lawyerProfile->verification_status);
        $this->assertSame(VerificationStatus::PENDING, $lawyer->lawyerVerification->status);
        $this->assertNotNull($lawyer->lawyerVerification->submitted_at);

        $this->assertTrue($lawyer->lawyerProfile->services->contains($service));
        $this->assertTrue($lawyer->lawyerProfile->specializations->contains($specialization));

        Storage::disk('local')->assertExists($lawyer->lawyerVerification->cnic_image_path);
        Storage::disk('local')->assertExists($lawyer->lawyerVerification->face_image_path);
        Storage::disk('public')->assertExists($lawyer->lawyerProfile->profile_image);

        // Original client filenames must never be used as the stored name.
        $this->assertStringNotContainsString('cnic.jpg', $lawyer->lawyerVerification->cnic_image_path);
        $this->assertStringNotContainsString('avatar.jpg', $lawyer->lawyerProfile->profile_image);
    }

    public function test_admins_are_notified_when_an_application_is_submitted(): void
    {
        Storage::fake('local');
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $lawyer = $this->lawyer();

        $this->actingAs($lawyer)->post(route('lawyer.onboarding.store'), $this->validPayload());

        Notification::assertSentTo($admin, LawyerApplicationSubmitted::class);
    }

    public function test_a_second_onboarding_submission_is_rejected_once_a_profile_exists(): void
    {
        Storage::fake('local');

        $lawyer = $this->lawyer();
        LawyerProfile::factory()->for($lawyer, 'user')->create();
        $existingCount = LawyerProfile::query()->count();

        $this->actingAs($lawyer)->post(route('lawyer.onboarding.store'), $this->validPayload())
            ->assertRedirect(route('lawyer.dashboard'));

        $this->assertSame($existingCount, LawyerProfile::query()->count());
    }

    public function test_invalid_image_type_is_rejected(): void
    {
        Storage::fake('local');

        $lawyer = $this->lawyer();

        $response = $this->actingAs($lawyer)->post(route('lawyer.onboarding.store'), $this->validPayload([
            'cnic_image' => UploadedFile::fake()->create('cnic.pdf', 100, 'application/pdf'),
        ]));

        $response->assertSessionHasErrors('cnic_image');
        $this->assertNull($lawyer->fresh()->lawyerProfile);
    }

    public function test_oversized_image_is_rejected(): void
    {
        Storage::fake('local');

        $lawyer = $this->lawyer();

        $response = $this->actingAs($lawyer)->post(route('lawyer.onboarding.store'), $this->validPayload([
            'cnic_image' => UploadedFile::fake()->image('cnic.jpg')->size(5000), // > 4096 KB limit
        ]));

        $response->assertSessionHasErrors('cnic_image');
    }

    public function test_missing_required_fields_are_rejected(): void
    {
        $lawyer = $this->lawyer();

        $response = $this->actingAs($lawyer)->post(route('lawyer.onboarding.store'), []);

        $response->assertSessionHasErrors(['title', 'bio', 'years_of_experience', 'city', 'consultation_fee', 'services', 'cnic_image', 'face_image']);
    }

    public function test_a_public_request_cannot_force_the_verification_status_to_approved(): void
    {
        Storage::fake('local');

        $lawyer = $this->lawyer();

        $this->actingAs($lawyer)->post(route('lawyer.onboarding.store'), $this->validPayload([
            'verification_status' => 'approved',
            'status' => 'approved',
        ]));

        $this->assertSame(VerificationStatus::PENDING, $lawyer->fresh()->lawyerProfile->verification_status);
        $this->assertSame(VerificationStatus::PENDING, $lawyer->fresh()->lawyerVerification->status);
    }

    public function test_registration_rolls_back_when_the_database_transaction_fails(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $lawyer = $this->lawyer();

        // Fail *inside* the real DB::transaction(), after the profile
        // row has been created but before the verification row is, to
        // prove (a) the transaction rolls back the half-written rows
        // and (b) the already-uploaded files are cleaned up.
        LawyerVerification::creating(function () {
            throw new \RuntimeException('simulated failure');
        });

        $response = $this->actingAs($lawyer)->post(route('lawyer.onboarding.store'), $this->validPayload());

        $response->assertStatus(500);
        $this->assertNull(LawyerProfile::query()->where('user_id', $lawyer->id)->first());
        $this->assertNull(LawyerVerification::query()->where('user_id', $lawyer->id)->first());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
