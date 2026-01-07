<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ensure the request is authenticated via Sanctum and return a JSON 401 when not.
 *
 * Use this middleware in API routes instead of `auth:sanctum` when you want
 * unauthenticated requests to get a JSON error instead of a redirect.
 */
class EnsureApiAuthenticated
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Try the sanctum guard. This will attempt to resolve a user from the
        // token/cookie as configured. If the guard has no user, return JSON 401.
        if (!Auth::guard('sanctum')->check()) {
            return $this->error('Unauthenticated.', 401);
        }

        // Ensure subsequent auth() calls use sanctum for this request.
        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
