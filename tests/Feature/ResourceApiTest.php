<?php

namespace Tests\Feature;

use App\Models\Resource;
use App\Models\User;
use App\Models\Subject;
use App\Models\Classes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResourceApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        
        // Set up storage for testing
        Storage::fake('private');
    }

    public function test_public_resources_can_be_retrieved_without_authentication(): void
    {
        // Create public resource
        $resource = Resource::factory()->create([
            'title' => 'Public Resource',
            'is_public' => true,
        ]);

        // Create private resource (should not appear)
        Resource::factory()->create([
            'title' => 'Private Resource',
            'is_public' => false,
        ]);

        $response = $this->getJson('/api/public/resources');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'title',
                                'description',
                                'file_name',
                                'file_type',
                                'resource_type',
                                'is_public',
                                'created_at',
                                'updated_at'
                            ]
                        ]
                    ]
                ])
                ->assertJsonPath('data.data.0.title', 'Public Resource')
                ->assertJsonCount(1, 'data.data');
    }

    public function test_authenticated_user_can_upload_resource(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $subject = Subject::factory()->create();
        $class = Classes::factory()->create();
        
        Sanctum::actingAs($user);

        // Create a fake file
        $file = UploadedFile::fake()->create('test-document.pdf', 1024, 'application/pdf');

        $resourceData = [
            'title' => 'Test Resource',
            'description' => 'This is a test resource.',
            'file' => $file,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'is_public' => true,
        ];

        $response = $this->postJson('/api/resources', $resourceData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'title',
                        'description',
                        'file_name',
                        'file_path',
                        'file_type',
                        'mime_type',
                        'file_size',
                        'resource_type',
                        'teacher_id',
                        'subject',
                        'class',
                        'teacher'
                    ]
                ])
                ->assertJsonPath('data.title', 'Test Resource')
                ->assertJsonPath('data.teacher_id', $user->id)
                ->assertJsonPath('data.resource_type', 'document');

        $this->assertDatabaseHas('resources', [
            'title' => 'Test Resource',
            'teacher_id' => $user->id,
        ]);

        // Verify file was stored
        $resource = Resource::where('title', 'Test Resource')->first();
        Storage::disk('private')->assertExists($resource->file_path);
    }

    public function test_unauthenticated_user_cannot_upload_resource(): void
    {
        $file = UploadedFile::fake()->create('test-document.pdf', 1024, 'application/pdf');

        $resourceData = [
            'title' => 'Test Resource',
            'file' => $file,
        ];

        $response = $this->postJson('/api/resources', $resourceData);

        $response->assertStatus(401);
    }

    public function test_resource_upload_validates_required_fields(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/resources', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'file']);
    }

    public function test_resource_upload_validates_file_type(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        // Create a file with disallowed type
        $file = UploadedFile::fake()->create('malicious.exe', 1024, 'application/x-executable');

        $response = $this->postJson('/api/resources', [
            'title' => 'Test Resource',
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_resource_upload_validates_file_size(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        // Create a file larger than allowed (simulate 100MB file)
        $file = UploadedFile::fake()->create('large-file.pdf', 100 * 1024, 'application/pdf');

        $response = $this->postJson('/api/resources', [
            'title' => 'Test Resource',
            'file' => $file,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['file']);
    }

    public function test_authenticated_user_can_update_resource(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $resource = Resource::factory()->create(['teacher_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $updateData = [
            'title' => 'Updated Resource Title',
            'description' => 'Updated description',
        ];

        $response = $this->putJson("/api/resources/{$resource->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonPath('data.title', 'Updated Resource Title')
                ->assertJsonPath('data.description', 'Updated description');

        $this->assertDatabaseHas('resources', [
            'id' => $resource->id,
            'title' => 'Updated Resource Title',
        ]);
    }

    public function test_authenticated_user_can_delete_resource(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        $resource = Resource::factory()->create(['teacher_id' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/resources/{$resource->id}");

        $response->assertStatus(200)
                ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('resources', [
            'id' => $resource->id,
        ]);
    }

    public function test_can_retrieve_resource_types(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/resource-types');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ])
                ->assertJsonPath('success', true);

        $types = $response->json('data');
        $this->assertContains('document', $types);
        $this->assertContains('image', $types);
        $this->assertContains('video', $types);
    }

    public function test_can_retrieve_upload_limits(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/upload-limits');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'allowed_mime_types',
                        'max_file_size',
                        'max_file_size_human'
                    ]
                ])
                ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertIsArray($data['allowed_mime_types']);
        $this->assertIsInt($data['max_file_size']);
        $this->assertIsString($data['max_file_size_human']);
    }

    public function test_public_resource_show_returns_public_resource(): void
    {
        $resource = Resource::factory()->create([
            'title' => 'Public Resource',
            'is_public' => true,
        ]);

        $response = $this->getJson("/api/public/resources/{$resource->id}");

        $response->assertStatus(200)
                ->assertJsonPath('data.title', 'Public Resource');
    }

    public function test_public_resource_show_hides_private_resource(): void
    {
        $resource = Resource::factory()->create([
            'is_public' => false,
        ]);

        $response = $this->getJson("/api/public/resources/{$resource->id}");

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_view_all_resources(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        
        // Create both public and private resources
        Resource::factory()->create(['is_public' => true]);
        Resource::factory()->create(['is_public' => false]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/resources');

        $response->assertStatus(200)
                ->assertJsonCount(2, 'data.data');
    }

    public function test_resources_can_be_filtered_by_type(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        
        Resource::factory()->create(['resource_type' => 'document']);
        Resource::factory()->create(['resource_type' => 'image']);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/resources?resource_type=document');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data.data')
                ->assertJsonPath('data.data.0.resource_type', 'document');
    }

    public function test_resources_can_be_searched(): void
    {
        $user = User::factory()->create(['role' => 'teacher']);
        
        Resource::factory()->create(['title' => 'Mathematics Worksheet']);
        Resource::factory()->create(['title' => 'Science Lab Report']);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/resources?search=Math');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data.data')
                ->assertJsonPath('data.data.0.title', 'Mathematics Worksheet');
    }

    public function test_resource_download_works_for_public_resource(): void
    {
        // Create a fake file and resource
        $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');
        $filePath = $file->store('resources', 'private');
        
        $resource = Resource::factory()->create([
            'is_public' => true,
            'file_path' => $filePath,
            'file_name' => 'test.pdf',
        ]);

        $response = $this->get("/api/resources/{$resource->id}/download");

        $response->assertStatus(200);
        $response->assertHeader('content-disposition', 'attachment; filename=test.pdf');
    }

    public function test_resource_download_requires_auth_for_private_resource(): void
    {
        $resource = Resource::factory()->create([
            'is_public' => false,
            'file_name' => 'private.pdf',
        ]);

        $response = $this->get("/api/resources/{$resource->id}/download");

        $response->assertStatus(404);
    }
}
