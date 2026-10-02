<?php

namespace Tests\Feature\Matching;

use App\Models\LawyerVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The matching layer may only ever expose PUBLIC lawyer information --
 * the same fields the public directory already shows -- and must be
 * safe against injection / XSS / probing for private data.
 */
class SecurityTest extends MatchingTestCase
{
    private const FORBIDDEN_KEYS = [
        'user_id', 'email', 'phone', 'address', 'password', 'remember_token', 'license_number',
        'bar_council', 'education', 'cnic', 'cnic_image_path', 'face_image_path', 'face',
        'rejection_reason', 'reviewed_by', 'reviewed_at', 'admin_notes', 'notes', 'status',
        'verification_status', 'is_active', 'is_public', 'verification',
    ];

    /** Approved lawyer that also has a full verification record + private account data. */
    private function lawyerWithPrivateData()
    {
        $user = User::factory()->lawyer()->create([
            'email' => 'private-lawyer@example.test',
            'phone' => '+92 300 5550123',
        ]);

        $lawyer = $this->lawyer([
            'user_id' => $user->id,
            'display_name' => 'Adv. Public Name',
            'phone' => '+92 300 9998877',
            'address' => '12 Secret Street, Hidden Colony',
            'license_number' => 'BC-SECRET-777',
            'bar_council' => 'Secret Bar Council',
            'education' => 'Secret University LLB',
        ], ['family-law']);

        LawyerVerification::factory()->approved()->create([
            'user_id' => $user->id,
            'cnic_image_path' => 'lawyers/verification/cnic/SECRET-CNIC-FILE.jpg',
            'face_image_path' => 'lawyers/verification/face/SECRET-FACE-FILE.jpg',
            'rejection_reason' => 'SECRET REJECTION REASON',
        ]);

        return $lawyer;
    }

    private function assertNoKeyRecursively(array $data, array $forbidden): void
    {
        foreach ($data as $key => $value) {
            $this->assertNotContains((string) $key, $forbidden, "Forbidden key leaked: {$key}");
            if (is_array($value)) {
                $this->assertNoKeyRecursively($value, $forbidden);
            }
        }
    }

    private const SECRETS = [
        'private-lawyer@example.test', '+92 300 5550123', '+92 300 9998877', 'Secret Street',
        'BC-SECRET-777', 'Secret Bar Council', 'Secret University', 'SECRET-CNIC-FILE',
        'SECRET-FACE-FILE', 'SECRET REJECTION REASON', 'lawyers/verification',
    ];

    public function test_private_verification_and_account_data_never_appears_in_the_json_response(): void
    {
        $this->lawyerWithPrivateData();

        $response = $this->getJson(route('lawyers.match', ['service' => $this->svc('family-law')->id]))->assertOk();

        $body = $response->getContent();
        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $body, "Leaked: {$secret}");
        }
        $this->assertNoKeyRecursively($response->json(), self::FORBIDDEN_KEYS);
        $this->assertSame('Adv. Public Name', $response->json('data.0.lawyer.name'));
    }

    public function test_private_data_never_appears_in_the_html_page(): void
    {
        $this->lawyerWithPrivateData();

        $body = $this->get(route('lawyers.match', ['service' => $this->svc('family-law')->id]))->assertOk()->getContent();

        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $body, "Leaked: {$secret}");
        }
    }

    public function test_the_service_does_not_even_load_private_columns(): void
    {
        $this->lawyerWithPrivateData();

        $model = $this->first($this->match(['service' => $this->svc('family-law')->id]))->lawyer;

        $allowed = ['id', 'display_name', 'title', 'bio', 'experience_summary', 'city', 'years_of_experience', 'consultation_fee', 'profile_image', 'updated_at'];
        $this->assertEqualsCanonicalizing($allowed, array_keys($model->getAttributes()));
        $this->assertFalse($model->relationLoaded('user'));
        $this->assertFalse($model->relationLoaded('verification'));
    }

    public function test_probing_parameters_cannot_request_private_data(): void
    {
        $lawyer = $this->lawyerWithPrivateData();
        $base = ['service' => $this->svc('family-law')->id];

        $plain = $this->getJson(route('lawyers.match', $base))->json();

        $probes = [
            ['include' => 'verification,user'], ['with' => ['user', 'verification']], ['fields' => 'cnic_image_path,email'],
            ['lawyer_id' => $lawyer->id], ['user_id' => $lawyer->user_id], ['select' => '*'], ['verification' => 1],
            ['sort' => 'id; DROP TABLE users'], ['debug' => 1], ['admin' => 1],
        ];

        foreach ($probes as $probe) {
            $this->assertSame($plain, $this->getJson(route('lawyers.match', $base + $probe))->json(), 'Probe changed output: '.json_encode($probe));
        }
    }

    public function test_the_response_is_identical_for_guests_customers_lawyers_and_admins(): void
    {
        $this->lawyerWithPrivateData();
        $url = route('lawyers.match', ['service' => $this->svc('family-law')->id]);

        $guest = $this->getJson($url)->json();

        foreach ([User::factory()->create(), User::factory()->lawyer()->create(), User::factory()->admin()->create()] as $user) {
            $this->assertSame($guest, $this->actingAs($user)->getJson($url)->json());
        }
    }

    public function test_html_output_is_escaped_against_xss_in_input_and_in_lawyer_data(): void
    {
        $this->lawyer([
            'display_name' => '<script>alert("name")</script>',
            'title' => '<img src=x onerror=alert(1)>',
            'city' => '<b>Evil</b>',
        ], ['family-law']);

        $body = $this->get(route('lawyers.match', [
            'service' => $this->svc('family-law')->id,
            'problem' => '<script>alert("problem")</script>',
            'city' => '"><script>alert(1)</script>',
        ]))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert("name")</script>', $body);
        $this->assertStringNotContainsString('<script>alert("problem")</script>', $body);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $body);
        $this->assertStringNotContainsString('<b>Evil</b>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;name&quot;)&lt;/script&gt;', $body);
    }

    public function test_user_input_is_always_bound_never_interpolated_into_sql(): void
    {
        $this->lawyer([], ['family-law']);
        $payload = "x' OR '1'='1' --";

        DB::enableQueryLog();
        $result = $this->match(['problem' => $payload, 'city' => $payload]);
        $queries = DB::getQueryLog();

        $this->assertCount(0, $result->matches());
        $this->assertNotEmpty($queries);

        $boundPayloads = 0;
        foreach ($queries as $query) {
            // The raw payload must never be part of the SQL text...
            $this->assertStringNotContainsString("'1'='1'", $query['query']);
            $this->assertStringNotContainsString('--', $query['query']);
            // ...it can only travel as a bound parameter.
            $boundPayloads += count(array_filter($query['bindings'], fn ($b) => is_string($b) && str_contains($b, "'1'='1'")));
        }
        $this->assertGreaterThan(0, $boundPayloads, 'Payload should have been sent as a binding');
        $this->assertDatabaseCount('lawyer_profiles', 1);
    }

    public function test_a_matching_request_performs_only_read_queries(): void
    {
        $this->lawyer([], ['family-law']);

        DB::enableQueryLog();
        $this->getJson(route('lawyers.match', ['service' => $this->svc('family-law')->id, 'problem' => 'divorce', 'city' => 'Karachi']))->assertOk();

        foreach (DB::getQueryLog() as $query) {
            $this->assertMatchesRegularExpression('/^\s*select\b/i', $query['query'], 'Non-read query: '.$query['query']);
        }
    }

    public function test_ineligible_lawyers_do_not_inflate_metadata_counters(): void
    {
        $this->lawyer([], ['family-law']);
        foreach (['pending', 'rejected', 'suspended'] as $status) {
            $this->lawyer(['verification_status' => $status], ['family-law']);
        }

        $meta = $this->match(['service' => $this->svc('family-law')->id])->meta();

        $this->assertSame(1, $meta['candidates_considered']);
        $this->assertSame(1, $meta['returned']);
    }

    public function test_the_matching_endpoint_exposes_no_admin_or_write_routes(): void
    {
        $this->get('/lawyers/match/1')->assertNotFound();
        $this->get('/admin/lawyer-matching')->assertNotFound();
    }
}
