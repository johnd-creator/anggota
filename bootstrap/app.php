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
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\LogConflictResponses::class,
            \App\Http\Middleware\RequestIdMiddleware::class,
            \App\Http\Middleware\SecurityHeadersMiddleware::class,
            \App\Http\Middleware\RevokedSessionMiddleware::class,
            \App\Http\Middleware\TrackUserSessionMiddleware::class,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'api_token' => \App\Http\Middleware\ApiTokenMiddleware::class,
        ]);
    })
        ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
            $schedule->command('backup:database')->dailyAt('02:00');
            $schedule->command('kpi:weekly')->weeklyOn(1, '03:00');
            $schedule->command('sla:remind-mutations')->dailyAt('08:00');
            $schedule->command('sla:remind-onboarding')->dailyAt('09:00');
            $schedule->command('sla:remind-updates')->dailyAt('09:30');
            $schedule->command('notifications:digest')->dailyAt('18:00');
        })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
