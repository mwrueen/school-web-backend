<?php

namespace App\Services;

use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MonitoringService
{
    /**
     * Log a system event
     */
    public function log(
        string $level,
        string $type,
        string $message,
        array $context = [],
        ?Request $request = null,
        ?string $file = null,
        ?int $line = null,
        ?string $stackTrace = null
    ) {
        try {
            $logData = [
                'level' => $level,
                'type' => $type,
                'message' => $message,
                'context' => json_encode($context),
                'file' => $file,
                'line' => $line,
                'stack_trace' => $stackTrace,
                'logged_at' => now()
            ];

            if ($request) {
                $logData['user_id'] = $request->user()?->id;
                $logData['ip_address'] = $request->ip();
                $logData['user_agent'] = $request->header('user-agent');
                $logData['request_id'] = $request->header('x-request-id') ?? uniqid();
                $logData['session_id'] = $request->hasSession() ? $request->session()->getId() : null;
            }

            return SystemLog::create($logData);
        } catch (\Exception $e) {
            // Fallback to Laravel's default logging if database logging fails
            Log::error('Failed to log to database: ' . $e->getMessage(), [
                'original_message' => $message,
                'original_context' => $context
            ]);
        }
    }

    /**
     * Log an error
     */
    public function logError(string $message, array $context = [], ?Request $request = null, ?\Throwable $exception = null)
    {
        $file = null;
        $line = null;
        $stackTrace = null;

        if ($exception) {
            $file = $exception->getFile();
            $line = $exception->getLine();
            $stackTrace = $exception->getTraceAsString();
            $context['exception_class'] = get_class($exception);
            $context['exception_code'] = $exception->getCode();
        }

        return $this->log(
            SystemLog::LEVEL_ERROR,
            SystemLog::TYPE_ERROR,
            $message,
            $context,
            $request,
            $file,
            $line,
            $stackTrace
        );
    }

    /**
     * Log a security event
     */
    public function logSecurityEvent(string $message, array $context = [], ?Request $request = null)
    {
        return $this->log(
            SystemLog::LEVEL_WARNING,
            SystemLog::TYPE_SECURITY,
            $message,
            $context,
            $request
        );
    }

    /**
     * Log a performance issue
     */
    public function logPerformanceIssue(string $message, array $context = [], ?Request $request = null)
    {
        return $this->log(
            SystemLog::LEVEL_WARNING,
            SystemLog::TYPE_PERFORMANCE,
            $message,
            $context,
            $request
        );
    }

    /**
     * Log system information
     */
    public function logSystemInfo(string $message, array $context = [], ?Request $request = null)
    {
        return $this->log(
            SystemLog::LEVEL_INFO,
            SystemLog::TYPE_SYSTEM,
            $message,
            $context,
            $request
        );
    }

    /**
     * Get system health metrics
     */
    public function getSystemHealth()
    {
        $last24Hours = now()->subHours(24);
        $lastWeek = now()->subWeek();

        return [
            'errors_last_24h' => SystemLog::errors()
                ->where('logged_at', '>=', $last24Hours)
                ->count(),
            'warnings_last_24h' => SystemLog::warnings()
                ->where('logged_at', '>=', $last24Hours)
                ->count(),
            'security_events_last_week' => SystemLog::securityEvents()
                ->where('logged_at', '>=', $lastWeek)
                ->count(),
            'performance_issues_last_week' => SystemLog::performanceIssues()
                ->where('logged_at', '>=', $lastWeek)
                ->count(),
            'database_status' => $this->checkDatabaseHealth(),
            'disk_usage' => $this->getDiskUsage(),
            'memory_usage' => $this->getMemoryUsage(),
        ];
    }

    /**
     * Get error statistics
     */
    public function getErrorStats($days = 7)
    {
        $startDate = now()->subDays($days);

        return [
            'total_errors' => SystemLog::errors()
                ->inDateRange($startDate, now())
                ->count(),
            'errors_by_day' => SystemLog::errors()
                ->inDateRange($startDate, now())
                ->select(
                    DB::raw('DATE(logged_at) as date'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'top_error_messages' => SystemLog::errors()
                ->inDateRange($startDate, now())
                ->select('message', DB::raw('COUNT(*) as count'))
                ->groupBy('message')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'errors_by_file' => SystemLog::errors()
                ->inDateRange($startDate, now())
                ->whereNotNull('file')
                ->select('file', DB::raw('COUNT(*) as count'))
                ->groupBy('file')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * Get security event statistics
     */
    public function getSecurityStats($days = 30)
    {
        $startDate = now()->subDays($days);

        return [
            'total_security_events' => SystemLog::securityEvents()
                ->inDateRange($startDate, now())
                ->count(),
            'events_by_day' => SystemLog::securityEvents()
                ->inDateRange($startDate, now())
                ->select(
                    DB::raw('DATE(logged_at) as date'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'events_by_ip' => SystemLog::securityEvents()
                ->inDateRange($startDate, now())
                ->whereNotNull('ip_address')
                ->select('ip_address', DB::raw('COUNT(*) as count'))
                ->groupBy('ip_address')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'recent_events' => SystemLog::securityEvents()
                ->inDateRange($startDate, now())
                ->orderByDesc('logged_at')
                ->limit(20)
                ->get(),
        ];
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics($days = 7)
    {
        $startDate = now()->subDays($days);

        return [
            'total_performance_issues' => SystemLog::performanceIssues()
                ->inDateRange($startDate, now())
                ->count(),
            'issues_by_day' => SystemLog::performanceIssues()
                ->inDateRange($startDate, now())
                ->select(
                    DB::raw('DATE(logged_at) as date'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'slow_queries' => SystemLog::performanceIssues()
                ->inDateRange($startDate, now())
                ->where('message', 'like', '%slow query%')
                ->orderByDesc('logged_at')
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * Get recent logs with filtering
     */
    public function getRecentLogs($limit = 100, $level = null, $type = null, $days = 1)
    {
        $query = SystemLog::query()
            ->inDateRange(now()->subDays($days), now())
            ->orderByDesc('logged_at')
            ->limit($limit);

        if ($level) {
            $query->byLevel($level);
        }

        if ($type) {
            $query->byType($type);
        }

        return $query->get();
    }

    /**
     * Clean up old logs
     */
    public function cleanupOldLogs($daysToKeep = 90)
    {
        $cutoffDate = now()->subDays($daysToKeep);
        
        return SystemLog::where('logged_at', '<', $cutoffDate)->delete();
    }

    /**
     * Check database health
     */
    private function checkDatabaseHealth()
    {
        try {
            DB::connection()->getPdo();
            $connectionTime = microtime(true);
            DB::select('SELECT 1');
            $queryTime = (microtime(true) - $connectionTime) * 1000;

            return [
                'status' => 'healthy',
                'response_time_ms' => round($queryTime, 2)
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get disk usage information
     */
    private function getDiskUsage()
    {
        try {
            $totalBytes = disk_total_space('/');
            $freeBytes = disk_free_space('/');
            $usedBytes = $totalBytes - $freeBytes;
            $usagePercent = ($usedBytes / $totalBytes) * 100;

            return [
                'total_gb' => round($totalBytes / (1024 ** 3), 2),
                'used_gb' => round($usedBytes / (1024 ** 3), 2),
                'free_gb' => round($freeBytes / (1024 ** 3), 2),
                'usage_percent' => round($usagePercent, 2)
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to get disk usage: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get memory usage information
     */
    private function getMemoryUsage()
    {
        try {
            $memoryUsage = memory_get_usage(true);
            $peakMemoryUsage = memory_get_peak_usage(true);
            $memoryLimit = ini_get('memory_limit');

            // Convert memory limit to bytes
            $memoryLimitBytes = $this->convertToBytes($memoryLimit);
            $usagePercent = $memoryLimitBytes > 0 ? ($memoryUsage / $memoryLimitBytes) * 100 : 0;

            return [
                'current_mb' => round($memoryUsage / (1024 ** 2), 2),
                'peak_mb' => round($peakMemoryUsage / (1024 ** 2), 2),
                'limit' => $memoryLimit,
                'usage_percent' => round($usagePercent, 2)
            ];
        } catch (\Exception $e) {
            return [
                'error' => 'Unable to get memory usage: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes($value)
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}