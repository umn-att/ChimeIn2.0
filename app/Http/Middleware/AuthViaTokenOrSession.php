<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthViaTokenOrSession
{
    /**
     * Authenticate via Sanctum bearer token if present, otherwise pass through
     * to allow session-based auth (Shibboleth) to take over.
     *
     * If a bearer token is provided in the Authorization header, Sanctum will
     * automatically authenticate the user. If no bearer token is present,
     * the session/Shibboleth authentication flow takes over via AuthIfNecessary
     * and ShibInjection middleware.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // If a bearer token is present, attempt Sanctum authentication
        if ($request->bearerToken()) {
            // Try to authenticate using the sanctum guard
            try {
                $user = Auth::guard('sanctum')->user();
                if ($user) {
                    Auth::setUser($user);
                } else {
                    // Token was provided but is not valid — reject with 401
                    return response()->json(['message' => 'Invalid API token.'], 401);
                }
            } catch (\Exception $e) {
                return response()->json(['message' => 'Invalid API token.'], 401);
            }

            // Restrict token-authenticated requests to the slides-addon allowlist.
            // All other routes (create/modify chimes, questions, responses, etc.)
            // must be accessed via session auth only.
            if (!$this->isAllowedForToken($request)) {
                return response()->json([
                    'message' => 'API tokens are restricted to read-only Google Slides integration endpoints.',
                ], 403);
            }
        }

        return $next($request);
    }

    /**
     * Returns true if the request matches the allowlist of routes
     * that token-authenticated clients (i.e. the Slides add-on) may access.
     */
    private function isAllowedForToken(Request $request): bool
    {
        if (!$request->isMethod('GET')) {
            return false;
        }

        return $request->is(
            'api/users/self',
            'api/chime',
            'api/chime/*/openQuestions',
            'api/chime/*/session/*/results',
            'api/chime/*/qrcode'
        );
    }
}
