<?php

use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;

// The backend only serves the REST API; the Vue frontend is a separate app.
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api'),
    'docs' => url('/docs'),
]));

// Interactive API documentation generated from the routes, form requests and resources.
Scramble::registerUiRoute('docs')->name('docs');
Scramble::registerJsonSpecificationRoute('docs/api.json')->name('docs.json');
