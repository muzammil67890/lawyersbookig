<?php

namespace Tests\Feature\Lawyers;

use App\Models\LawyerProfile;
use App\Models\Service;
use App\Models\Specialization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_directory_page_loads_successfully(): void
    {
        $this->get(route('lawyers.index'))->assertOk();
    }

    public function test_an_approved_active_lawyer_appears_in_the_directory(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Findable Lawyer']);

        $this->get(route('lawyers.index'))->assertSee('Adv. Findable Lawyer');
    }

    public function test_a_pending_lawyer_does_not_appear_in_the_directory(): void
    {
        LawyerProfile::factory()->pending()->create(['display_name' => 'Adv. Pending Lawyer']);

        $this->get(route('lawyers.index'))->assertDontSee('Adv. Pending Lawyer');
    }

    public function test_a_rejected_lawyer_does_not_appear_in_the_directory(): void
    {
        LawyerProfile::factory()->rejected()->create(['display_name' => 'Adv. Rejected Lawyer']);

        $this->get(route('lawyers.index'))->assertDontSee('Adv. Rejected Lawyer');
    }

    public function test_a_suspended_inactive_lawyer_does_not_appear_in_the_directory(): void
    {
        LawyerProfile::factory()->suspended()->create(['display_name' => 'Adv. Suspended Lawyer']);

        $this->get(route('lawyers.index'))->assertDontSee('Adv. Suspended Lawyer');
    }

    public function test_an_approved_but_not_public_lawyer_does_not_appear(): void
    {
        LawyerProfile::factory()->approved()->create([
            'display_name' => 'Adv. Opted Out Lawyer',
            'is_public' => false,
        ]);

        $this->get(route('lawyers.index'))->assertDontSee('Adv. Opted Out Lawyer');
    }

    public function test_an_approved_but_inactive_lawyer_does_not_appear(): void
    {
        LawyerProfile::factory()->approved()->create([
            'display_name' => 'Adv. Deactivated Lawyer',
            'is_active' => false,
        ]);

        $this->get(route('lawyers.index'))->assertDontSee('Adv. Deactivated Lawyer');
    }

    // --- Search -----------------------------------------------------

    public function test_search_by_lawyer_name(): void
    {
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Zubair Chaudhry']);
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Someone Else']);

        $response = $this->get(route('lawyers.index', ['q' => 'Zubair']));

        $response->assertSee('Adv. Zubair Chaudhry');
        $response->assertDontSee('Adv. Someone Else');
    }

    public function test_search_by_professional_title(): void
    {
        LawyerProfile::factory()->approved()->create([
            'display_name' => 'Adv. Title Match',
            'title' => 'Maritime Law Specialist',
        ]);
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. No Match']);

        $response = $this->get(route('lawyers.index', ['q' => 'Maritime']));

        $response->assertSee('Adv. Title Match');
        $response->assertDontSee('Adv. No Match');
    }

    public function test_search_by_city(): void
    {
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. City Match', 'city' => 'Multan']);
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. City No Match', 'city' => 'Quetta']);

        $response = $this->get(route('lawyers.index', ['q' => 'Multan']));

        $response->assertSee('Adv. City Match');
        $response->assertDontSee('Adv. City No Match');
    }

    public function test_search_by_service_name(): void
    {
        $service = Service::factory()->create(['name' => 'Very Unique Legal Service']);
        $lawyer = LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Service Match']);
        $lawyer->services()->attach($service);

        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Service No Match']);

        $response = $this->get(route('lawyers.index', ['q' => 'Very Unique Legal Service']));

        $response->assertSee('Adv. Service Match');
        $response->assertDontSee('Adv. Service No Match');
    }

    public function test_search_by_specialization_name(): void
    {
        $service = Service::factory()->create();
        $specialization = Specialization::factory()->for($service)->create(['name' => 'Very Unique Specialization']);
        $lawyer = LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Spec Match']);
        $lawyer->specializations()->attach($specialization);

        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Spec No Match']);

        $response = $this->get(route('lawyers.index', ['q' => 'Very Unique Specialization']));

        $response->assertSee('Adv. Spec Match');
        $response->assertDontSee('Adv. Spec No Match');
    }

    public function test_combined_search_and_filter(): void
    {
        $lawyer = LawyerProfile::factory()->approved()->create([
            'display_name' => 'Adv. Combo Match',
            'city' => 'Sialkot',
            'consultation_fee' => 3000,
        ]);
        LawyerProfile::factory()->approved()->create([
            'display_name' => 'Adv. Combo Wrong City',
            'city' => 'Sukkur',
            'consultation_fee' => 3000,
        ]);

        $response = $this->get(route('lawyers.index', ['q' => 'Combo', 'city' => 'Sialkot']));

        $response->assertSee('Adv. Combo Match');
        $response->assertDontSee('Adv. Combo Wrong City');
    }

    // --- Filters ------------------------------------------------------

    public function test_filter_by_service_slug(): void
    {
        $service = Service::factory()->create(['slug' => 'unique-filter-service']);
        $lawyer = LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Filter Service Match']);
        $lawyer->services()->attach($service);

        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Filter Service No Match']);

        $response = $this->get(route('lawyers.index', ['service' => 'unique-filter-service']));

        $response->assertSee('Adv. Filter Service Match');
        $response->assertDontSee('Adv. Filter Service No Match');
    }

    public function test_filter_by_specialization_slug(): void
    {
        $service = Service::factory()->create();
        $specialization = Specialization::factory()->for($service)->create(['slug' => 'unique-filter-spec']);
        $lawyer = LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Filter Spec Match']);
        $lawyer->specializations()->attach($specialization);

        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Filter Spec No Match']);

        $response = $this->get(route('lawyers.index', ['specialization' => 'unique-filter-spec']));

        $response->assertSee('Adv. Filter Spec Match');
        $response->assertDontSee('Adv. Filter Spec No Match');
    }

    public function test_filter_by_city(): void
    {
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. City Filter Match', 'city' => 'Gujranwala']);
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. City Filter No Match', 'city' => 'Hyderabad']);

        $response = $this->get(route('lawyers.index', ['city' => 'Gujranwala']));

        $response->assertSee('Adv. City Filter Match');
        $response->assertDontSee('Adv. City Filter No Match');
    }

    public function test_filter_by_minimum_fee(): void
    {
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Expensive', 'consultation_fee' => 9000]);
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Cheap', 'consultation_fee' => 1000]);

        $response = $this->get(route('lawyers.index', ['min_fee' => 5000]));

        $response->assertSee('Adv. Expensive');
        $response->assertDontSee('Adv. Cheap');
    }

    public function test_filter_by_maximum_fee(): void
    {
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Within Budget', 'consultation_fee' => 2000]);
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Over Budget', 'consultation_fee' => 9000]);

        $response = $this->get(route('lawyers.index', ['max_fee' => 3000]));

        $response->assertSee('Adv. Within Budget');
        $response->assertDontSee('Adv. Over Budget');
    }

    public function test_filter_by_minimum_experience(): void
    {
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Veteran', 'years_of_experience' => 20]);
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Junior', 'years_of_experience' => 1]);

        $response = $this->get(route('lawyers.index', ['min_experience' => 10]));

        $response->assertSee('Adv. Veteran');
        $response->assertDontSee('Adv. Junior');
    }

    // --- Sorting --------------------------------------------------------

    public function test_sorting_by_fee_orders_cheapest_first(): void
    {
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Fee High', 'consultation_fee' => 9000]);
        LawyerProfile::factory()->approved()->create(['display_name' => 'Adv. Fee Low', 'consultation_fee' => 1000]);

        $response = $this->get(route('lawyers.index', ['sort' => 'fee']));
        $response->assertOk();

        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'Adv. Fee High'),
            strpos($content, 'Adv. Fee Low')
        );
    }

    public function test_an_invalid_sort_value_is_rejected_by_validation_not_used_as_a_column(): void
    {
        $response = $this->get(route('lawyers.index', ['sort' => 'id; DROP TABLE users; --']));

        $response->assertSessionHasErrors('sort');
    }

    public function test_omitting_sort_defaults_safely(): void
    {
        $this->get(route('lawyers.index'))->assertOk();
    }

    // --- Pagination -------------------------------------------------

    public function test_pagination_splits_results_across_pages(): void
    {
        LawyerProfile::factory()->approved()->count(15)->create();

        $page1 = $this->get(route('lawyers.index'));
        $page1->assertOk();
        $page1->assertViewHas('lawyers', fn ($paginator) => $paginator->count() === 12 && $paginator->total() === 15);

        $page2 = $this->get(route('lawyers.index', ['page' => 2]));
        $page2->assertOk();
        $page2->assertViewHas('lawyers', fn ($paginator) => $paginator->count() === 3);
    }

    public function test_filters_persist_across_pagination_links(): void
    {
        LawyerProfile::factory()->approved()->count(15)->create(['city' => 'Karachi']);

        $response = $this->get(route('lawyers.index', ['city' => 'Karachi']));

        $response->assertOk();
        $response->assertSee('city=Karachi', false);
    }
}
