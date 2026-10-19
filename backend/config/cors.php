<?php

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| Which web pages may call the API from a browser. The frontend reaches the
| API through a same-origin proxy (Vite locally, Vercel's rewrite in
| production), so only the app's own addresses are allowed: FRONTEND_URL,
| APP_URL (the API docs) and any extra addresses in ALLOWED_ORIGINS
| (comma-separated, e.g. a Vercel preview address).
|
| The AI overview accepts requests only from these addresses (see
| App\Http\Middleware\AllowedOriginsOnly).
|
*/

$origins = array_map(
    fn (string $url) => strtolower(rtrim(trim($url), '/')),
    [(string) env('FRONTEND_URL'), (string) env('APP_URL'), ...explode(',', (string) env('ALLOWED_ORIGINS'))],
);

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter($origins))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
