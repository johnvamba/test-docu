<?php

use App\Services\Import\ImportException;
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
    // Private/presence channel auth through Sanctum: POST /api/broadcasting/auth
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Session + CSRF for requests coming from SANCTUM_STATEFUL_DOMAINS
        $middleware->statefulApi();
        // Render and similar platforms terminate TLS at a proxy.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Import failures are user errors (bad or unreadable file), shown next to the upload.
        $exceptions->render(fn (ImportException $e) => response()->json([
            'message' => $e->getMessage(),
            'errors' => ['file' => [$e->getMessage()]],
        ], 422));
    })->create();
