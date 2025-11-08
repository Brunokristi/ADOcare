<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceJsonResponse
{
    /**
     * Handle an incoming request and ensure JSON responses for API routes.
     */
    public function handle(Request $request, Closure $next)
    {
        // Force the request to expect JSON — this triggers exception handlers and validators
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);

        // Ensure content-type is application/json for all responses
        try {
            $response->headers->set('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            // If response doesn't support headers modification, ignore
        }

        return $response;
    }
}
