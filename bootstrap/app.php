<?php

declare(strict_types=1);

use App\Http\Middleware\CheckAdmin;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies so HTTPS links are generated correctly on shared hosting.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => CheckAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $e, Request $request): JsonResponse {
            return response()->json([
                'message' => 'خطا در اعتبارسنجی داده‌ها.',
                'errors'  => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request): JsonResponse {
            return response()->json(['message' => 'احراز هویت الزامی است.'], 401);
        });

        $exceptions->render(function (HttpException $e, Request $request): JsonResponse {
            return response()->json(['message' => $e->getMessage() ?: 'خطای سرور.'], $e->getStatusCode());
        });
    })->create();
