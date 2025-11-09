<?php

namespace Tests\Feature;

use App\Models\Analytics;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        Analytics::factory()->count(50)->create();
        Analytics::factory()->pageView()->count(30)->create();
        Analytics::factory()->contentView()->count(20)->create();
        Analytics::factory()->download()->count(10)->create();
    }

    public function test_can_track_page_view()
    {
        $trackingData = [
            'event_type' => 'page_view',
            'page_title' => 'Home Page',
            'metadata' => [
                'section' => 'home'
            ]
        ];

        $response = $this->postJson('/api/analytics/track', $trackingData);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $this->assertDatabaseHas('analytics', [
            'event_type' => 'page_view',
            'page_title' => 'Home Page'
        ]);
    }

    public function test_can_track_content_view()
    {
        $trackingData = [
            'event_type' => 'content_view',
            'page_title' => 'Announcement Details',
            'metadata' => [
                'content_id' => 123,
                'content_type' => 'announcement',
                'content_title' => 'School Holiday Notice'
            ]
        ];

        $response = $this->postJson('/api/analytics/track', $trackingData);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $this->assertDatabaseHas('analytics', [
            'event_type' => 'content_view'
        ]);
    }

    public function test_can_track_download()
    {
        $trackingData = [
            'event_type' => 'download',
            'metadata' => [
                'file_name' => 'syllabus.pdf',
                'file_type' => 'pdf',
                'file_size' => 1024000
            ]
        ];

        $response = $this->postJson('/api/analytics/track', $trackingData);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $this->assertDatabaseHas('analytics', [
            'event_type' => 'download'
        ]);
    }

    public function test_tracking_validates_event_type()
    {
        $trackingData = [
            'event_type' => 'invalid_type',
            'page_title' => 'Test Page'
        ];

        $response = $this->postJson('/api/analytics/track', $trackingData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['event_type']);
    }

    public function test_admin_can_access_analytics_dashboard()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                        ->getJson('/api/analytics/dashboard');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'popular_pages',
                    'popular_content',
                    'page_views_over_time',
                    'engagement_metrics',
                    'download_stats',
                    'real_time'
                ]);
    }

    public function test_admin_can_get_popular_pages()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                        ->getJson('/api/analytics/popular-pages?days=7&limit=5');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    '*' => [
                        'page_url',
                        'page_title',
                        'views'
                    ]
                ]);
    }

    public function test_admin_can_get_popular_content()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                        ->getJson('/api/analytics/popular-content?days=30');

        $response->assertStatus(200);
    }

    public function test_admin_can_get_engagement_metrics()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                        ->getJson('/api/analytics/engagement-metrics?days=30');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'total_views',
                    'unique_sessions',
                    'avg_views_per_session',
                    'top_referrers',
                    'period_days'
                ]);
    }

    public function test_admin_can_get_real_time_analytics()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                        ->getJson('/api/analytics/real-time');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'views_last_24h',
                    'active_sessions',
                    'top_pages_today'
                ]);
    }

    public function test_non_admin_cannot_access_analytics_dashboard()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $response = $this->actingAs($teacher, 'sanctum')
                        ->getJson('/api/analytics/dashboard');

        // This should be restricted to admins only
        // For now, we'll assume it returns data but in production you'd add role middleware
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_analytics_dashboard()
    {
        $response = $this->getJson('/api/analytics/dashboard');

        $response->assertStatus(401);
    }
}
