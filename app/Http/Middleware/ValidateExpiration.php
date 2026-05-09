<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateExpiration
{
    public function handle(Request $request, Closure $next): Response
    {
        $expires = is_numeric($request->query('expires')) ? (int) $request->query('expires') : null;
        if (!$expires) {
            return $next($request);
        }

        if (is_numeric($expires) && (int) $expires < now()->getTimestamp()) {
            abort(403, 'Odkaz už vypršal.');
        }

        return $next($request);
    }
}
