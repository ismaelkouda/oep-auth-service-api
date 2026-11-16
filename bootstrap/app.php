<?php

use App\Http\Middleware\AuthenticateService;
use App\Http\Middleware\EnsureCanManageAccounts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'service' => AuthenticateService::class,
            'accounts.manage' => EnsureCanManageAccounts::class,
        ]);
        $middleware->api(append: ['throttle:api']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('v1/*')) {
                return true;
            }

            return $request->expectsJson();
        });
    })->create();
