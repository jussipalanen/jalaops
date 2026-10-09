<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_links_to_the_docs_and_the_frontend(): void
    {
        config(['app.frontend_url' => 'https://jalaops.example']);

        $this->get('/')
            ->assertOk()
            ->assertSee('JalaOps-rajapinta')
            ->assertSee(url('/docs'))
            ->assertSee('https://jalaops.example')
            ->assertSee('Powered by');
    }

    public function test_home_page_shows_request_counts_per_status(): void
    {
        ServiceRequest::factory()->count(2)->create(['status' => RequestStatus::Open]);
        ServiceRequest::factory()->create(['status' => RequestStatus::Completed]);

        $this->get('/')
            ->assertOk()
            ->assertViewHas('statusCounts', ['open' => 2, 'in_progress' => 0, 'completed' => 1])
            ->assertSee('Pyyntöjä yhteensä');
    }

    public function test_home_page_hides_the_frontend_link_when_it_is_not_set(): void
    {
        config(['app.frontend_url' => null]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Siirry sovellukseen');
    }

    public function test_api_clients_get_the_links_as_json(): void
    {
        $this->getJson('/')
            ->assertOk()
            ->assertJson([
                'name' => config('app.name'),
                'api' => url('/api'),
                'docs' => url('/docs'),
            ]);
    }
}
