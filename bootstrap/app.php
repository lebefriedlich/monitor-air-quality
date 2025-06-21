<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('app:sync-region-air-quality')
            ->dailyAt('00:00')
            ->withoutOverlapping()
            ->onSuccess(function () {
                if (now()->format('H:i') === '00:00') {
                    Log::info('Menjalankan sync-air-quality setelah sync-region-air-quality selesai.');
                    Artisan::call('app:sync-air-quality');
                }
            });

        $schedule->command('app:sync-air-quality')
            ->everyTwoHours()
            ->withoutOverlapping()
            ->when(function () {
                return now()->format('H:i') !== '00:00';
            });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
