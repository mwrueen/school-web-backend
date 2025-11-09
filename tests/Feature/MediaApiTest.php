<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    public function test_authenticated_user_can_list_media_files()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        // Create some fake files
        Storage::disk('public')->put('media/test1.jpg', 'fake content');
        Storage::disk('public')->put('media/test2.pdf', 'fake content');

        $response = $this->getJson('/api/cms/media');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'filename',
                            'path',
                            'url',
                            'size',
                            'type',
                            'mime_type'
                        ]
                    ]
                ]);
    }

    public function test_unauthenticated_user_cannot_access_media()
    {
        $response = $this->getJson('/api/cms/media');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_upload_file()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('test.jpg', 100, 100);

        $response = $this->postJson('/api/cms/media', [
            'file' => $file,
            'alt_text' => 'Test image',
            'caption' => 'This is a test image'
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'File uploaded successfully'
                ]);

        // Check if file was stored
        $this->assertTrue(Storage::disk('public')->exists('media/' . $response->json('data.filename')));
    }

    public function test_file_upload_validates_file_type()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('test.exe', 100);

        $response = $this->postJson('/api/cms/media', [
            'file' => $file
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'File type not allowed'
                ]);
    }

    public function test_file_upload_validates_file_size()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        // Create a file larger than 10MB
        $file = UploadedFile::fake()->create('large.pdf', 11000);

        $response = $this->postJson('/api/cms/media', [
            'file' => $file
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
    }

    public function test_authenticated_user_can_delete_file()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        // Create a fake file
        Storage::disk('public')->put('media/test.jpg', 'fake content');

        $response = $this->deleteJson('/api/cms/media/test.jpg');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'File deleted successfully'
                ]);

        // Check if file was deleted
        $this->assertFalse(Storage::disk('public')->exists('media/test.jpg'));
    }

    public function test_delete_nonexistent_file_returns_404()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/cms/media/nonexistent.jpg');

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'File not found'
                ]);
    }

    public function test_authenticated_user_can_create_directory()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/cms/media/directories', [
            'name' => 'New Folder',
            'parent' => 'media'
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Directory created successfully'
                ]);

        // Check if directory was created
        $this->assertTrue(Storage::disk('public')->exists('media/new-folder'));
    }

    public function test_can_list_directories()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        // Create some directories
        Storage::disk('public')->makeDirectory('media/folder1');
        Storage::disk('public')->makeDirectory('media/folder2');

        $response = $this->getJson('/api/cms/media-directories?parent=media');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'name',
                            'path'
                        ]
                    ]
                ]);
    }

    public function test_can_bulk_delete_files()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        // Create some fake files
        Storage::disk('public')->put('media/test1.jpg', 'fake content');
        Storage::disk('public')->put('media/test2.jpg', 'fake content');

        $response = $this->deleteJson('/api/cms/media/bulk-delete', [
            'files' => ['test1.jpg', 'test2.jpg']
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ]);

        // Check if files were deleted
        $this->assertFalse(Storage::disk('public')->exists('media/test1.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('media/test2.jpg'));
    }

    public function test_can_filter_media_by_type()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        // Create files of different types
        Storage::disk('public')->put('media/image.jpg', 'fake image content');
        Storage::disk('public')->put('media/document.pdf', 'fake pdf content');

        $response = $this->getJson('/api/cms/media?type=image');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('image', $data[0]['type']);
    }

    public function test_get_file_info()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        // Create a fake file
        Storage::disk('public')->put('media/test.jpg', 'fake content');

        $response = $this->getJson('/api/cms/media/test.jpg');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'filename',
                        'path',
                        'url',
                        'size',
                        'type',
                        'mime_type'
                    ]
                ]);
    }
}
