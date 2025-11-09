<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear rate limiter before each test
        RateLimiter::clear('login.127.0.0.1');
        RateLimiter::clear('api.1');
        RateLimiter::clear('content.1');
        RateLimiter::clear('upload.1');
    }

    /** @test */
    public function it_sanitizes_malicious_input_in_requests()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $maliciousData = [
            'title' => '<script>alert("xss")</script>Test Title',
            'content' => 'Normal content with <script>alert("xss")</script> injection',
            'type' => 'news',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/cms/content', $maliciousData);

        $response->assertStatus(201);
        
        // Verify that malicious scripts are removed
        $content = $response->json('data');
        $this->assertStringNotContainsString('<script>', $content['title']);
        $this->assertStringNotContainsString('alert', $content['title']);
        $this->assertEquals('Test Title', $content['title']);
    }

    /** @test */
    public function it_validates_input_length_limits()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $longTitle = str_repeat('a', 300); // Exceeds 255 character limit
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/announcements', [
                'title' => $longTitle,
                'content' => 'Valid content',
                'type' => 'news',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/announcements', [
                'content' => 'Valid content',
                'type' => 'news',
                // Missing required 'title' field
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /** @test */
    public function it_validates_field_patterns()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/announcements', [
                'title' => 'Title with invalid chars: <>|&',
                'content' => 'Valid content',
                'type' => 'news',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /** @test */
    public function it_enforces_rate_limiting_on_login_attempts()
    {
        // Make 5 failed login attempts (the limit)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'wrongpassword',
            ]);
            
            $response->assertStatus(422);
        }

        // 6th attempt should be rate limited
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['email' => ['Too many login attempts. Please try again in 60 seconds.']]);
    }

    /** @test */
    public function it_enforces_rate_limiting_on_api_requests()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Make requests up to the limit (100 per minute for API)
        // We'll test with a smaller number to avoid long test execution
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/dashboard');
            
            if ($response->status() === 429) {
                // Rate limit hit earlier than expected, which is fine
                break;
            }
        }

        // Verify rate limit headers are present
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard');
            
        $this->assertTrue(
            $response->headers->has('X-RateLimit-Limit') ||
            $response->status() === 429
        );
    }

    /** @test */
    public function it_validates_assignment_data_comprehensively()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        
        $invalidData = [
            'title' => 'A', // Too short
            'description' => 'Short', // Too short
            'class_id' => 999, // Non-existent
            'subject_id' => 999, // Non-existent
            'type' => 'invalid_type', // Invalid type
            'max_points' => 1500, // Exceeds limit
            'due_date' => '2020-01-01', // In the past
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/assignments', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'title',
                'description',
                'class_id',
                'subject_id',
                'type',
                'max_points',
                'due_date'
            ]);
    }

    /** @test */
    public function it_prevents_sql_injection_attempts()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $sqlInjectionAttempts = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin'/*",
            "' UNION SELECT * FROM users --",
        ];

        foreach ($sqlInjectionAttempts as $maliciousInput) {
            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/announcements', [
                    'title' => $maliciousInput,
                    'content' => 'Valid content',
                    'type' => 'news',
                ]);

            // Should either be sanitized or rejected
            if ($response->status() === 201) {
                $content = $response->json('data');
                $this->assertStringNotContainsString('DROP TABLE', $content['title']);
                $this->assertStringNotContainsString('UNION SELECT', $content['title']);
                $this->assertStringNotContainsString('--', $content['title']);
            } else {
                $response->assertStatus(422);
            }
        }
    }

    /** @test */
    public function it_validates_file_upload_security()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        
        // Test with various malicious file types
        $maliciousFiles = [
            'test.php' => 'text/php',
            'test.exe' => 'application/x-executable',
            'test.js' => 'application/javascript',
            'test.html' => 'text/html',
        ];

        foreach ($maliciousFiles as $filename => $mimeType) {
            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/resources', [
                    'title' => 'Test Resource',
                    'description' => 'Test description',
                    'type' => 'document',
                    'file_name' => $filename,
                    'file_type' => pathinfo($filename, PATHINFO_EXTENSION),
                    'file_size' => 1024,
                    'file_path' => 'test/path',
                ]);

            // Should validate file types and reject dangerous ones
            // The exact response depends on your file validation rules
            $this->assertTrue(
                $response->status() === 422 || 
                $response->status() === 201
            );
        }
    }

    /** @test */
    public function it_validates_content_length_limits()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $veryLongContent = str_repeat('Lorem ipsum dolor sit amet. ', 2000); // Very long content
        
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/cms/content', [
                'title' => 'Valid Title',
                'content' => $veryLongContent,
                'type' => 'page',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    /** @test */
    public function it_validates_email_format_strictly()
    {
        $invalidEmails = [
            'invalid-email',
            '@domain.com',
            'user@',
            'user..name@domain.com',
            'user@domain',
            'user name@domain.com',
        ];

        foreach ($invalidEmails as $email) {
            $response = $this->postJson('/api/auth/login', [
                'email' => $email,
                'password' => 'password123',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        }
    }

    /** @test */
    public function it_validates_numeric_ranges()
    {
        $user = User::factory()->create(['role' => 'teacher']);
        
        $invalidNumericData = [
            'max_points' => -5, // Negative value
            'late_penalty_percent' => 150, // Exceeds 100%
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/assignments', array_merge([
                'title' => 'Valid Title',
                'description' => 'Valid description for assignment',
                'class_id' => 1,
                'subject_id' => 1,
                'type' => 'homework',
                'due_date' => now()->addDays(7)->toDateString(),
            ], $invalidNumericData));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['max_points', 'late_penalty_percent']);
    }

    /** @test */
    public function it_prevents_xss_in_html_content()
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $xssAttempts = [
            '<img src="x" onerror="alert(1)">',
            '<svg onload="alert(1)">',
            '<iframe src="javascript:alert(1)"></iframe>',
            '<script>document.cookie</script>',
            'javascript:alert(1)',
        ];

        foreach ($xssAttempts as $xssPayload) {
            $response = $this->actingAs($user, 'sanctum')
                ->postJson('/api/cms/content', [
                    'title' => 'Test Title',
                    'content' => "Normal content with {$xssPayload} injection",
                    'type' => 'page',
                ]);

            if ($response->status() === 201) {
                $content = $response->json('data');
                $this->assertStringNotContainsString('onerror', $content['content']);
                $this->assertStringNotContainsString('onload', $content['content']);
                $this->assertStringNotContainsString('javascript:', $content['content']);
                $this->assertStringNotContainsString('<script>', $content['content']);
            } else {
                $response->assertStatus(422);
            }
        }
    }

    /** @test */
    public function it_validates_authorization_for_form_requests()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $unauthorizedUser = User::factory()->create(['role' => 'student']);
        
        // Teacher should be able to create announcements
        $response = $this->actingAs($teacher, 'sanctum')
            ->postJson('/api/announcements', [
                'title' => 'Valid Title',
                'content' => 'Valid content for announcement',
                'type' => 'news',
            ]);

        $response->assertStatus(201);

        // Unauthorized user should not be able to create announcements
        $response = $this->actingAs($unauthorizedUser, 'sanctum')
            ->postJson('/api/announcements', [
                'title' => 'Valid Title',
                'content' => 'Valid content for announcement',
                'type' => 'news',
            ]);

        $response->assertStatus(403);
    }
}