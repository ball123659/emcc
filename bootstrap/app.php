<?php

use App\Http\Middleware\BlockUnusedRoutes;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TRUSTED_PROXIES อ่านจาก config/emcc.php ตอนรับคำขอ (ใน AppServiceProvider)
        // ห้ามใช้ env() ตรงนี้: ไฟล์นี้ทำงานก่อนโหลด .env และหลัง php artisan optimize ก็ไม่อ่าน .env อีก

        $middleware->prepend(BlockUnusedRoutes::class);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
