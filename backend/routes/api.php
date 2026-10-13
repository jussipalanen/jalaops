<?php

use App\Http\Controllers\Api\AiOverviewController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\GeocodeController;
use App\Http\Controllers\Api\RequestItemController;
use App\Http\Controllers\Api\RequestTransferController;
use App\Http\Controllers\Api\ServiceRequestController;
use App\Http\Controllers\Api\SystemController;
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

// Application details and health for the "Tietoja" page.
Route::get('/system', [SystemController::class, 'show']);

// Customers whose maintenance work is billed.
Route::apiResource('customers', CustomerController::class);

// Bulk export and import. Registered before the resource routes, so "export"
// and "import" aren't read as request ids.
Route::get('/requests/export', [RequestTransferController::class, 'export']);
Route::post('/requests/import', [RequestTransferController::class, 'import'])->middleware('throttle:imports');

// Work and materials on a request; a row is only found under its own request.
Route::apiResource('requests.items', RequestItemController::class)
    ->parameters(['requests' => 'serviceRequest', 'items' => 'item'])
    ->except(['show'])
    ->scoped();

// The model is ServiceRequest, but the public API uses /api/requests.
Route::get('/requests/{serviceRequest}/signature', [ServiceRequestController::class, 'signature']);
Route::get('/requests/{serviceRequest}/export/{format}', [ServiceRequestController::class, 'export'])
    ->whereIn('format', ['pdf', 'html']);
Route::apiResource('requests', ServiceRequestController::class)
    ->parameters(['requests' => 'serviceRequest']);

// Address to coordinates for request locations (Nominatim), and address suggestions while typing (Photon).
Route::get('/geocode', [GeocodeController::class, 'show'])->middleware('throttle:geocode');
Route::get('/geocode/suggest', [GeocodeController::class, 'suggest'])->middleware('throttle:geocode-suggest');

// Request counts for the dashboard on the home page.
Route::get('/dashboard', [DashboardController::class, 'show']);

// AI-tilannekatsaus for the dashboard (Gemini or Puter). Off unless AI_INSIGHTS_ENABLED and the provider's key are set.
Route::get('/dashboard/ai-overview', [AiOverviewController::class, 'show']);
Route::post('/dashboard/ai-overview/refresh', [AiOverviewController::class, 'refresh'])
    ->middleware('throttle:ai-overview-refresh');
