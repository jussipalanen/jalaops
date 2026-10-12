<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodeTest extends TestCase
{
    private const NOMINATIM_URL = 'nominatim.openstreetmap.org/search*';

    public function test_returns_the_coordinates_of_an_address(): void
    {
        Http::fake([self::NOMINATIM_URL => Http::response([[
            'lat' => '61.4983189',
            'lon' => '23.7714834',
            'display_name' => 'Hämeenkatu 1, Tampere, 33100, Suomi',
        ]])]);

        $this->getJson('/api/geocode?q='.urlencode('Hämeenkatu 1, 33100 Tampere'))
            ->assertOk()
            ->assertExactJson([
                'latitude' => 61.4983189,
                'longitude' => 23.7714834,
                'display_name' => 'Hämeenkatu 1, Tampere, 33100, Suomi',
            ]);

        Http::assertSent(fn (Request $request) => $request['q'] === 'Hämeenkatu 1, 33100 Tampere'
            && str_starts_with($request->header('User-Agent')[0], 'JalaOps'));
    }

    public function test_caches_results_so_nominatim_is_asked_only_once(): void
    {
        Http::fake([self::NOMINATIM_URL => Http::response([['lat' => '65.0103', 'lon' => '25.4701']])]);

        $this->getJson('/api/geocode?q=Isokatu+32,+Oulu')->assertOk();
        $this->getJson('/api/geocode?q=isokatu++32,+oulu')->assertOk()->assertJsonPath('latitude', 65.0103);

        Http::assertSentCount(1);
    }

    public function test_unknown_address_returns_404(): void
    {
        Http::fake([self::NOMINATIM_URL => Http::response([])]);

        $this->getJson('/api/geocode?q=Olematon+katu+999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Osoitetta ei löytynyt kartalta.']);
    }

    public function test_nominatim_failure_returns_503(): void
    {
        Http::fake([self::NOMINATIM_URL => Http::response('Too Many Requests', 429)]);

        $this->getJson('/api/geocode?q=Hämeenkatu+1')
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Osoitehaku ei juuri nyt onnistu. Yritä hetken kuluttua uudelleen.');
    }

    public function test_requires_a_query(): void
    {
        Http::fake();

        $this->getJson('/api/geocode')
            ->assertUnprocessable()
            ->assertJsonPath('errors.q.0', 'Kenttä haku on pakollinen.');

        Http::assertNothingSent();
    }

    public function test_suggests_addresses_while_typing(): void
    {
        Http::fake(['photon.komoot.io/api/*' => Http::response(['features' => [
            $this->photonPlace(['type' => 'house', 'street' => 'Hämeenkatu', 'housenumber' => '1', 'postcode' => '33100', 'city' => 'Tampere', 'name' => 'Kahvila'], 23.7716, 61.4982),
            // The same address again, as another shop in the building.
            $this->photonPlace(['type' => 'house', 'street' => 'Hämeenkatu', 'housenumber' => '1', 'postcode' => '33100', 'city' => 'Tampere'], 23.7712, 61.4983),
            $this->photonPlace(['type' => 'street', 'name' => 'Hämeenkatu', 'postcode' => '15110', 'city' => 'Lahti'], 25.6531, 60.9837),
            // Places without a street can't fill in an address.
            $this->photonPlace(['type' => 'house', 'name' => 'Pyynikin näkötorni', 'city' => 'Tampere'], 23.7323, 61.4918),
        ]])]);

        $this->getJson('/api/geocode/suggest?q='.urlencode('Hämeenkatu 1'))
            ->assertOk()
            ->assertExactJson(['data' => [
                [
                    'label' => 'Hämeenkatu 1, 33100 Tampere',
                    'street_address' => 'Hämeenkatu 1',
                    'postal_code' => '33100',
                    'city' => 'Tampere',
                    'latitude' => 61.4982,
                    'longitude' => 23.7716,
                ],
                [
                    'label' => 'Hämeenkatu, 15110 Lahti',
                    'street_address' => 'Hämeenkatu',
                    'postal_code' => '15110',
                    'city' => 'Lahti',
                    'latitude' => 60.9837,
                    'longitude' => 25.6531,
                ],
            ]]);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'layer=house&layer=street')
            && $request['q'] === 'Hämeenkatu 1');
    }

    public function test_suggestions_need_three_characters(): void
    {
        Http::fake();

        $this->getJson('/api/geocode/suggest?q=Hä')
            ->assertUnprocessable()
            ->assertJsonPath('errors.q.0', 'Kentän haku on oltava vähintään 3 merkkiä pitkä.');

        Http::assertNothingSent();
    }

    public function test_suggestion_failure_returns_503(): void
    {
        Http::fake(['photon.komoot.io/api/*' => Http::response('Bad Gateway', 502)]);

        $this->getJson('/api/geocode/suggest?q=Isokatu')
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Osoite-ehdotuksia ei juuri nyt saada. Kirjoita osoite itse.');
    }

    /**
     * @param  array<string, string>  $properties
     * @return array<string, mixed>
     */
    private function photonPlace(array $properties, float $longitude, float $latitude): array
    {
        return [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [$longitude, $latitude]],
            'properties' => ['countrycode' => 'FI', ...$properties],
        ];
    }
}
