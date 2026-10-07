<?php

namespace Tests\Feature;

use App\Models\ServiceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Vaihda ilmansuodatin',
            'description' => 'Vaihda varaston ilmanvaihtokoneen ilmansuodatin.',
            'priority' => 'high',
            'status' => 'open',
            'due_date' => '2026-10-15',
        ], $overrides);
    }

    public function test_lists_requests_soonest_due_first(): void
    {
        ServiceRequest::factory()->create(['title' => 'Ei määräpäivää', 'due_date' => null]);
        ServiceRequest::factory()->create(['title' => 'Myöhemmin', 'due_date' => '2026-11-01']);
        ServiceRequest::factory()->create(['title' => 'Pian', 'due_date' => '2026-10-10']);

        $this->getJson('/api/requests')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'Pian')
            ->assertJsonPath('data.1.title', 'Myöhemmin')
            ->assertJsonPath('data.2.title', 'Ei määräpäivää');
    }

    public function test_shows_a_request(): void
    {
        $request = ServiceRequest::factory()->create($this->validPayload());

        $this->getJson("/api/requests/{$request->id}")
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $request->id,
                'title' => 'Vaihda ilmansuodatin',
                'description' => 'Vaihda varaston ilmanvaihtokoneen ilmansuodatin.',
                'priority' => 'high',
                'status' => 'open',
                'due_date' => '2026-10-15',
                'created_at' => $request->created_at->toJSON(),
                'updated_at' => $request->updated_at->toJSON(),
            ]]);
    }

    public function test_showing_a_missing_request_returns_404(): void
    {
        $this->getJson('/api/requests/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Pyyntöä ei löytynyt.']);
    }

    public function test_creates_a_request(): void
    {
        $this->postJson('/api/requests', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Vaihda ilmansuodatin')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.due_date', '2026-10-15');

        $this->assertDatabaseHas('service_requests', [
            'title' => 'Vaihda ilmansuodatin',
            'priority' => 'high',
            'status' => 'open',
        ]);
    }

    public function test_description_and_due_date_are_optional(): void
    {
        $this->postJson('/api/requests', $this->validPayload(['description' => null, 'due_date' => null]))
            ->assertCreated()
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.due_date', null);
    }

    public function test_creating_without_required_fields_returns_finnish_errors(): void
    {
        $this->postJson('/api/requests', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'priority', 'status'])
            ->assertJsonPath('errors.title.0', 'Kenttä otsikko on pakollinen.')
            ->assertJsonPath('message', 'Kenttä otsikko on pakollinen. (ja 2 muuta virhettä)');

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_rejects_invalid_values(): void
    {
        $this->postJson('/api/requests', $this->validPayload([
            'title' => str_repeat('a', 256),
            'priority' => 'urgent',
            'status' => 'done',
            'due_date' => '15.10.2026',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'priority', 'status', 'due_date'])
            ->assertJsonPath('errors.priority.0', 'Kentän prioriteetti arvo on virheellinen.')
            ->assertJsonPath('errors.due_date.0', 'Kentän määräpäivä on oltava muodossa Y-m-d.');
    }

    public function test_updates_a_request(): void
    {
        $request = ServiceRequest::factory()->create($this->validPayload());

        $this->putJson("/api/requests/{$request->id}", $this->validPayload([
            'title' => 'Vaihda ilmansuodattimet',
            'status' => 'in_progress',
        ]))
            ->assertOk()
            ->assertJsonPath('data.title', 'Vaihda ilmansuodattimet')
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('service_requests', [
            'id' => $request->id,
            'title' => 'Vaihda ilmansuodattimet',
            'status' => 'in_progress',
        ]);
    }

    public function test_updating_with_invalid_data_returns_errors(): void
    {
        $request = ServiceRequest::factory()->create($this->validPayload());

        $this->putJson("/api/requests/{$request->id}", $this->validPayload(['title' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);

        $this->assertSame('Vaihda ilmansuodatin', $request->fresh()->title);
    }

    public function test_deletes_a_request(): void
    {
        $request = ServiceRequest::factory()->create();

        $this->deleteJson("/api/requests/{$request->id}")->assertNoContent();

        $this->assertModelMissing($request);
    }

    public function test_deleting_a_missing_request_returns_404(): void
    {
        $this->deleteJson('/api/requests/999')->assertNotFound();
    }

    public function test_unknown_api_route_returns_finnish_404(): void
    {
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Osoitetta ei löytynyt.']);
    }
}
