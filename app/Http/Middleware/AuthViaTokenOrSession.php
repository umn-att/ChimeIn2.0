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
                }
            } catch (\Exception $e) {
                // Token validation failed, let AuthIfNecessary handle it
            }
        }

        return $next($request);
    }
}
