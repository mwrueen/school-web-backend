<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MonitoringService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MonitoringController extends Controller
{
    protected $monitoringService;

    public function __construct(MonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    /**
     * Get system health dashboard
     */
    public function systemHealth(): JsonResponse
    {
        try {
            $health = $this->monitoringService->getSystemHealth();
            return response()->json($health);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get system health'], 500);
        }
    }

    /**
     * Get error statistics
     */
    public function errorStats(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);

        try {
            $stats = $this->monitoringService->getErrorStats($days);
            return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get error statistics'], 500);
        }
    }

    /**
     * Get security event statistics
     */
    public function securityStats(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);

        try {
            $stats = $this->monitoringService->getSecurityStats($days);
            return response()->json($stats);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get security statistics'], 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function performanceMetrics(Request $request): JsonResponse
    {
        $days = $request->input('days', 7);

        try {
            $metrics = $this->monitoringService->getPerformanceMetrics($days);
            return response()->json($metrics);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get performance metrics'], 500);
        }
    }

    /**
     * Get recent logs
     */
    public function recentLogs(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 100);
        $level = $request->input('level');
        $type = $request->input('type');
        $days = $request->input('days', 1);

        try {
            $logs = $this->monitoringService->getRecentLogs($limit, $level, $type, $days);
            return response()->json($logs);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get recent logs'], 500);
        }
    }

    /**
     * Log a custom event (for testing or manual logging)
     */
    public function logEvent(Request $request): JsonResponse
    {
        $request->validate([
            'level' => 'required|string|in:debug,info,warning,error',
            'type' => 'required|string|in:error,security,performance,system',
            'message' => 'required|string|max:255',
            'context' => 'nullable|array'
        ]);

        try {
            $log = $this->monitoringService->log(
                $request->input('level'),
                $request->input('type'),
                $request->input('message'),
                $request->input('context', []),
                $request
            );

            return response()->json(['success' => true, 'log_id' => $log->id]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to log event'], 500);
        }
    }

    /**
     * Clean up old logs
     */
    public function cleanupLogs(Request $request): JsonResponse
    {
        $daysToKeep = $request->input('days_to_keep', 90);

        try {
            $deletedCount = $this->monitoringService->cleanupOldLogs($daysToKeep);
            return response()->json([
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Deleted {$deletedCount} old log entries"
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to cleanup logs'], 500);
        }
    }
}
