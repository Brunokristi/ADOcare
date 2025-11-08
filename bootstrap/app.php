<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias(
            [
                'role' => \App\Http\Middleware\EnsureUserHasRole::class,
                // API auth that returns JSON 401 instead of redirecting
                'api.auth' => \App\Http\Middleware\EnsureApiAuthenticated::class,
            ]
        );

        $middleware->api([
            App\Http\Middleware\ForceJsonResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request, Throwable $e) {
            if ($request->is('api/*'))
                return true;

            return $request->expectsJson();
        });

    })->create();
