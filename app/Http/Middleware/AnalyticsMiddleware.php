<?php

namespace App\Http\Middleware;

use App\Services\AnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsMiddleware
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track GET requests to public routes that return successful responses
        if ($request->isMethod('GET') && 
            $response->getStatusCode() >= 200 && 
            $response->getStatusCode() < 300 &&
            $this->shouldTrack($request)) {
            
            try {
                // Track page view asynchronously to avoid slowing down the response
                dispatch(function () use ($request) {
                    $this->analyticsService->track($request, 'page_view');
                });
            } catch (\Exception $e) {
                // Silently fail - analytics shouldn't break the application
                \Log::error('Analytics tracking failed: ' . $e->getMessage());
            }
        }

        return $response;
    }

    /**
     * Determine if the request should be tracked
     */
    private function shouldTrack(Request $request): bool
    {
        $path = $request->path();
        
        // Track public routes
        $trackableRoutes = [
            'api/public/',
            'api/resources/',
        ];

        foreach ($trackableRoutes as $route) {
            if (str_starts_with($path, $route)) {
                return true;
            }
        }

        // Don't track API endpoints that are not public content
        if (str_starts_with($path, 'api/')) {
            return false;
        }

        return false;
    }
}
