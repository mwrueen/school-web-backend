<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class HomeController extends Controller
{
    /**
     * Get home page data including latest news, announcements, and featured events
     */
    public function index(Request $request): JsonResponse
    {
        // Use cache for home page data (5 minutes)
        $cacheKey = 'home_page_data';
        $data = Cache::remember($cacheKey, 300, function () {
            // Optimize queries with select to reduce data transfer
            $latestNews = Announcement::public()
                ->published()
                ->byType('news')
                ->select(['id', 'title', 'content', 'published_at', 'created_at'])
                ->orderBy('published_at', 'desc')
                ->limit(5)
                ->get();

            $latestAnnouncements = Announcement::public()
                ->published()
                ->byType('notice')
                ->select(['id', 'title', 'content', 'published_at', 'created_at'])
                ->orderBy('published_at', 'desc')
                ->limit(5)
                ->get();

            // Get featured/upcoming events with optimized query
            $featuredEvents = Event::public()
                ->upcoming()
                ->select(['id', 'title', 'description', 'event_date', 'location', 'type'])
                ->orderBy('event_date', 'asc')
                ->limit(5)
                ->get();

            // Get recent achievements (announcements of type achievement)
            $achievements = Announcement::public()
                ->published()
                ->byType('achievement')
                ->select(['id', 'title', 'content', 'published_at', 'created_at'])
                ->orderBy('published_at', 'desc')
                ->limit(3)
                ->get();

            return [
                'latest_news' => $latestNews,
                'latest_announcements' => $latestAnnouncements,
                'featured_events' => $featuredEvents,
                'achievements' => $achievements,
            ];
        });

        // Get quick access links configuration (cached separately)
        $quickAccessLinks = $this->getQuickAccessLinks();

        return response()->json([
            'success' => true,
            'data' => array_merge($data, [
                'quick_access_links' => $quickAccessLinks,
                'last_updated' => Carbon::now()->toISOString(),
            ]),
        ]);
    }

    /**
     * Get latest news only
     */
    public function news(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        
        $news = Announcement::public()
            ->published()
            ->byType('news')
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $news,
        ]);
    }

    /**
     * Get latest announcements only
     */
    public function announcements(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        
        $announcements = Announcement::public()
            ->published()
            ->byType('notice')
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $announcements,
        ]);
    }

    /**
     * Get featured events for home page
     */
    public function featuredEvents(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 5);
        
        $events = Event::public()
            ->upcoming()
            ->orderBy('event_date', 'asc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Get achievements
     */
    public function achievements(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 5);
        
        $achievements = Announcement::public()
            ->published()
            ->byType('achievement')
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $achievements,
        ]);
    }

    /**
     * Get quick access links configuration
     */
    public function quickAccessLinks(): JsonResponse
    {
        $links = $this->getQuickAccessLinks();

        return response()->json([
            'success' => true,
            'data' => $links,
        ]);
    }

    /**
     * Get the quick access links configuration
     * In a real application, this might come from a database or config file
     */
    private function getQuickAccessLinks(): array
    {
        return [
            [
                'title' => 'Parent Portal',
                'description' => 'Access student information and communicate with teachers',
                'url' => '/parent-portal',
                'icon' => 'users',
                'external' => false,
            ],
            [
                'title' => 'Teacher Portal',
                'description' => 'Manage classes, assignments, and student records',
                'url' => '/teacher-portal',
                'icon' => 'academic-cap',
                'external' => false,
            ],
            [
                'title' => 'Academic Calendar',
                'description' => 'View important dates and school events',
                'url' => '/academics/calendar',
                'icon' => 'calendar',
                'external' => false,
            ],
            [
                'title' => 'Admissions',
                'description' => 'Learn about our admission process and requirements',
                'url' => '/admissions',
                'icon' => 'document-text',
                'external' => false,
            ],
            [
                'title' => 'Contact Us',
                'description' => 'Get in touch with our administration',
                'url' => '/contact',
                'icon' => 'phone',
                'external' => false,
            ],
            [
                'title' => 'Online Library',
                'description' => 'Access digital resources and e-books',
                'url' => '/library',
                'icon' => 'book-open',
                'external' => false,
            ],
        ];
    }
}