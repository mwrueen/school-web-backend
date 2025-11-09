<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, int $ttl = 300): Response
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Generate cache key based on URL and query parameters
        $cacheKey = $this->generateCacheKey($request);

        // Check if response is cached
        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);
            
            return response($cachedResponse['content'], $cachedResponse['status'])
                ->withHeaders($cachedResponse['headers'])
                ->header('X-Cache-Status', 'HIT');
        }

        // Process request
        $response = $next($request);

        // Only cache successful responses
        if ($response->getStatusCode() === 200) {
            $cacheData = [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $this->getHeadersToCache($response),
            ];

            Cache::put($cacheKey, $cacheData, $ttl);
            $response->header('X-Cache-Status', 'MISS');
        }

        return $response;
    }

    /**
     * Generate cache key for the request
     */
    private function generateCacheKey(Request $request): string
    {
        $url = $request->url();
        $queryParams = $request->query();
        
        // Sort query parameters for consistent cache keys
        ksort($queryParams);
        
        return 'api_cache:' . md5($url . serialize($queryParams));
    }

    /**
     * Get headers that should be cached
     */
    private function getHeadersToCache(Response $response): array
    {
        $headersToCahe = ['Content-Type', 'Content-Encoding'];
        $headers = [];

        foreach ($headersToCahe as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->get($header);
            }
        }

        return $headers;
    }
}