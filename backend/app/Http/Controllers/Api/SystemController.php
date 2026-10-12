<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Throwable;

/**
 * @tags System
 */
class SystemController extends Controller
{
    /**
     * Application details.
     *
     * The API version, environment, demo mode and time zone, and a health check
     * of the database (with its response time) and the cache. With
     * `SHOW_SYSTEM_DETAILS=true` (the default only when `APP_ENV=local`) also
     * the PHP, Laravel and database versions and the cache and queue drivers.
     *
     * @response array{app: array{name: string, api_version: string, environment: string, demo: bool, timezone: string, locale: string, server_time: string}, health: array{database: array{status: string, response_ms: float|null}, cache: array{status: string}}, details: array{php: string, laravel: string, database: array{driver: string, version: string|null}, cache_driver: string, queue_driver: string}|null}
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'app' => [
                'name' => 'JalaOps',
                'api_version' => config('scramble.info.version'),
                'environment' => app()->environment(),
                'demo' => config('app.demo_mode'),
                'timezone' => config('app.timezone'),
                'locale' => app()->getLocale(),
                'server_time' => now()->toIso8601String(),
            ],
            'health' => [
                'database' => $this->databaseHealth(),
                'cache' => $this->cacheHealth(),
            ],
            'details' => config('app.show_system_details') ? $this->details() : null,
        ]);
    }

    /**
     * @return array{status: string, response_ms: float|null}
     */
    private function databaseHealth(): array
    {
        try {
            $started = hrtime(true);
            DB::select('select 1');

            return ['status' => 'ok', 'response_ms' => round((hrtime(true) - $started) / 1e6, 1)];
        } catch (Throwable) {
            return ['status' => 'error', 'response_ms' => null];
        }
    }

    /**
     * @return array{status: string}
     */
    private function cacheHealth(): array
    {
        try {
            $key = 'system:health:'.Str::random(8);
            Cache::put($key, true, 10);
            $ok = Cache::pull($key) === true;

            return ['status' => $ok ? 'ok' : 'error'];
        } catch (Throwable) {
            return ['status' => 'error'];
        }
    }

    /**
     * @return array{php: string, laravel: string, database: array{driver: string, version: string|null}, cache_driver: string, queue_driver: string}
     */
    private function details(): array
    {
        $connection = DB::connection();

        try {
            $databaseVersion = (string) $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (Throwable) {
            $databaseVersion = null;
        }

        return [
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'database' => ['driver' => $connection->getDriverName(), 'version' => $databaseVersion],
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
        ];
    }
}
