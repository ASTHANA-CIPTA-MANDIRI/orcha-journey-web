<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'kunci.orcha' => \App\Http\Middleware\PeriksaKunciApi::class,
        ]);

        // Header keamanan untuk SELURUH jawaban, termasuk berkas dan API —
        // bukan hanya halaman web. Lihat alasannya di kelasnya.
        $middleware->append(\App\Http\Middleware\HeaderKeamanan::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
