<?php

use Illuminate\Support\Facades\Route;

// The backend only serves the REST API; the Vue frontend is a separate app.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api'),
]));
