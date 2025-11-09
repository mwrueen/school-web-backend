<?php

namespace App\Services;

use App\Models\Analytics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsService
{
    /**
     * Track a page view or event
     */
    public function track(Request $request, string $eventType, array $metadata = [])
    {
        return Analytics::create([
            'event_type' => $eventType,
            'page_url' => $request->url(),
            'page_title' => $metadata['page_title'] ?? null,
            'referrer' => $request->header('referer'),
            'user_agent' => $request->header('user-agent'),
            'ip_address' => $request->ip(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'metadata' => $metadata,
            'viewed_at' => now()
        ]);
    }

    /**
     * Get popular pages based on page views
     */
    public function getPopularPages($days = 30, $limit = 10)
    {
        return Analytics::pageViews()
            ->inDateRange(now()->subDays($days), now())
            ->select('page_url', 'page_title', DB::raw('COUNT(*) as views'))
            ->groupBy('page_url', 'page_title')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();
    }

    /**
     * Get popular content based on content views
     */
    public function getPopularContent($days = 30, $limit = 10)
    {
        return Analytics::contentViews()
            ->inDateRange(now()->subDays($days), now())
            ->whereNotNull('metadata->content_id')
            ->select(
                DB::raw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.content_id")) as content_id'),
                DB::raw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.content_type")) as content_type'),
                DB::raw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.content_title")) as content_title'),
                DB::raw('COUNT(*) as views')
            )
            ->groupBy('content_id', 'content_type', 'content_title')
            ->orderByDesc('views')
            ->limit($limit)
            ->get();
    }

    /**
     * Get page views over time
     */
    public function getPageViewsOverTime($days = 30)
    {
        return Analytics::pageViews()
            ->inDateRange(now()->subDays($days), now())
            ->select(
                DB::raw('DATE(viewed_at) as date'),
                DB::raw('COUNT(*) as views')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    /**
     * Get user engagement metrics
     */
    public function getUserEngagementMetrics($days = 30)
    {
        $startDate = now()->subDays($days);
        $endDate = now();

        $totalViews = Analytics::pageViews()
            ->inDateRange($startDate, $endDate)
            ->count();

        $uniqueSessions = Analytics::pageViews()
            ->inDateRange($startDate, $endDate)
            ->distinct('session_id')
            ->count();

        $avgViewsPerSession = $uniqueSessions > 0 ? round($totalViews / $uniqueSessions, 2) : 0;

        $topReferrers = Analytics::pageViews()
            ->inDateRange($startDate, $endDate)
            ->whereNotNull('referrer')
            ->select('referrer', DB::raw('COUNT(*) as count'))
            ->groupBy('referrer')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        return [
            'total_views' => $totalViews,
            'unique_sessions' => $uniqueSessions,
            'avg_views_per_session' => $avgViewsPerSession,
            'top_referrers' => $topReferrers,
            'period_days' => $days
        ];
    }

    /**
     * Get download statistics
     */
    public function getDownloadStats($days = 30)
    {
        return Analytics::downloads()
            ->inDateRange(now()->subDays($days), now())
            ->select(
                DB::raw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.file_name")) as file_name'),
                DB::raw('JSON_UNQUOTE(JSON_EXTRACT(metadata, "$.file_type")) as file_type'),
                DB::raw('COUNT(*) as downloads')
            )
            ->groupBy('file_name', 'file_type')
            ->orderByDesc('downloads')
            ->get();
    }

    /**
     * Get real-time analytics (last 24 hours)
     */
    public function getRealTimeAnalytics()
    {
        $last24Hours = now()->subHours(24);

        return [
            'views_last_24h' => Analytics::pageViews()
                ->where('viewed_at', '>=', $last24Hours)
                ->count(),
            'active_sessions' => Analytics::pageViews()
                ->where('viewed_at', '>=', now()->subMinutes(30))
                ->distinct('session_id')
                ->count(),
            'top_pages_today' => Analytics::pageViews()
                ->where('viewed_at', '>=', now()->startOfDay())
                ->select('page_url', 'page_title', DB::raw('COUNT(*) as views'))
                ->groupBy('page_url', 'page_title')
                ->orderByDesc('views')
                ->limit(5)
                ->get()
        ];
    }
}