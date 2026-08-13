<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureReseller;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'reseller' => EnsureReseller::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // مدیریت پیش‌فرض لاراول عمداً حفظ شده است.
    })->create();
