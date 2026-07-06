<?php

use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ThrottleByTenantTier;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // SubstituteBindings must run after resolve.tenant so route-model-bound
            // lookups (e.g. {incident}) are already scoped by BelongsToTenant —
            // this custom group doesn't get it for free like the default api/web groups do.
            Route::prefix('api/v1')
                ->middleware(['resolve.tenant', 'throttle.tenant', SubstituteBindings::class])
                ->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'resolve.tenant' => ResolveTenant::class,
            'throttle.tenant' => ThrottleByTenantTier::class,
            'verify.webhook' => VerifyWebhookSignature::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('incidents:escalate')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
