<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only when it comes from the app's own pages
 * (config/cors.php's allowed_origins), so other websites and plain scripts
 * can't use routes that cost money, such as the AI overview. The page is
 * read from the Origin header, or from the Referer for same-origin GET
 * requests, which browsers send without an Origin.
 *
 * This isn't a login: a script can fake these headers, so the routes keep
 * their rate limits. Off when APP_ENV=local, so the app also works from
 * phones on the local network.
 */
class AllowedOriginsOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isLocal() || in_array($this->origin($request), config('cors.allowed_origins'), true)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Tätä toimintoa voi käyttää vain JalaOpsin omalla sivustolla.',
        ], 403);
    }

    /**
     * The scheme, host and port of the page the request came from, or null.
     */
    private function origin(Request $request): ?string
    {
        if ($request->headers->has('Origin')) {
            return strtolower((string) $request->headers->get('Origin'));
        }

        $referer = parse_url((string) $request->headers->get('Referer'));

        if (! isset($referer['scheme'], $referer['host'])) {
            return null;
        }

        $port = isset($referer['port']) ? ":{$referer['port']}" : '';

        return strtolower("{$referer['scheme']}://{$referer['host']}{$port}");
    }
}
