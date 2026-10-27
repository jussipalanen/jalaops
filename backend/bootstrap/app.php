<?php

use App\Http\Middleware\NoIndex;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

        // The app logs in with a session cookie (see AuthController), so the
        // API gets the web group's cookies, session and CSRF check. The app
        // and the API share one address, so the cookie needs no CORS setup.
        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ValidateCsrfToken::class,
        ]);

        // Without a login the API answers 401, also to a page opened in a
        // new tab (a request's PDF): the backend has no login page of its own.
        $middleware->redirectGuestsTo(null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Finnish messages for logins, roles and expired sessions.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return $request->is('api/*') ? response()->json(['message' => 'Kirjaudu ensin sisään.'], 401) : null;
        });
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            return $request->is('api/*') ? response()->json(['message' => 'Sinulla ei ole oikeutta tähän toimintoon.'], 403) : null;
        });
        $exceptions->render(function (HttpException $e, Request $request) {
            return $request->is('api/*') && $e->getPrevious() instanceof TokenMismatchException
                ? response()->json(['message' => 'Istunto on vanhentunut. Lataa sivu uudelleen.'], 419)
                : null;
        });

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
