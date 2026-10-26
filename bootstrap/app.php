<?php

use App\Http\Middleware\EnforceSessionIdleTimeout;
use App\Http\Middleware\EnsureAdminRegionAccess;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
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
        // Header keamanan global, termasuk untuk respons error (NFR-SEC-017).
        $middleware->append(SecurityHeaders::class);

        // Reverse proxy tepercaya dibaca oleh middleware bawaan Laravel dari
        // `config/trustedproxy.php` (TRUSTED_PROXIES), bukan dari bootstrap,
        // agar tetap benar saat `config:cache` aktif.

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
            'idle' => EnforceSessionIdleTimeout::class,
            'admin-region' => EnsureAdminRegionAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
