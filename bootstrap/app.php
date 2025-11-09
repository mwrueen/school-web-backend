<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'analytics' => \App\Http\Middleware\AnalyticsMiddleware::class,
            'rate.limit' => \App\Http\Middleware\RateLimitingMiddleware::class,
            'sanitize' => \App\Http\Middleware\SanitizeInputMiddleware::class,
            'cache.response' => \App\Http\Middleware\CacheResponseMiddleware::class,
        ]);
        
        // Add security and analytics middleware to API routes
        $middleware->group('api', [
            \App\Http\Middleware\SanitizeInputMiddleware::class,
            \App\Http\Middleware\AnalyticsMiddleware::class,
        ]);
        
        // Add global security middleware
        $middleware->append([
            \App\Http\Middleware\SanitizeInputMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->reportable(function (Throwable $e) {
            // Log exceptions to our monitoring system
            try {
                $monitoringService = app(\App\Services\MonitoringService::class);
                $request = request();
                
                $monitoringService->logError(
                    $e->getMessage(),
                    [
                        'exception_class' => get_class($e),
                        'exception_code' => $e->getCode(),
                        'url' => $request->fullUrl(),
                        'method' => $request->method(),
                    ],
                    $request,
                    $e
                );
            } catch (\Exception $loggingException) {
                // If monitoring logging fails, fall back to Laravel's default logging
                \Log::error('Failed to log exception to monitoring system: ' . $loggingException->getMessage());
            }
        });
    })->create();
