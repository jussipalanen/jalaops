<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Geocoder;
use App\Services\GeocoderException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @tags Locations
 */
class GeocodeController extends Controller
{
    /**
     * Find an address's coordinates.
     *
     * Looks up the address with Nominatim (OpenStreetMap) and returns its
     * coordinates, for example `?q=Hämeenkatu 1, 33100 Tampere`. Results are
     * cached. Limited to ten calls per minute per visitor.
     *
     * @response array{latitude: float, longitude: float, display_name: string}
     */
    public function show(Request $request, Geocoder $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:255'],
        ]);

        try {
            $place = $geocoder->geocode($validated['q']);
        } catch (GeocoderException $exception) {
            Log::warning('Geocoding failed: '.$exception->getMessage());

            return response()->json([
                'message' => 'Osoitehaku ei juuri nyt onnistu. Yritä hetken kuluttua uudelleen.',
            ], 503);
        }

        if ($place === null) {
            return response()->json(['message' => 'Osoitetta ei löytynyt kartalta.'], 404);
        }

        return response()->json($place);
    }

    /**
     * Suggest addresses while typing.
     *
     * Up to five street addresses that match the text typed so far, with their
     * postal code, city and coordinates, nearest to Finland first. Uses Photon
     * (OpenStreetMap). Results are cached. Limited to 60 calls per minute per visitor.
     *
     * @response array{data: list<array{label: string, street_address: string, postal_code: string|null, city: string|null, latitude: float, longitude: float}>}
     */
    public function suggest(Request $request, Geocoder $geocoder): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        try {
            return response()->json(['data' => $geocoder->suggest($validated['q'])]);
        } catch (GeocoderException $exception) {
            Log::warning('Address suggestions failed: '.$exception->getMessage());

            return response()->json([
                'message' => 'Osoite-ehdotuksia ei juuri nyt saada. Kirjoita osoite itse.',
            ], 503);
        }
    }
}
