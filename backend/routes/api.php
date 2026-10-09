<?php

use App\Http\Controllers\Api\AiOverviewController;
use App\Http\Controllers\Api\ServiceRequestController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Health check.
 *
 * Reports whether the API and its database connection work, and whether the
 * app runs in demo mode.
 */
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
    } catch (Throwable) {
        return response()->json([
            'status' => 'error',
            'database' => 'error',
            'demo' => config('app.demo_mode'),
        ], 503);
    }

    return response()->json([
        'status' => 'ok',
        'database' => 'ok',
        'demo' => config('app.demo_mode'),
    ]);
});

// The model is ServiceRequest, but the public API uses /api/requests.
Route::apiResource('requests', ServiceRequestController::class)
    ->parameters(['requests' => 'serviceRequest']);

// AI-tilannekatsaus for the dashboard (Gemini or Puter). Off unless AI_INSIGHTS_ENABLED and the provider's key are set.
Route::get('/dashboard/ai-overview', [AiOverviewController::class, 'show']);
Route::post('/dashboard/ai-overview/refresh', [AiOverviewController::class, 'refresh'])
    ->middleware('throttle:ai-overview-refresh');
