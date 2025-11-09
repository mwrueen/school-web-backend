<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authenticated_user_can_list_content()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        Content::factory()->count(3)->create(['author_id' => $user->id]);

        $response = $this->getJson('/api/cms/content');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'title',
                                'slug',
                                'type',
                                'status',
                                'author',
                                'created_at'
                            ]
                        ]
                    ]
                ]);
    }

    public function test_unauthenticated_user_cannot_access_cms()
    {
        $response = $this->getJson('/api/cms/content');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_create_content()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $contentData = [
            'title' => 'Test Content',
            'content' => 'This is test content for the CMS',
            'type' => 'page',
            'status' => 'draft'
        ];

        $response = $this->postJson('/api/cms/content', $contentData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Content created successfully'
                ]);

        $this->assertDatabaseHas('contents', [
            'title' => 'Test Content',
            'content' => 'This is test content for the CMS',
            'type' => 'page',
            'status' => 'draft',
            'author_id' => $user->id
        ]);
    }

    public function test_content_creation_validates_required_fields()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cms/content', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'content', 'type']);
    }

    public function test_authenticated_user_can_update_own_content()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        $content = Content::create([
            'title' => 'Original Title',
            'content' => 'Original content',
            'type' => 'page',
            'author_id' => $user->id
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content',
            'type' => 'page',
            'status' => 'published'
        ];

        $response = $this->putJson("/api/cms/content/{$content->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Content updated successfully'
                ]);

        $this->assertDatabaseHas('contents', [
            'id' => $content->id,
            'title' => 'Updated Title',
            'content' => 'Updated content'
        ]);
    }

    public function test_user_cannot_update_others_content_unless_admin()
    {
        $author = User::factory()->create(['role' => 'teacher']);
        $otherUser = User::factory()->create(['role' => 'teacher']);
        
        $content = Content::create([
            'title' => 'Original Title',
            'content' => 'Original content',
            'type' => 'page',
            'author_id' => $author->id
        ]);

        Sanctum::actingAs($otherUser);

        $response = $this->putJson("/api/cms/content/{$content->id}", [
            'title' => 'Hacked Title',
            'content' => 'Hacked content',
            'type' => 'page'
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_any_content()
    {
        $author = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'admin']);
        
        $content = Content::create([
            'title' => 'Original Title',
            'content' => 'Original content',
            'type' => 'page',
            'author_id' => $author->id
        ]);

        Sanctum::actingAs($admin);

        $response = $this->putJson("/api/cms/content/{$content->id}", [
            'title' => 'Admin Updated Title',
            'content' => 'Admin updated content',
            'type' => 'page'
        ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('contents', [
            'id' => $content->id,
            'title' => 'Admin Updated Title'
        ]);
    }

    public function test_authenticated_user_can_delete_own_content()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        $content = Content::create([
            'title' => 'Test Content',
            'content' => 'Test content',
            'type' => 'page',
            'author_id' => $user->id
        ]);

        $response = $this->deleteJson("/api/cms/content/{$content->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Content deleted successfully'
                ]);

        $this->assertDatabaseMissing('contents', ['id' => $content->id]);
    }

    public function test_can_filter_content_by_type()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        Content::create([
            'title' => 'Page Content',
            'content' => 'Page content',
            'type' => 'page',
            'author_id' => $user->id
        ]);

        Content::create([
            'title' => 'Post Content',
            'content' => 'Post content',
            'type' => 'post',
            'author_id' => $user->id
        ]);

        $response = $this->getJson('/api/cms/content?type=page');

        $response->assertStatus(200);
        
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('page', $data[0]['type']);
    }

    public function test_can_search_content()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        Content::create([
            'title' => 'Laravel Tutorial',
            'content' => 'Learn Laravel framework',
            'type' => 'post',
            'author_id' => $user->id
        ]);

        Content::create([
            'title' => 'PHP Basics',
            'content' => 'Learn PHP programming',
            'type' => 'post',
            'author_id' => $user->id
        ]);

        $response = $this->getJson('/api/cms/content?search=Laravel');

        $response->assertStatus(200);
        
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('Laravel', $data[0]['title']);
    }

    public function test_can_get_content_versions()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $content = Content::create([
            'title' => 'Test Content',
            'content' => 'Original content',
            'type' => 'page',
            'author_id' => $user->id
        ]);

        // Create a version
        $content->createVersion([
            'title' => 'Updated Title',
            'content' => 'Updated content',
            'change_summary' => 'Updated title and content'
        ], $user);

        $response = $this->getJson("/api/cms/content/{$content->id}/versions");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'version_number',
                            'title',
                            'content',
                            'change_summary',
                            'created_by'
                        ]
                    ]
                ]);
    }

    public function test_can_publish_specific_version()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $content = Content::create([
            'title' => 'Test Content',
            'content' => 'Original content',
            'type' => 'page',
            'status' => 'draft',
            'author_id' => $user->id
        ]);

        $version = $content->createVersion([
            'title' => 'Published Title',
            'content' => 'Published content'
        ], $user);

        $response = $this->postJson("/api/cms/content/{$content->id}/publish-version", [
            'version_id' => $version->id
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Version published successfully'
                ]);

        $this->assertEquals('published', $content->fresh()->status);
        $this->assertTrue($version->fresh()->is_current);
    }

    public function test_can_perform_bulk_actions()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $content1 = Content::create([
            'title' => 'Content 1',
            'content' => 'Content 1',
            'type' => 'page',
            'status' => 'draft',
            'author_id' => $user->id
        ]);

        $content2 = Content::create([
            'title' => 'Content 2',
            'content' => 'Content 2',
            'type' => 'page',
            'status' => 'draft',
            'author_id' => $user->id
        ]);

        $response = $this->postJson('/api/cms/content/bulk-action', [
            'action' => 'publish',
            'content_ids' => [$content1->id, $content2->id]
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);

        $this->assertEquals('published', $content1->fresh()->status);
        $this->assertEquals('published', $content2->fresh()->status);
    }
}
