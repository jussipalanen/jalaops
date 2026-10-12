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
                'street_address' => null,
                'postal_code' => null,
                'city' => null,
                'latitude' => null,
                'longitude' => null,
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

    public function test_creates_a_request_with_a_location(): void
    {
        $this->postJson('/api/requests', $this->validPayload([
            'street_address' => 'Hämeenkatu 10',
            'postal_code' => '33100',
            'city' => 'Tampere',
            'latitude' => 61.4980,
            'longitude' => 23.7660,
        ]))
            ->assertCreated()
            ->assertJsonPath('data.street_address', 'Hämeenkatu 10')
            ->assertJsonPath('data.postal_code', '33100')
            ->assertJsonPath('data.city', 'Tampere')
            ->assertJsonPath('data.latitude', 61.498)
            ->assertJsonPath('data.longitude', 23.766);

        $this->assertDatabaseHas('service_requests', ['city' => 'Tampere', 'postal_code' => '33100']);
    }

    public function test_an_address_without_coordinates_is_allowed(): void
    {
        $this->postJson('/api/requests', $this->validPayload(['street_address' => 'Isokatu 32', 'city' => 'Oulu']))
            ->assertCreated()
            ->assertJsonPath('data.city', 'Oulu')
            ->assertJsonPath('data.latitude', null);
    }

    public function test_rejects_invalid_locations(): void
    {
        $this->postJson('/api/requests', $this->validPayload([
            'postal_code' => '<b>331</b>',
            'latitude' => 91,
            'longitude' => 'itä',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.postal_code.0', 'Kentän postinumero muoto on virheellinen.')
            ->assertJsonPath('errors.latitude.0', 'Kentän leveysaste on oltava välillä -90–90.')
            ->assertJsonPath('errors.longitude.0', 'Kentän pituusaste on oltava numero.');
    }

    public function test_coordinates_must_be_given_together(): void
    {
        $this->postJson('/api/requests', $this->validPayload(['latitude' => 61.5]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.longitude.0', 'Kenttä pituusaste on pakollinen, kun leveysaste on annettu.');
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

    private function createFilterFixtures(): void
    {
        ServiceRequest::factory()->create(['title' => 'Avoin kiireellinen', 'status' => 'open', 'priority' => 'high']);
        ServiceRequest::factory()->create(['title' => 'Avoin tavallinen', 'status' => 'open', 'priority' => 'normal']);
        ServiceRequest::factory()->create(['title' => 'Valmis kiireellinen', 'status' => 'completed', 'priority' => 'high']);
        ServiceRequest::factory()->create(['title' => 'Työn alla matala', 'status' => 'in_progress', 'priority' => 'low']);
    }

    /**
     * @return list<string>
     */
    private function listedTitles(string $query): array
    {
        return collect($this->getJson("/api/requests{$query}")->assertOk()->json('data'))
            ->pluck('title')
            ->sort()
            ->values()
            ->all();
    }

    public function test_filters_by_status(): void
    {
        $this->createFilterFixtures();

        $this->assertSame(['Avoin kiireellinen', 'Avoin tavallinen'], $this->listedTitles('?status=open'));
    }

    public function test_filters_by_priority(): void
    {
        $this->createFilterFixtures();

        $this->assertSame(['Avoin kiireellinen', 'Valmis kiireellinen'], $this->listedTitles('?priority=high'));
    }

    public function test_filters_by_status_and_priority(): void
    {
        $this->createFilterFixtures();

        $this->assertSame(['Avoin kiireellinen'], $this->listedTitles('?status=open&priority=high'));
    }

    public function test_empty_filters_list_all_requests(): void
    {
        $this->createFilterFixtures();

        $this->assertCount(4, $this->listedTitles('?status=&priority='));
    }

    public function test_searches_by_title_street_address_postal_code_or_city(): void
    {
        ServiceRequest::factory()->create(['title' => 'Tampere', 'street_address' => 'Hämeenkatu 10', 'postal_code' => '33100', 'city' => 'Tampere']);
        ServiceRequest::factory()->create(['title' => 'Oulu', 'street_address' => 'Isokatu 32', 'postal_code' => '90100', 'city' => 'Oulu']);
        ServiceRequest::factory()->create(['title' => 'Ei osoitetta']);

        $titles = fn (string $search) => collect($this->getJson('/api/requests?search='.urlencode($search))->assertOk()->json('data'))
            ->pluck('title')->sort()->values()->all();

        $this->assertSame(['Tampere'], $titles('tampere'));
        $this->assertSame(['Oulu'], $titles('Isokatu'));
        $this->assertSame(['Oulu'], $titles('901'));
        $this->assertSame(['Oulu', 'Tampere'], $titles('katu'));
        $this->assertSame(['Ei osoitetta'], $titles('osoitetta'));
        $this->assertSame([], $titles('Rovaniemi'));
        $this->assertSame(['Ei osoitetta', 'Oulu', 'Tampere'], $titles('  '));
    }

    public function test_search_treats_wildcards_literally(): void
    {
        ServiceRequest::factory()->create(['city' => 'Tampere']);
        ServiceRequest::factory()->create(['street_address' => 'Varasto_2!']);

        $this->getJson('/api/requests?search=%25')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/requests?search=_')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/requests?search=o_2!')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_search_combines_with_filters(): void
    {
        ServiceRequest::factory()->create(['title' => 'Avoin', 'status' => 'open', 'city' => 'Tampere']);
        ServiceRequest::factory()->create(['title' => 'Valmis', 'status' => 'completed', 'city' => 'Tampere']);

        $this->getJson('/api/requests?search=Tampere&status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Avoin');
    }

    public function test_filters_by_due_date_period(): void
    {
        $this->travelTo('2026-10-12 12:00');

        ServiceRequest::factory()->create(['title' => 'Myöhässä', 'status' => 'open', 'due_date' => '2026-10-05']);
        ServiceRequest::factory()->create(['title' => 'Myöhässä mutta valmis', 'status' => 'completed', 'due_date' => '2026-10-05']);
        ServiceRequest::factory()->create(['title' => 'Tänään', 'status' => 'open', 'due_date' => '2026-10-12']);
        ServiceRequest::factory()->create(['title' => 'Viikon päästä', 'status' => 'open', 'due_date' => '2026-10-19']);
        ServiceRequest::factory()->create(['title' => 'Kuukauden päästä', 'status' => 'in_progress', 'due_date' => '2026-11-11']);
        ServiceRequest::factory()->create(['title' => 'Myöhemmin', 'status' => 'open', 'due_date' => '2026-12-31']);
        ServiceRequest::factory()->create(['title' => 'Ei määräpäivää', 'status' => 'open', 'due_date' => null]);

        $titles = fn (string $query) => collect($this->getJson("/api/requests?{$query}")->assertOk()->json('data'))->pluck('title')->all();

        $this->assertSame(['Myöhässä'], $titles('due=overdue'));
        $this->assertSame(['Tänään'], $titles('due=today'));
        $this->assertSame(['Tänään', 'Viikon päästä'], $titles('due=week'));
        $this->assertSame(['Tänään', 'Viikon päästä', 'Kuukauden päästä'], $titles('due=month'));
        $this->assertSame(['Ei määräpäivää'], $titles('due=none'));
    }

    public function test_filters_by_due_date_range(): void
    {
        ServiceRequest::factory()->create(['title' => 'Syyskuu', 'due_date' => '2026-09-30']);
        ServiceRequest::factory()->create(['title' => 'Lokakuun alku', 'due_date' => '2026-10-01']);
        ServiceRequest::factory()->create(['title' => 'Lokakuun loppu', 'due_date' => '2026-10-31']);
        ServiceRequest::factory()->create(['title' => 'Marraskuu', 'due_date' => '2026-11-01']);

        $titles = fn (string $query) => collect($this->getJson("/api/requests?{$query}")->assertOk()->json('data'))->pluck('title')->all();

        $this->assertSame(['Lokakuun alku', 'Lokakuun loppu'], $titles('due_from=2026-10-01&due_to=2026-10-31'));
        $this->assertSame(['Lokakuun loppu', 'Marraskuu'], $titles('due_from=2026-10-15'));
        $this->assertSame(['Syyskuu'], $titles('due_to=2026-09-30'));
    }

    public function test_rejects_invalid_due_date_filters(): void
    {
        $this->getJson('/api/requests?due=yesterday&due_from=1.10.2026&due_to=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonPath('errors.due.0', 'Kentän määräpäivä arvo on virheellinen.')
            ->assertJsonPath('errors.due_from.0', 'Kentän alkaen on oltava muodossa Y-m-d.');

        $this->getJson('/api/requests?due_from=2026-10-10&due_to=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonPath('errors.due_to.0', 'Kentän asti on oltava sama tai myöhempi päivä kuin alkaen.');
    }

    public function test_rejects_invalid_filter_values(): void
    {
        $this->getJson('/api/requests?status=done&priority=urgent')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'priority'])
            ->assertJsonPath('errors.status.0', 'Kentän tila arvo on virheellinen.');
    }
}
