<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Http\Middleware\HandleCors;
use App\Http\Middleware\SuperAdminMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

       //  CORS middleware globally
        $middleware->append(App\Http\Middleware\Cors::class);
        
        // Or for API only
        $middleware->api(append: [
            HandleCors::class,
        ]);
         // Register route middleware aliases

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'super_admin' => SuperAdminMiddleware::class,
        ]);

         // Configure CORS
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        
        $middleware->trustHosts(at: [
            'localhost',
            '127.0.0.1',
            '::1',
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
