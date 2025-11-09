<?php

namespace Tests\Unit;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_can_be_created_with_required_fields()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $content = Content::create([
            'title' => 'Test Content',
            'content' => 'This is test content',
            'type' => 'page',
            'author_id' => $user->id
        ]);

        $this->assertDatabaseHas('contents', [
            'title' => 'Test Content',
            'content' => 'This is test content',
            'type' => 'page',
            'author_id' => $user->id
        ]);
    }

    public function test_slug_is_automatically_generated_from_title()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $content = Content::create([
            'title' => 'Test Content Title',
            'content' => 'This is test content',
            'type' => 'page',
            'author_id' => $user->id
        ]);

        $this->assertEquals('test-content-title', $content->slug);
    }

    public function test_content_has_author_relationship()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $content = Content::create([
            'title' => 'Test Content',
            'content' => 'This is test content',
            'type' => 'page',
            'author_id' => $user->id
        ]);

        $this->assertInstanceOf(User::class, $content->author);
        $this->assertEquals($user->id, $content->author->id);
    }

    public function test_content_can_create_versions()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $content = Content::create([
            'title' => 'Test Content',
            'content' => 'This is test content',
            'type' => 'page',
            'author_id' => $user->id
        ]);

        $version = $content->createVersion([
            'title' => 'Updated Title',
            'content' => 'Updated content',
            'change_summary' => 'Updated title and content'
        ], $user);

        $this->assertInstanceOf(ContentVersion::class, $version);
        $this->assertEquals(1, $version->version_number);
        $this->assertEquals('Updated Title', $version->title);
        $this->assertEquals('Updated content', $version->content);
    }

    public function test_content_can_publish_version()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $content = Content::create([
            'title' => 'Test Content',
            'content' => 'This is test content',
            'type' => 'page',
            'status' => 'draft',
            'author_id' => $user->id
        ]);

        $version = $content->createVersion([
            'title' => 'Published Title',
            'content' => 'Published content'
        ], $user);

        $content->publishVersion($version);

        $this->assertEquals('published', $content->fresh()->status);
        $this->assertEquals('Published Title', $content->fresh()->title);
        $this->assertTrue($version->fresh()->is_current);
    }

    public function test_published_scope_filters_correctly()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create published content
        $publishedContent = Content::create([
            'title' => 'Published Content',
            'content' => 'This is published',
            'type' => 'page',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'author_id' => $user->id
        ]);

        // Create draft content
        $draftContent = Content::create([
            'title' => 'Draft Content',
            'content' => 'This is draft',
            'type' => 'page',
            'status' => 'draft',
            'author_id' => $user->id
        ]);

        $publishedContents = Content::published()->get();

        $this->assertCount(1, $publishedContents);
        $this->assertEquals($publishedContent->id, $publishedContents->first()->id);
    }

    public function test_can_edit_permission_check()
    {
        $author = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create(['role' => 'teacher']);
        
        $content = Content::create([
            'title' => 'Test Content',
            'content' => 'This is test content',
            'type' => 'page',
            'author_id' => $author->id
        ]);

        $this->assertTrue($content->canEdit($author));
        $this->assertTrue($content->canEdit($admin));
        $this->assertFalse($content->canEdit($otherUser));
    }

    public function test_is_published_method()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Published content
        $publishedContent = Content::create([
            'title' => 'Published Content',
            'content' => 'This is published',
            'type' => 'page',
            'status' => 'published',
            'published_at' => now()->subHour(),
            'author_id' => $user->id
        ]);

        // Draft content
        $draftContent = Content::create([
            'title' => 'Draft Content',
            'content' => 'This is draft',
            'type' => 'page',
            'status' => 'draft',
            'author_id' => $user->id
        ]);

        // Future published content
        $futureContent = Content::create([
            'title' => 'Future Content',
            'content' => 'This is future',
            'type' => 'page',
            'status' => 'published',
            'published_at' => now()->addHour(),
            'author_id' => $user->id
        ]);

        $this->assertTrue($publishedContent->isPublished());
        $this->assertFalse($draftContent->isPublished());
        $this->assertFalse($futureContent->isPublished());
    }
}
