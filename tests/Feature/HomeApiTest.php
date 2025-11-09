<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class HomeApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_home_page_data_can_be_retrieved_without_authentication(): void
    {
        // Create test data
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create news announcements
        Announcement::factory()->create([
            'title' => 'Latest News',
            'type' => 'news',
            'is_public' => true,
            'published_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        // Create notice announcements
        Announcement::factory()->create([
            'title' => 'Important Notice',
            'type' => 'notice',
            'is_public' => true,
            'published_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        // Create achievement announcements
        Announcement::factory()->create([
            'title' => 'Student Achievement',
            'type' => 'achievement',
            'is_public' => true,
            'published_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        // Create upcoming events
        Event::factory()->create([
            'title' => 'Upcoming Event',
            'event_date' => now()->addWeek(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/home');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'latest_news',
                        'latest_announcements',
                        'featured_events',
                        'achievements',
                        'quick_access_links',
                        'last_updated'
                    ]
                ])
                ->assertJsonPath('success', true)
                ->assertJsonCount(1, 'data.latest_news')
                ->assertJsonCount(1, 'data.latest_announcements')
                ->assertJsonCount(1, 'data.featured_events')
                ->assertJsonCount(1, 'data.achievements')
                ->assertJsonPath('data.latest_news.0.title', 'Latest News')
                ->assertJsonPath('data.latest_announcements.0.title', 'Important Notice')
                ->assertJsonPath('data.achievements.0.title', 'Student Achievement')
                ->assertJsonPath('data.featured_events.0.title', 'Upcoming Event');
    }

    public function test_home_page_data_excludes_private_content(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create private announcements (should not appear)
        Announcement::factory()->create([
            'title' => 'Private News',
            'type' => 'news',
            'is_public' => false,
            'published_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        // Create private events (should not appear)
        Event::factory()->create([
            'title' => 'Private Event',
            'event_date' => now()->addWeek(),
            'is_public' => false,
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/home');

        $response->assertStatus(200)
                ->assertJsonCount(0, 'data.latest_news')
                ->assertJsonCount(0, 'data.featured_events');
    }

    public function test_home_page_data_excludes_unpublished_announcements(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create unpublished announcement (should not appear)
        Announcement::factory()->create([
            'title' => 'Unpublished News',
            'type' => 'news',
            'is_public' => true,
            'published_at' => null,
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/home');

        $response->assertStatus(200)
                ->assertJsonCount(0, 'data.latest_news');
    }

    public function test_home_page_data_excludes_past_events(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create past event (should not appear in featured events)
        Event::factory()->create([
            'title' => 'Past Event',
            'event_date' => now()->subWeek(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/home');

        $response->assertStatus(200)
                ->assertJsonCount(0, 'data.featured_events');
    }

    public function test_latest_news_endpoint_returns_news_only(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create news
        Announcement::factory()->create([
            'title' => 'News Item',
            'type' => 'news',
            'is_public' => true,
            'published_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        // Create notice (should not appear)
        Announcement::factory()->create([
            'title' => 'Notice Item',
            'type' => 'notice',
            'is_public' => true,
            'published_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/home/news');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ])
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.title', 'News Item')
                ->assertJsonPath('data.0.type', 'news');
    }

    public function test_latest_announcements_endpoint_returns_notices_only(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create notice
        Announcement::factory()->create([
            'title' => 'Notice Item',
            'type' => 'notice',
            'is_public' => true,
            'published_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        // Create news (should not appear)
        Announcement::factory()->create([
            'title' => 'News Item',
            'type' => 'news',
            'is_public' => true,
            'published_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/home/announcements');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ])
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.title', 'Notice Item')
                ->assertJsonPath('data.0.type', 'notice');
    }

    public function test_featured_events_endpoint_returns_upcoming_events_only(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create upcoming event
        Event::factory()->create([
            'title' => 'Upcoming Event',
            'event_date' => now()->addWeek(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        // Create past event (should not appear)
        Event::factory()->create([
            'title' => 'Past Event',
            'event_date' => now()->subWeek(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/home/featured-events');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ])
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.title', 'Upcoming Event');
    }

    public function test_achievements_endpoint_returns_achievements_only(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create achievement
        Announcement::factory()->create([
            'title' => 'Achievement Item',
            'type' => 'achievement',
            'is_public' => true,
            'published_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        // Create news (should not appear)
        Announcement::factory()->create([
            'title' => 'News Item',
            'type' => 'news',
            'is_public' => true,
            'published_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/home/achievements');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ])
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.title', 'Achievement Item')
                ->assertJsonPath('data.0.type', 'achievement');
    }

    public function test_quick_access_links_endpoint_returns_configured_links(): void
    {
        $response = $this->getJson('/api/public/home/quick-access-links');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'title',
                            'description',
                            'url',
                            'icon',
                            'external'
                        ]
                    ]
                ])
                ->assertJsonPath('success', true);

        $links = $response->json('data');
        $this->assertGreaterThan(0, count($links));
        
        // Check that Parent Portal link exists
        $parentPortalLink = collect($links)->firstWhere('title', 'Parent Portal');
        $this->assertNotNull($parentPortalLink);
        $this->assertEquals('/parent-portal', $parentPortalLink['url']);
    }

    public function test_home_endpoints_respect_limit_parameter(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create multiple news items
        for ($i = 0; $i < 10; $i++) {
            Announcement::factory()->create([
                'title' => "News Item $i",
                'type' => 'news',
                'is_public' => true,
                'published_at' => now()->subDays($i),
                'created_by' => $user->id,
            ]);
        }

        $response = $this->getJson('/api/public/home/news?limit=3');

        $response->assertStatus(200)
                ->assertJsonCount(3, 'data');
    }

    public function test_home_data_is_ordered_correctly(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create news items with different dates
        $oldNews = Announcement::factory()->create([
            'title' => 'Old News',
            'type' => 'news',
            'is_public' => true,
            'published_at' => now()->subWeek(),
            'created_by' => $user->id,
        ]);

        $newNews = Announcement::factory()->create([
            'title' => 'New News',
            'type' => 'news',
            'is_public' => true,
            'published_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/home/news');

        $response->assertStatus(200)
                ->assertJsonPath('data.0.title', 'New News')
                ->assertJsonPath('data.1.title', 'Old News');
    }
}