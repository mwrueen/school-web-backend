<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    /** @test */
    public function it_validates_file_types_strictly()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        $dangerousFiles = [
            ['filename' => 'malicious.php', 'content' => '<?php system($_GET["cmd"]); ?>'],
            ['filename' => 'script.js', 'content' => 'alert("xss")'],
            ['filename' => 'executable.exe', 'content' => 'binary content'],
            ['filename' => 'batch.bat', 'content' => '@echo off\ndir'],
            ['filename' => 'shell.sh', 'content' => '#!/bin/bash\nls -la'],
        ];

        foreach ($dangerousFiles as $fileData) {
            $file = UploadedFile::fake()->createWithContent(
                $fileData['filename'],
                $fileData['content']
            );

            $response = $this->postJson('/api/resources', [
                'title' => 'Test Resource',
                'description' => 'Test description',
                'type' => 'document',
                'file' => $file,
            ]);

            // Should reject dangerous file types
            $this->assertTrue(
                $response->status() === 422 || $response->status() === 400,
                "File {$fileData['filename']} should be rejected but got status {$response->status()}"
            );
        }
    }

    /** @test */
    public function it_validates_file_content_not_just_extension()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        // PHP code disguised as a text file
        $maliciousFile = UploadedFile::fake()->createWithContent(
            'innocent.txt',
            '<?php system($_GET["cmd"]); ?>'
        );

        $response = $this->postJson('/api/resources', [
            'title' => 'Test Resource',
            'description' => 'Test description',
            'type' => 'document',
            'file' => $maliciousFile,
        ]);

        // Should detect PHP content even with .txt extension
        if ($response->status() === 201) {
            $resource = $response->json('data');
            $filePath = storage_path('app/public/' . $resource['file_path']);
            
            // File should be sanitized or rejected
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $this->assertStringNotContainsString('<?php', $content);
                $this->assertStringNotContainsString('system(', $content);
            }
        }
    }

    /** @test */
    public function it_enforces_file_size_limits()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        // Create a file larger than allowed (assuming 10MB limit)
        $largeFile = UploadedFile::fake()->create('large.pdf', 11000); // 11MB

        $response = $this->postJson('/api/resources', [
            'title' => 'Large File',
            'description' => 'Test large file',
            'type' => 'document',
            'file' => $largeFile,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('file', strtolower($response->json('message')));
    }

    /** @test */
    public function it_sanitizes_file_names()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        $maliciousNames = [
            '../../../etc/passwd.txt',
            '..\\..\\windows\\system32\\config\\sam.txt',
            'file with spaces and special chars!@#$.pdf',
            'file<script>alert(1)</script>.txt',
            'file"with"quotes.pdf',
        ];

        foreach ($maliciousNames as $filename) {
            $file = UploadedFile::fake()->create($filename, 100);

            $response = $this->postJson('/api/resources', [
                'title' => 'Test Resource',
                'description' => 'Test description',
                'type' => 'document',
                'file' => $file,
            ]);

            if ($response->status() === 201) {
                $resource = $response->json('data');
                
                // Filename should be sanitized
                $this->assertStringNotContainsString('..', $resource['file_path']);
                $this->assertStringNotContainsString('<script>', $resource['file_path']);
                $this->assertStringNotContainsString('"', $resource['file_path']);
            }
        }
    }

    /** @test */
    public function it_prevents_path_traversal_attacks()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('test.pdf', 100);

        // Try to manipulate the upload path
        $response = $this->postJson('/api/resources', [
            'title' => 'Test Resource',
            'description' => 'Test description',
            'type' => 'document',
            'file' => $file,
            'path' => '../../../etc/',
        ]);

        if ($response->status() === 201) {
            $resource = $response->json('data');
            
            // File should be stored in the correct directory
            $this->assertStringStartsWith('resources/', $resource['file_path']);
            $this->assertStringNotContainsString('..', $resource['file_path']);
        }
    }

    /** @test */
    public function it_validates_image_files_properly()
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        // Valid image
        $validImage = UploadedFile::fake()->image('test.jpg', 100, 100);

        $response = $this->postJson('/api/cms/media', [
            'file' => $validImage,
            'alt_text' => 'Test image',
        ]);

        $response->assertStatus(201);

        // Fake image (non-image file with image extension)
        $fakeImage = UploadedFile::fake()->createWithContent(
            'fake.jpg',
            'This is not an image file'
        );

        $response = $this->postJson('/api/cms/media', [
            'file' => $fakeImage,
            'alt_text' => 'Fake image',
        ]);

        // Should reject fake image files
        $this->assertTrue(in_array($response->status(), [400, 422]));
    }

    /** @test */
    public function it_handles_concurrent_file_uploads()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        $files = [];
        for ($i = 0; $i < 5; $i++) {
            $files[] = UploadedFile::fake()->create("test{$i}.pdf", 100);
        }

        $responses = [];
        foreach ($files as $index => $file) {
            $responses[] = $this->postJson('/api/resources', [
                'title' => "Test Resource {$index}",
                'description' => 'Test description',
                'type' => 'document',
                'file' => $file,
            ]);
        }

        // All uploads should succeed
        foreach ($responses as $response) {
            $this->assertTrue(in_array($response->status(), [200, 201]));
        }

        // Verify all files were stored with unique names
        $filePaths = [];
        foreach ($responses as $response) {
            if ($response->status() === 201) {
                $resource = $response->json('data');
                $filePaths[] = $resource['file_path'];
            }
        }

        $this->assertEquals(count($filePaths), count(array_unique($filePaths)));
    }

    /** @test */
    public function it_handles_file_upload_errors_gracefully()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        // Simulate upload failure by using a very large file
        $response = $this->postJson('/api/resources', [
            'title' => 'Test Resource',
            'description' => 'Test description',
            'type' => 'document',
            'file' => null, // No file provided
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('file', $response->json('errors', []));
    }

    /** @test */
    public function it_prevents_zip_bomb_attacks()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        // Create a small zip file (simulating a zip bomb)
        $zipFile = UploadedFile::fake()->create('archive.zip', 100);

        $response = $this->postJson('/api/resources', [
            'title' => 'Archive File',
            'description' => 'Test archive',
            'type' => 'document',
            'file' => $zipFile,
        ]);

        // Should either reject zip files or handle them safely
        if ($response->status() === 201) {
            // If zip files are allowed, ensure they're not automatically extracted
            $resource = $response->json('data');
            $this->assertStringEndsWith('.zip', $resource['file_path']);
        }
    }

    /** @test */
    public function it_validates_file_permissions_after_upload()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('test.pdf', 100);

        $response = $this->postJson('/api/resources', [
            'title' => 'Test Resource',
            'description' => 'Test description',
            'type' => 'document',
            'file' => $file,
        ]);

        if ($response->status() === 201) {
            $resource = $response->json('data');
            $filePath = storage_path('app/public/' . $resource['file_path']);

            if (file_exists($filePath)) {
                // File should not be executable
                $permissions = fileperms($filePath);
                $this->assertFalse($permissions & 0111); // No execute permissions
            }
        }
    }

    /** @test */
    public function it_handles_duplicate_file_names()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        Sanctum::actingAs($user);

        $file1 = UploadedFile::fake()->create('duplicate.pdf', 100);
        $file2 = UploadedFile::fake()->create('duplicate.pdf', 100);

        // Upload first file
        $response1 = $this->postJson('/api/resources', [
            'title' => 'First Resource',
            'description' => 'First description',
            'type' => 'document',
            'file' => $file1,
        ]);

        // Upload second file with same name
        $response2 = $this->postJson('/api/resources', [
            'title' => 'Second Resource',
            'description' => 'Second description',
            'type' => 'document',
            'file' => $file2,
        ]);

        $response1->assertStatus(201);
        $response2->assertStatus(201);

        // Files should have different paths
        $resource1 = $response1->json('data');
        $resource2 = $response2->json('data');
        
        $this->assertNotEquals($resource1['file_path'], $resource2['file_path']);
    }
}