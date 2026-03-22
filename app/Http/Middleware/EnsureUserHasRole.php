<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     * Usage: ->middleware('role:manager') or 'role:manager,nurse'
     */
    public function handle(Request $request, Closure $next, ?string $roles = null)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $globalRole = $user->role?->position ?? 'nurse';
        $hasGlobalRole = is_string($globalRole) && trim($globalRole) !== '';

        if ($roles === null || trim($roles) === 'any' || trim($roles) === '') {
            // no roles specified, allow by default
            return $next($request);
        }

        $wanted = array_filter(array_map('trim', explode(',', $roles)));
        if (empty($wanted)) {
            return $next($request);
        }

        // role checks operate on the user's global role column.
        $exists = ($hasGlobalRole && in_array($globalRole, $wanted, true));

        if (!$exists) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
