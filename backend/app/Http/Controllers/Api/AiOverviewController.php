<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiException;
use App\Services\AiOverviewLimitException;
use App\Services\AiStatusOverview;
use Closure;
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
     *
     * A cached overview is returned right away. `outdated` is true when the
     * requests have changed since it was written (or it has expired); the
     * frontend then calls the refresh endpoint in the background. Only when no
     * overview exists yet does this call wait for the AI.
     *
     * @response array{enabled: bool, summary?: string, actions?: list<string>, risks?: list<string>, generated_at?: string, provider?: string, model?: string, stale?: bool, outdated?: bool}
     */
    public function show(AiStatusOverview $overview): JsonResponse
    {
        return $this->respond(fn () => $overview->get(), $overview);
    }

    /**
     * Refresh the AI status overview.
     *
     * Generates a new overview. An overview generated for the same requests in
     * the last minute is returned instead, so simultaneous refreshes cause only
     * one AI call. `stale` is true when the hourly AI call limit is used up and
     * the previous overview is returned. Limited to three calls per minute per visitor.
     *
     * @response array{enabled: bool, summary?: string, actions?: list<string>, risks?: list<string>, generated_at?: string, provider?: string, model?: string, stale?: bool, outdated?: bool}
     */
    public function refresh(AiStatusOverview $overview): JsonResponse
    {
        return $this->respond(fn () => $overview->refresh(), $overview);
    }

    /**
     * @param  Closure(): array<string, mixed>  $load
     */
    private function respond(Closure $load, AiStatusOverview $overview): JsonResponse
    {
        if (! $overview->isEnabled()) {
            return response()->json(['enabled' => false]);
        }

        try {
            return response()->json(['enabled' => true, ...$load()]);
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
