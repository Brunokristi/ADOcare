<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class ApiTokenAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization');
        if (! $header || ! str_starts_with($header, 'Bearer ')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = trim(substr($header, 7));
        if ($token === '') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $hashed = hash('sha256', $token);
        $user = User::where('api_token', $hashed)->first();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // set the current user for the request
        Auth::setUser($user);

        return $next($request);
    }
}
