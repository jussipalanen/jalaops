<?php

namespace App\Providers;

use App\Services\Ai\AiClient;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\PuterClient;
use Dedoc\Scramble\Scramble;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // API docs are served at /docs instead of Scramble's default /docs/api (see routes/web.php).
        Scramble::ignoreDefaultRoutes();

        // The AI overview's provider is chosen with AI_INSIGHTS_PROVIDER.
        $this->app->bind(AiClient::class, fn () => match (config('ai_overview.provider')) {
            'puter' => PuterClient::fromConfig(),
            default => GeminiClient::fromConfig(),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Refreshing the AI overview calls the AI provider, so each visitor gets only a few refreshes.
        RateLimiter::for('ai-overview-refresh', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
    }
}
