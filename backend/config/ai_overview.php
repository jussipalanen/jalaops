<?php

/*
|--------------------------------------------------------------------------
| AI status overview (AI-tilannekatsaus)
|--------------------------------------------------------------------------
|
| A short Finnish summary of the requests on the dashboard, written by an
| AI model. It is off by default; turn it on with AI_INSIGHTS_ENABLED=true
| and the key of the chosen provider (see config/services.php):
|
| - gemini: Google Gemini, needs GEMINI_API_KEY
| - puter:  Puter AI, needs PUTER_AUTH_TOKEN
|
*/

return [

    'enabled' => (bool) env('AI_INSIGHTS_ENABLED', false),

    'provider' => env('AI_INSIGHTS_PROVIDER', 'gemini'),

    // How long an overview is reused while the requests stay the same.
    'cache_minutes' => (int) env('AI_INSIGHTS_CACHE_MINUTES', 60),

    // Upper limit of AI calls per hour for the whole app, to stay within
    // the free tier. Past the limit, the previous overview is shown instead.
    'max_generations_per_hour' => (int) env('AI_INSIGHTS_MAX_PER_HOUR', 20),

];
