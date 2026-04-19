<?php

use App\Console\Commands\SoftDeleteInactivePatients;
use App\Console\Commands\SendCarMaintenanceNotifications;
use App\Console\Commands\AutoTrainDekurzVertexModel;
use App\Console\Commands\SyncDekurzEndpointAfterTraining;
use App\Http\Responses\ApiResponseClass;
use Illuminate\Console\Scheduling\Schedule;
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
    })->withSchedule(function (Schedule $schedule): void {
        $schedule->command(SoftDeleteInactivePatients::class)->monthlyOn(1, '2:00');
        $schedule->command(SendCarMaintenanceNotifications::class)->dailyAt('6:00');

        $schedule->command('backup:run --only-db')->dailyAt('01:00')->withoutOverlapping();
        $schedule->command('backup:clean')->dailyAt('01:30')->withoutOverlapping();

        $autoTrainSchedule = (string) config('services.vertex_ai.auto_train.schedule', 'daily');
        $autoTrainWeekday = (int) config('services.vertex_ai.auto_train.weekday', 1);
        $autoTrainTime = (string) config('services.vertex_ai.auto_train.time', '02:30');

        $autoTrainEvent = $schedule->command(AutoTrainDekurzVertexModel::class)->withoutOverlapping();

        if ($autoTrainSchedule === 'weekly') {
            $autoTrainEvent->weeklyOn($autoTrainWeekday, $autoTrainTime);
        } elseif ($autoTrainSchedule === 'biweekly') {
            $autoTrainEvent
                ->weeklyOn($autoTrainWeekday, $autoTrainTime)
                ->when(fn() => ((int) now()->isoWeek()) % 2 === 0);
        } else {
            $autoTrainEvent->dailyAt($autoTrainTime);
        }

        $schedule->command(SyncDekurzEndpointAfterTraining::class)->hourly()->withoutOverlapping();

    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request, Throwable $e) {
            if ($request->is('api/*'))
                return true;

            return $request->expectsJson();
        });


        $exceptions->render(function (Throwable $e) {
            if (request()->is('api/*')) {
                $response = new ApiResponseClass();

                $status = 500;
                if (method_exists($e, 'getStatusCode')) {
                    $status = $e->getStatusCode();
                }

                $message = $e->getMessage() ?: 'Server Error';

                $errors = [];

                if ($e instanceof \Illuminate\Validation\ValidationException) {
                    $status = 422;
                    $errors = $e->errors();
                }

                return $response->error($message, $status, $errors);
            }
        });



    })->create();
