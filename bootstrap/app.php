<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Lewat ngrok halaman datang sebagai https, tapi nginx meneruskannya ke
        // php-fpm sebagai http. Tanpa ini asset() menulis http://...css dan
        // browser memblokirnya sebagai mixed content - halaman tampil polos
        // tanpa CSS/JS. Satu-satunya yang bicara ke php-fpm adalah nginx di
        // mesin yang sama, jadi memercayai header X-Forwarded-* dari '*' aman.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
