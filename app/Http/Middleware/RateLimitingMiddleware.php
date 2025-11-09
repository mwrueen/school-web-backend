<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RateLimitingMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $type = 'general'): Response
    {
        $key = $this->resolveRequestSignature($request, $type);
        $maxAttempts = $this->getMaxAttempts($type);
        $decayMinutes = $this->getDecayMinutes($type);

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            
            return $this->buildResponse($type, $retryAfter);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - RateLimiter::attempts($key)));
        $response->headers->set('X-RateLimit-Reset', now()->addSeconds($retryAfter ?? 0)->timestamp);

        return $response;
    }

    /**
     * Resolve request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request, string $type): string
    {
        $user = $request->user();
        
        if ($user) {
            return $type . '.' . $user->id;
        }

        return $type . '.' . $request->ip();
    }

    /**
     * Get maximum attempts for rate limit type
     */
    protected function getMaxAttempts(string $type): int
    {
        return match ($type) {
            'login' => 5,           // 5 login attempts
            'api' => 100,           // 100 API calls per minute
            'upload' => 10,         // 10 file uploads per minute
            'content' => 20,        // 20 content operations per minute
            'search' => 30,         // 30 search requests per minute
            'public' => 60,         // 60 public API calls per minute
            default => 60,          // Default 60 requests per minute
        };
    }

    /**
     * Get decay time in minutes for rate limit type
     */
    protected function getDecayMinutes(string $type): int
    {
        return match ($type) {
            'login' => 15,          // 15 minute lockout for failed logins
            'upload' => 5,          // 5 minute cooldown for uploads
            default => 1,           // Default 1 minute window
        };
    }

    /**
     * Build rate limit exceeded response
     */
    protected function buildResponse(string $type, int $retryAfter): JsonResponse
    {
        $message = match ($type) {
            'login' => 'Too many login attempts. Please try again later.',
            'upload' => 'Too many file uploads. Please wait before uploading again.',
            'content' => 'Too many content operations. Please slow down.',
            'search' => 'Too many search requests. Please wait before searching again.',
            default => 'Too many requests. Please slow down.',
        };

        return response()->json([
            'success' => false,
            'message' => $message,
            'retry_after' => $retryAfter,
            'error_code' => 'RATE_LIMIT_EXCEEDED'
        ], 429);
    }
}