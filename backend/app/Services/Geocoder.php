<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Turns an address into map coordinates with Nominatim, OpenStreetMap's free
 * geocoder. Its usage policy asks for an identifying User-Agent and at most one
 * request per second, so results are cached and calls are spaced app-wide.
 *
 * Address suggestions while typing come from Photon instead, because
 * Nominatim's policy forbids autocomplete.
 */
class Geocoder
{
    /**
     * @return array{latitude: float, longitude: float, display_name: string}|null null when the address isn't found
     *
     * @throws GeocoderException when Nominatim can't be reached, fails or is busy
     */
    public function geocode(string $query): ?array
    {
        $query = Str::squish($query);
        $key = 'geocode:'.md5(Str::lower($query));

        // "Not found" is cached as false, because the cache can't tell a stored null from a miss.
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached ?: null;
        }

        $result = $this->search($query);

        // Addresses rarely move, so results are kept for a long time.
        Cache::put($key, $result ?? false, now()->addDays(30));

        return $result;
    }

    /**
     * @return array{latitude: float, longitude: float, display_name: string}|null
     */
    private function search(string $query): ?array
    {
        // The lock is never released; it expires after a second, so Nominatim
        // gets at most one call per second from the whole app.
        try {
            Cache::lock('geocode:nominatim', 1)->block(3);
        } catch (LockTimeoutException) {
            throw new GeocoderException('Nominatim is busy.');
        }

        try {
            $response = Http::withUserAgent(config('services.nominatim.user_agent'))
                ->acceptJson()
                ->timeout(config('services.nominatim.timeout'))
                ->get(rtrim(config('services.nominatim.base_url'), '/').'/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'accept-language' => 'fi',
                ]);
        } catch (ConnectionException $exception) {
            throw new GeocoderException('Nominatim could not be reached.', previous: $exception);
        }

        if ($response->failed()) {
            throw new GeocoderException("Nominatim returned status {$response->status()}.");
        }

        $place = $response->json('0');

        if (! is_array($place) || ! isset($place['lat'], $place['lon'])) {
            return null;
        }

        return [
            'latitude' => round((float) $place['lat'], 7),
            'longitude' => round((float) $place['lon'], 7),
            'display_name' => (string) ($place['display_name'] ?? $query),
        ];
    }

    /**
     * Street addresses matching what the user has typed so far, nearest to Finland first.
     *
     * @return list<array{label: string, street_address: string, postal_code: string|null, city: string|null, latitude: float, longitude: float}>
     *
     * @throws GeocoderException when Photon can't be reached or fails
     */
    public function suggest(string $query): array
    {
        $query = Str::squish($query);

        return Cache::remember('geocode:suggest:'.md5(Str::lower($query)), now()->addDay(), function () use ($query) {
            try {
                $response = Http::withUserAgent(config('services.nominatim.user_agent'))
                    ->acceptJson()
                    ->timeout(config('services.photon.timeout'))
                    ->get(rtrim(config('services.photon.base_url'), '/').'/api/?'.http_build_query([
                        'q' => $query,
                        'limit' => 10,
                        // Most requests are in Finland, so places in Finland rank first
                        // (zoom 5 makes the bias country-wide instead of around one point).
                        'lat' => 64.0,
                        'lon' => 26.0,
                        'zoom' => 5,
                    ]).'&layer=house&layer=street');
            } catch (ConnectionException $exception) {
                throw new GeocoderException('Photon could not be reached.', previous: $exception);
            }

            if ($response->failed()) {
                throw new GeocoderException("Photon returned status {$response->status()}.");
            }

            return collect($response->json('features', []))
                ->map(fn (array $feature) => $this->suggestion($feature))
                ->filter()
                // Shops and offices at the same address come back as separate places.
                ->unique('label')
                ->take(5)
                ->values()
                ->all();
        });
    }

    /**
     * @param  array<string, mixed>  $feature  a GeoJSON feature from Photon
     * @return array{label: string, street_address: string, postal_code: string|null, city: string|null, latitude: float, longitude: float}|null
     */
    private function suggestion(array $feature): ?array
    {
        $place = $feature['properties'] ?? [];
        [$longitude, $latitude] = $feature['geometry']['coordinates'] ?? [null, null];

        // A house has its street in "street"; a street result is named after itself.
        $street = ($place['type'] ?? null) === 'street' ? ($place['name'] ?? null) : ($place['street'] ?? null);

        if (! $street || $latitude === null || $longitude === null) {
            return null;
        }

        $streetAddress = trim($street.' '.($place['housenumber'] ?? ''));
        $postalCode = $place['postcode'] ?? null;
        $city = $place['city'] ?? $place['town'] ?? $place['village'] ?? null;

        return [
            'label' => implode(', ', array_filter([$streetAddress, trim($postalCode.' '.$city)])),
            'street_address' => $streetAddress,
            'postal_code' => $postalCode,
            'city' => $city,
            'latitude' => round((float) $latitude, 7),
            'longitude' => round((float) $longitude, 7),
        ];
    }
}
