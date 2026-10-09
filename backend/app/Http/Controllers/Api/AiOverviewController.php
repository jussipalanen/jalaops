<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiException;
use App\Services\AiOverviewLimitException;
use App\Services\AiStatusOverview;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @tags Dashboard
 */
class AiOverviewController extends Controller
{
    /**
     * Get the AI status overview.
     *
     * A short Finnish summary of the requests with suggested actions and risks
     * (AI-tilannekatsaus), written by Google Gemini or Puter AI depending on
     * `AI_INSIGHTS_PROVIDER`. Returns `{"enabled": false}` unless the feature is
     * turned on with `AI_INSIGHTS_ENABLED` and the provider's key.
     * The overview is cached until the requests change; `stale` is true when an
     * earlier overview is shown because the hourly generation limit is used up.
     *
     * @response array{enabled: bool, summary?: string, actions?: list<string>, risks?: list<string>, generated_at?: string, provider?: string, model?: string, stale?: bool}
     */
    public function show(AiStatusOverview $overview): JsonResponse
    {
        return $this->respond($overview, refresh: false);
    }

    /**
     * Refresh the AI status overview.
     *
     * Generates a new overview even if the cached one is current. Limited to
     * three calls per minute per visitor.
     *
     * @response array{enabled: bool, summary?: string, actions?: list<string>, risks?: list<string>, generated_at?: string, provider?: string, model?: string, stale?: bool}
     */
    public function refresh(AiStatusOverview $overview): JsonResponse
    {
        return $this->respond($overview, refresh: true);
    }

    private function respond(AiStatusOverview $overview, bool $refresh): JsonResponse
    {
        if (! $overview->isEnabled()) {
            return response()->json(['enabled' => false]);
        }

        try {
            return response()->json(['enabled' => true, ...$overview->get($refresh)]);
        } catch (AiOverviewLimitException) {
            return response()->json([
                'message' => 'AI-tilannekatsauksia on luotu tällä tunnilla jo enimmäismäärä. Yritä myöhemmin uudelleen.',
            ], 429);
        } catch (AiException $exception) {
            Log::warning('AI status overview failed: '.$exception->getMessage());

            return response()->json([
                'message' => 'AI-tilannekatsauksen luominen epäonnistui. Yritä hetken kuluttua uudelleen.',
            ], 503);
        }
    }
}
