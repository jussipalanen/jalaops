<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Login with a session cookie. The app and the API share one address (Vercel
 * forwards /api/*), so the browser keeps the cookie like on any website.
 * Requests that change something also need the XSRF-TOKEN cookie's value in
 * the X-XSRF-TOKEN header.
 *
 * @tags Login
 */
class AuthController extends Controller
{
    /**
     * Log in.
     *
     * Checks the email and password and starts a session. At most 5 attempts
     * per minute per visitor.
     *
     * @unauthenticated
     */
    public function login(Request $request): UserResource
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'Sähköposti tai salasana on väärin.']);
        }

        // A new session id, so an id known before the login is no use.
        $request->session()->regenerate();

        return new UserResource($request->user());
    }

    /**
     * Log in to a demo account.
     *
     * Signs in as the shared demo user (`role: user`) or demo admin
     * (`role: admin`) without a password. Only when the demo buttons are on
     * (demo mode and local development, see `DEMO_LOGIN`); otherwise 404.
     *
     * @unauthenticated
     */
    public function demo(Request $request): JsonResponse
    {
        abort_unless(config('auth.demo_login'), 404);

        $validated = $request->validate(['role' => ['required', Rule::enum(UserRole::class)]]);

        Auth::login(User::demoAccount(UserRole::from($validated['role'])));
        $request->session()->regenerate();

        // 200 also when the demo account was just created: this is a login.
        return (new UserResource($request->user()))->response()->setStatusCode(200);
    }

    /**
     * Log out.
     *
     * Ends the session.
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * The logged-in user.
     *
     * 401 when no one is logged in.
     */
    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Login options.
     *
     * Whether the login page shows the demo buttons.
     *
     * @unauthenticated
     *
     * @response array{demo_login: bool}
     */
    public function options(): JsonResponse
    {
        return response()->json(['demo_login' => (bool) config('auth.demo_login')]);
    }
}
