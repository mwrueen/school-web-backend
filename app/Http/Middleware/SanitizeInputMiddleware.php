<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInputMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanitize all input data
        $this->sanitizeInput($request);
        
        return $next($request);
    }

    /**
     * Sanitize request input
     */
    protected function sanitizeInput(Request $request): void
    {
        $input = $request->all();
        $sanitized = $this->sanitizeArray($input);
        
        $request->replace($sanitized);
    }

    /**
     * Recursively sanitize array data
     */
    protected function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $sanitizedKey = $this->sanitizeKey($key);
            
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                // Skip aggressive sanitization for password fields
                if (in_array($key, ['password', 'password_confirmation', 'current_password', 'new_password'])) {
                    // Only remove null bytes for password fields
                    $sanitized[$sanitizedKey] = str_replace("\0", '', $value);
                } else {
                    $sanitized[$sanitizedKey] = $this->sanitizeString($value);
                }
            } else {
                $sanitized[$sanitizedKey] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitize array keys
     */
    protected function sanitizeKey(string $key): string
    {
        // Remove any non-alphanumeric characters except underscores and hyphens
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
    }

    /**
     * Sanitize string values
     */
    protected function sanitizeString(string $value): string
    {
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        
        // Remove or encode potentially dangerous characters
        $value = $this->removeDangerousPatterns($value);
        
        // Trim whitespace
        return trim($value);
    }

    /**
     * Remove dangerous patterns from input
     */
    protected function removeDangerousPatterns(string $value): string
    {
        // SQL injection patterns
        $sqlPatterns = [
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i',
            '/(\-\-|\#|\/\*|\*\/)/i',
            '/(\'|\"|;|\||&|\$|\`)/i'
        ];

        // XSS patterns
        $xssPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/i',
            '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/i',
            '/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/i'
        ];

        // Command injection patterns
        $commandPatterns = [
            '/(\||&|;|\$\(|\`|<|>)/i',
            '/(rm|mv|cp|chmod|chown|sudo|su|passwd|cat|more|less|head|tail|grep|find|locate|which|whoami|id|ps|top|kill|killall|mount|umount|df|du|free|uname|uptime|w|who|last|history|crontab|at|batch|nohup|screen|tmux)/i'
        ];

        // Apply all pattern removals
        $allPatterns = array_merge($sqlPatterns, $xssPatterns, $commandPatterns);
        
        foreach ($allPatterns as $pattern) {
            $value = preg_replace($pattern, '', $value);
        }

        return $value;
    }
}