<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_public_announcements_can_be_retrieved_without_authentication(): void
    {
        // Create public announcements
        Announcement::factory()->create([
            'title' => 'Public News',
            'type' => 'news',
            'is_public' => true,
            'published_at' => now()->subDay(),
        ]);

        // Create private announcement (should not appear)
        Announcement::factory()->create([
            'title' => 'Private Notice',
            'type' => 'notice',
            'is_public' => false,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/public/announcements');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'title',
                                'content',
                                'type',
                                'is_public',
                                'published_at',
                                'created_at',
                                'updated_at'
                            ]
                        ]
                    ]
                ])
                ->assertJsonPath('data.data.0.title', 'Public News')
                ->assertJsonCount(1, 'data.data');
    }

    public function test_public_announcements_can_be_filtered_by_type(): void
    {
        Announcement::factory()->create([
            'type' => 'news',
            'is_public' => true,
            'published_at' => now()->subDay(),
        ]);

        Announcement::factory()->create([
            'type' => 'notice',
            'is_public' => true,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/public/announcements?type=news');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data.data')
                ->assertJsonPath('data.data.0.type', 'news');
    }

    public function test_authenticated_user_can_create_announcement(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $announcementData = [
            'title' => 'Test Announcement',
            'content' => 'This is a test announcement content.',
            'type' => 'news',
            'is_public' => true,
        ];

        $response = $this->postJson('/api/announcements', $announcementData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'title',
                        'content',
                        'type',
                        'is_public',
                        'created_by',
                        'creator'
                    ]
                ])
                ->assertJsonPath('data.title', 'Test Announcement')
                ->assertJsonPath('data.created_by', $user->id);

        $this->assertDatabaseHas('announcements', [
            'title' => 'Test Announcement',
            'created_by' => $user->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_create_announcement(): void
    {
        $announcementData = [
            'title' => 'Test Announcement',
            'content' => 'This is a test announcement content.',
            'type' => 'news',
            'is_public' => true,
        ];

        $response = $this->postJson('/api/announcements', $announcementData);

        $response->assertStatus(401);
    }

    public function test_announcement_creation_validates_required_fields(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/announcements', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'content', 'type']);
    }

    public function test_announcement_creation_validates_type_field(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/announcements', [
            'title' => 'Test',
            'content' => 'Content',
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['type']);
    }

    public function test_authenticated_user_can_update_announcement(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $announcement = Announcement::factory()->create(['created_by' => $user->id]);
        
        Sanctum::actingAs($user);

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ];

        $response = $this->putJson("/api/announcements/{$announcement->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonPath('data.title', 'Updated Title')
                ->assertJsonPath('data.content', 'Updated content');

        $this->assertDatabaseHas('announcements', [
            'id' => $announcement->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_authenticated_user_can_delete_announcement(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $announcement = Announcement::factory()->create(['created_by' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/announcements/{$announcement->id}");

        $response->assertStatus(200)
                ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('announcements', [
            'id' => $announcement->id,
        ]);
    }

    public function test_authenticated_user_can_publish_announcement(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $announcement = Announcement::factory()->create([
            'created_by' => $user->id,
            'published_at' => null,
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/announcements/{$announcement->id}/publish");

        $response->assertStatus(200)
                ->assertJsonPath('success', true);

        $announcement->refresh();
        $this->assertNotNull($announcement->published_at);
    }

    public function test_authenticated_user_can_unpublish_announcement(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $announcement = Announcement::factory()->create([
            'created_by' => $user->id,
            'published_at' => now(),
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/announcements/{$announcement->id}/unpublish");

        $response->assertStatus(200)
                ->assertJsonPath('success', true);

        $announcement->refresh();
        $this->assertNull($announcement->published_at);
    }

    public function test_can_retrieve_announcement_types(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/announcement-types');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ])
                ->assertJsonPath('success', true);

        $types = $response->json('data');
        $this->assertContains('news', $types);
        $this->assertContains('notice', $types);
        $this->assertContains('result', $types);
    }

    public function test_public_announcement_show_returns_public_announcement(): void
    {
        $announcement = Announcement::factory()->create([
            'title' => 'Public Announcement',
            'is_public' => true,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/public/announcements/{$announcement->id}");

        $response->assertStatus(200)
                ->assertJsonPath('data.title', 'Public Announcement');
    }

    public function test_public_announcement_show_hides_private_announcement(): void
    {
        $announcement = Announcement::factory()->create([
            'is_public' => false,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->getJson("/api/public/announcements/{$announcement->id}");

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_view_all_announcements(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create both public and private announcements
        Announcement::factory()->create(['is_public' => true, 'published_at' => now()]);
        Announcement::factory()->create(['is_public' => false, 'published_at' => now()]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/announcements');

        $response->assertStatus(200)
                ->assertJsonCount(2, 'data.data');
    }
}
