<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AdminOnlyMiddleware;
use App\Http\Middleware\ImmigrationAccessMiddleware;
use App\Http\Middleware\PageAccessMiddleware;
use App\Http\Middleware\SrrvAccessMiddleware;
use App\Http\Middleware\TicketingAccessMiddleware;
use App\Http\Middleware\VisaAssistanceAccessMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'admin.only' => AdminOnlyMiddleware::class,
            'immigration' => ImmigrationAccessMiddleware::class,
            'page.access' => PageAccessMiddleware::class,
            'srrv' => SrrvAccessMiddleware::class,
            'ticketing' => TicketingAccessMiddleware::class,
            'visa' => VisaAssistanceAccessMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
