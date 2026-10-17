<?php

use Ecommerce\Catalog\Infrastructure\Http\CatalogExceptions;
use Ecommerce\Identity\Http\Middleware\EnsureUserHasRole;
use Ecommerce\Import\Infrastructure\Http\ImportExceptions;
use Ecommerce\Ordering\Infrastructure\Http\OrderingExceptions;
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
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias(['role' => EnsureUserHasRole::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        CatalogExceptions::register($exceptions);
        ImportExceptions::register($exceptions);
        OrderingExceptions::register($exceptions);
    })->create();
