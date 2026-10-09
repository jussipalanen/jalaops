<?php

use App\Http\Controllers\HomeController;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;

// The backend serves the REST API; the Vue frontend is a separate app. The home
// page gives an overview of the API and links to its documentation.
Route::get('/', HomeController::class)->name('home');

// Interactive API documentation generated from the routes, form requests and resources.
Scramble::registerUiRoute('docs')->name('docs');
Scramble::registerJsonSpecificationRoute('docs/api.json')->name('docs.json');
