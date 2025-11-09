<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    protected $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Track a page view or event
     */
    public function track(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string|in:page_view,content_view,download,search,form_submit',
            'page_title' => 'nullable|string|max:255',
            'metadata' => 'nullable|array'
        ]);

        try {
            $this->analyticsService->track(
                $request,
                $request->input('event_type'),
                $request->input('metadata', []) + ['page_title' => $request->input('page_title')]
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to track event'], 500);
        }
    }

    /**
     * Get analytics dashboard data (admin only)
     */
    public function dashboard(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);

        try {
            $data = [
                'popular_pages' => $this->analyticsService->getPopularPages($days),
                'popular_content' => $this->analyticsService->getPopularContent($days),
                'page_views_over_time' => $this->analyticsService->getPageViewsOverTime($days),
                'engagement_metrics' => $this->analyticsService->getUserEngagementMetrics($days),
                'download_stats' => $this->analyticsService->getDownloadStats($days),
                'real_time' => $this->analyticsService->getRealTimeAnalytics()
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch analytics data'], 500);
        }
    }

    /**
     * Get popular pages
     */
    public function popularPages(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        $limit = $request->input('limit', 10);

        try {
            $popularPages = $this->analyticsService->getPopularPages($days, $limit);
            return response()->json($popularPages);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch popular pages'], 500);
        }
    }

    /**
     * Get popular content
     */
    public function popularContent(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        $limit = $request->input('limit', 10);

        try {
            $popularContent = $this->analyticsService->getPopularContent($days, $limit);
            return response()->json($popularContent);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch popular content'], 500);
        }
    }

    /**
     * Get user engagement metrics
     */
    public function engagementMetrics(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);

        try {
            $metrics = $this->analyticsService->getUserEngagementMetrics($days);
            return response()->json($metrics);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch engagement metrics'], 500);
        }
    }

    /**
     * Get real-time analytics
     */
    public function realTime(): JsonResponse
    {
        try {
            $realTimeData = $this->analyticsService->getRealTimeAnalytics();
            return response()->json($realTimeData);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch real-time data'], 500);
        }
    }
}
