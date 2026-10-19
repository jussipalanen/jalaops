<?php

namespace App\Providers;

use App\Services\Ai\AiClient;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\PuterClient;
use App\Services\Modules\ModuleRegistry;
use App\Services\Modules\ModuleRunner;
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

        // Python modules: the folder and the interpreter come from config/modules.php.
        $this->app->bind(ModuleRegistry::class, fn () => ModuleRegistry::fromConfig());
        $this->app->bind(ModuleRunner::class, fn () => ModuleRunner::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Refreshing the AI overview calls the AI provider, so each visitor gets only a few refreshes.
        RateLimiter::for('ai-overview-refresh', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));

        // Address searches go to Nominatim, whose usage policy asks for light use.
        RateLimiter::for('geocode', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('imports', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('geocode-suggest', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Each module run starts a Python process on the server. A module's
        // view can have several pages (a list, an item, a form), each a run.
        RateLimiter::for('modules', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('module-settings', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        // Loading the dashboard's widgets runs each widget's module.
        RateLimiter::for('module-widgets', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}
