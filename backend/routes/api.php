<?php

use App\Http\Controllers\Api\ServiceRequestController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Health check.
 *
 * Reports whether the API and its database connection work.
 */
Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
    } catch (Throwable) {
        return response()->json(['status' => 'error', 'database' => 'error'], 503);
    }

    return response()->json(['status' => 'ok', 'database' => 'ok']);
});

// The model is ServiceRequest, but the public API uses /api/requests.
Route::apiResource('requests', ServiceRequestController::class)
    ->parameters(['requests' => 'serviceRequest']);
