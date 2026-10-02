<?php

namespace Tests\Feature\Matching;

use App\Models\Service;
use App\Services\Matching\ProblemKeywordResolver;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Free-text "legal problem" handling: conservative, deterministic
 * word/phrase matching against REAL services/specializations. Not AI.
 */
class ProblemMatchingTest extends MatchingTestCase
{
    public function test_divorce_matches_divorce_and_family_lawyers_with_the_exact_service_ranked_first(): void
    {
        $divorce = $this->lawyer(['display_name' => 'Adv. Divorce'], ['divorce']);
        $family = $this->lawyer(['display_name' => 'Adv. Family'], ['family-law']);
        $this->lawyer(['display_name' => 'Adv. Criminal'], ['criminal-law']);

        $result = $this->match(['problem' => 'I need help with a divorce']);

        $this->assertSame([$divorce->id, $family->id], $this->ids($result));
        $this->assertSame(90, $result->matches()[0]->score);  // service_direct
        $this->assertSame(65, $result->matches()[1]->score);  // service_related
        $this->assertTrue($result->matches()[0]->matches['problem']);
        $this->assertSame('Offers Divorce, which matches your legal problem ("Divorce")', $result->matches()[0]->reasons[0]);
        $this->assertStringContainsString('relates to your legal problem ("divorce")', $result->matches()[1]->reasons[0]);
    }

    public function test_property_dispute_matches_property_law_lawyers_only(): void
    {
        $property = $this->lawyer([], ['property-law']);
        $civil = $this->lawyer([], ['civil-law']);
        $this->lawyer([], ['criminal-law']);
        $this->lawyer([], ['tax-law']);

        $result = $this->match(['problem' => 'property dispute']);

        $this->assertSame([$property->id, $civil->id], $this->ids($result));
    }

    public function test_criminal_case_matches_criminal_law(): void
    {
        $criminal = $this->lawyer([], ['criminal-law']);
        $this->lawyer([], ['family-law']);

        $this->assertSame([$criminal->id], $this->ids($this->match(['problem' => 'criminal case'])));
    }

    #[DataProvider('supportedProblems')]
    public function test_supported_legal_problem_keywords_resolve_to_the_expected_service(string $problem, string $serviceSlug): void
    {
        $expected = $this->lawyer([], [$serviceSlug]);
        // A lawyer in a totally different area of law must never be returned.
        $other = $serviceSlug === 'criminal-law' ? 'immigration' : 'criminal-law';
        $this->lawyer([], [$other]);

        $this->assertSame([$expected->id], $this->ids($this->match(['problem' => $problem])));
    }

    public static function supportedProblems(): array
    {
        return [
            'family matter' => ['family matter', 'family-law'],
            'corporate issue' => ['corporate issue', 'corporate-law'],
            'inheritance' => ['inheritance', 'family-law'],
            'tax' => ['tax', 'tax-law'],
            'employment dispute' => ['employment dispute', 'employment-law'],
            'unfair termination' => ['unfair termination of my job', 'employment-law'],
            'visa' => ['visa refused', 'immigration'],
            'bail' => ['I need bail after an arrest', 'criminal-law'],
            'affidavit' => ['affidavit', 'affidavit'],
            'plural forms' => ['taxes', 'tax-law'],
            'mixed case and punctuation' => ['  CRIMINAL, Case!!  ', 'criminal-law'],
        ];
    }

    public function test_a_specialization_named_in_the_problem_is_a_direct_specialization_hit(): void
    {
        $tenancy = $this->lawyer(['display_name' => 'Adv. Tenancy'], ['property-law'], ['tenancy-disputes']);
        $propertyOnly = $this->lawyer(['display_name' => 'Adv. Property'], ['property-law']);

        $result = $this->match(['problem' => 'tenancy dispute']);

        $this->assertSame([$tenancy->id, $propertyOnly->id], $this->ids($result));
        $this->assertSame(100, $result->matches()[0]->score);
        $this->assertSame('specialization_direct', $result->matches()[0]->breakdown['problem']['tier']);
    }

    public function test_a_generic_word_shared_with_an_unrelated_specialization_does_not_cause_a_false_match(): void
    {
        // "dispute" appears in both "Tenancy Disputes" and "Tax Disputes"; it must not link them.
        $this->lawyer([], ['tax-law'], ['tax-disputes']);

        $this->assertSame([], $this->ids($this->match(['problem' => 'tenancy dispute'])));
    }

    public function test_words_found_only_in_a_lawyers_own_profile_give_a_weak_text_match(): void
    {
        $text = $this->lawyer(['bio' => 'Specialises in arbitration and mediation.'], ['corporate-law']);
        $this->lawyer([], ['corporate-law']);

        $result = $this->match(['problem' => 'arbitration']);

        $this->assertSame([$text->id], $this->ids($result));
        $this->assertSame(35, $result->matches()[0]->score);
        $this->assertSame('text', $result->matches()[0]->breakdown['problem']['tier']);
        $this->assertSame(['Profile mentions "arbitration" from your legal problem'], $result->matches()[0]->reasons);
    }

    public function test_unrecognisable_text_produces_no_fake_results(): void
    {
        $this->lawyer([], ['family-law']);
        $this->lawyer([], ['criminal-law']);

        $result = $this->match(['problem' => 'zzqqxx']);

        $this->assertCount(0, $result->matches());
        $this->assertFalse($result->meta()['problem']['catalogue_matched']);
    }

    public function test_a_problem_made_only_of_filler_words_is_ignored_not_matched_to_everyone(): void
    {
        $this->lawyer([], ['family-law']);

        $result = $this->match(['problem' => 'I need a lawyer to help me']);

        $this->assertCount(0, $result->matches());
        $this->assertFalse($result->meta()['problem']['used']);
        $this->assertArrayNotHasKey('problem', $result->meta()['applied_components']);
    }

    public function test_an_ignored_problem_does_not_stop_other_criteria_from_working(): void
    {
        $lawyer = $this->lawyer(['city' => 'Lahore']);

        $result = $this->match(['problem' => 'I need a lawyer', 'city' => 'Lahore']);

        $this->assertSame([$lawyer->id], $this->ids($result));
        $this->assertSame(100, $result->matches()[0]->score);
    }

    public function test_the_problem_combines_with_an_explicit_service_without_double_counting_weights(): void
    {
        $lawyer = $this->lawyer([], ['family-law']);

        $match = $this->first($this->match(['problem' => 'custody', 'service' => $this->svc('family-law')->id]));

        // service 25 (full) + problem 20 * 0.65 (related) = 38 / 45
        $this->assertSame($lawyer->id, $match->lawyer->id);
        $this->assertSame(84, $match->score);
    }

    public function test_the_same_problem_always_produces_the_same_interpretation(): void
    {
        $resolver = app(ProblemKeywordResolver::class);

        $this->assertEquals($resolver->resolve('divorce and child custody'), $resolver->resolve('divorce and child custody'));
    }

    public function test_synonym_entries_pointing_at_unknown_slugs_are_ignored_safely(): void
    {
        $resolver = new ProblemKeywordResolver([
            'stopwords' => [],
            'keywords' => ['zoning' => ['services' => ['does-not-exist'], 'specializations' => ['nope']]],
        ]);

        $interpretation = $resolver->resolve('zoning');

        $this->assertSame([], $interpretation->services);
        $this->assertSame([], $interpretation->specializations);
    }

    public function test_inactive_services_are_never_resolved_from_a_problem(): void
    {
        Service::query()->where('slug', 'divorce')->update(['is_active' => false]);
        $this->lawyer([], ['divorce']);

        $result = $this->match(['problem' => 'divorce']);

        $this->assertNotContains('Divorce', $result->meta()['problem']['services']);
    }

    public function test_new_catalogue_entries_are_matched_by_name_without_code_changes(): void
    {
        $service = Service::factory()->create(['name' => 'Maritime Law', 'slug' => 'maritime-law']);
        $lawyer = $this->lawyer();
        $lawyer->services()->attach($service->id);

        $result = $this->match(['problem' => 'maritime']);

        $this->assertSame([$lawyer->id], $this->ids($result));
    }

    public function test_sql_wildcards_and_quotes_in_the_problem_are_inert(): void
    {
        $this->lawyer([], ['family-law']);

        foreach (["' OR '1'='1", '%', '_', '"; DROP TABLE lawyer_profiles; --', '\\'] as $payload) {
            $result = $this->match(['problem' => $payload]);
            $this->assertCount(0, $result->matches(), 'Payload matched something: '.$payload);
        }

        $this->assertDatabaseCount('lawyer_profiles', 1);
    }
}
