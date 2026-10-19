<?php

use App\Http\Middleware\NoIndex;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // A demo application: keep every page and API response out of search engines.
        $middleware->append(NoIndex::class);

        // Cloud Run (and Vercel in front of it) pass the visitor's address in
        // X-Forwarded-For. Without this, every visitor would have the proxy's
        // address and share one rate limit.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Finnish 404 messages for the API instead of Laravel's English defaults.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();
            $message = match (true) {
                $previous instanceof ModelNotFoundException && $previous->getModel() === Customer::class => 'Asiakasta ei löytynyt.',
                $previous instanceof ModelNotFoundException && $previous->getModel() === Invoice::class => 'Laskua ei löytynyt.',
                $previous instanceof ModelNotFoundException => 'Pyyntöä ei löytynyt.',
                default => 'Osoitetta ei löytynyt.',
            };

            return response()->json(['message' => $message], 404);
        });
    })->create();
