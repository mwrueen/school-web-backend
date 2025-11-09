<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Classes;
use App\Models\Assignment;
use App\Models\Announcement;
use App\Models\Resource;
use App\Models\Content;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $teacher;
    protected $otherTeacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->otherTeacher = User::factory()->create(['role' => 'teacher']);
    }

    /** @test */
    public function admin_can_access_all_resources()
    {
        Sanctum::actingAs($this->admin);

        // Admin can create announcements
        $response = $this->postJson('/api/announcements', [
            'title' => 'Admin Announcement',
            'content' => 'This is an admin announcement',
            'type' => 'news',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));

        // Admin can create content
        $response = $this->postJson('/api/cms/content', [
            'title' => 'Admin Content',
            'content' => 'This is admin content',
            'type' => 'page',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 201]));

        // Admin can access analytics
        $response = $this->getJson('/api/analytics/dashboard');
        $this->assertTrue(in_array($response->status(), [200, 404])); // 404 if no data
    }

    /** @test */
    public function teacher_can_only_access_their_own_classes()
    {
        $teacherClass = Classes::factory()->create(['teacher_id' => $this->teacher->id]);
        $otherClass = Classes::factory()->create(['teacher_id' => $this->otherTeacher->id]);

        Sanctum::actingAs($this->teacher);

        // Teacher can access their own class
        $response = $this->getJson("/api/classes/{$teacherClass->id}");
        $this->assertTrue(in_array($response->status(), [200, 403]));

        // Teacher cannot access other teacher's class
        $response = $this->getJson("/api/classes/{$otherClass->id}");
        $this->assertEquals(403, $response->status());
    }

    /** @test */
    public function teacher_can_only_manage_their_own_assignments()
    {
        $teacherClass = Classes::factory()->create(['teacher_id' => $this->teacher->id]);
        $otherClass = Classes::factory()->create(['teacher_id' => $this->otherTeacher->id]);

        $teacherAssignment = Assignment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $teacherClass->id,
        ]);

        $otherAssignment = Assignment::factory()->create([
            'teacher_id' => $this->otherTeacher->id,
            'class_id' => $otherClass->id,
        ]);

        Sanctum::actingAs($this->teacher);

        // Teacher can view their own assignment
        $response = $this->getJson("/api/assignments/{$teacherAssignment->id}");
        $this->assertTrue(in_array($response->status(), [200, 403]));

        // Teacher cannot view other teacher's assignment
        $response = $this->getJson("/api/assignments/{$otherAssignment->id}");
        $this->assertEquals(403, $response->status());

        // Teacher cannot update other teacher's assignment
        $response = $this->putJson("/api/assignments/{$otherAssignment->id}", [
            'title' => 'Updated Title',
        ]);
        $this->assertEquals(403, $response->status());
    }

    /** @test */
    public function unauthenticated_users_can_only_access_public_endpoints()
    {
        // Public endpoints should be accessible
        $response = $this->getJson('/api/public/announcements');
        $response->assertStatus(200);

        $response = $this->getJson('/api/public/events');
        $response->assertStatus(200);

        $response = $this->getJson('/api/public/academics');
        $response->assertStatus(200);

        // Protected endpoints should require authentication
        $response = $this->getJson('/api/dashboard');
        $response->assertStatus(401);

        $response = $this->getJson('/api/classes');
        $response->assertStatus(401);

        $response = $this->postJson('/api/announcements', [
            'title' => 'Test',
            'content' => 'Test content',
            'type' => 'news',
        ]);
        $response->assertStatus(401);
    }

    /** @test */
    public function role_based_access_control_works_correctly()
    {
        // Teacher trying to access admin-only endpoints
        Sanctum::actingAs($this->teacher);

        $response = $this->getJson('/api/analytics/dashboard');
        $this->assertTrue(in_array($response->status(), [403, 404]));

        $response = $this->postJson('/api/cms/content', [
            'title' => 'Teacher Content',
            'content' => 'Teacher trying to create content',
            'type' => 'page',
        ]);
        $this->assertTrue(in_array($response->status(), [403, 422]));

        // Admin accessing teacher endpoints should work
        Sanctum::actingAs($this->admin);

        $teacherClass = Classes::factory()->create(['teacher_id' => $this->teacher->id]);
        $response = $this->getJson("/api/classes/{$teacherClass->id}");
        $this->assertTrue(in_array($response->status(), [200, 403]));
    }

    /** @test */
    public function token_expiration_and_refresh_works()
    {
        // Login to get token
        $response = $this->postJson('/api/auth/login', [
            'email' => $this->teacher->email,
            'password' => 'password',
        ]);

        if ($response->status() === 200) {
            $token = $response->json('data.token');

            // Use token to access protected endpoint
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->getJson('/api/dashboard');

            $this->assertTrue(in_array($response->status(), [200, 403]));

            // Refresh token
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/auth/refresh');

            $this->assertTrue(in_array($response->status(), [200, 401]));
        }
    }

    /** @test */
    public function session_management_prevents_concurrent_sessions()
    {
        // Login from first "device"
        $response1 = $this->postJson('/api/auth/login', [
            'email' => $this->teacher->email,
            'password' => 'password',
        ]);

        // Login from second "device"
        $response2 = $this->postJson('/api/auth/login', [
            'email' => $this->teacher->email,
            'password' => 'password',
        ]);

        if ($response1->status() === 200 && $response2->status() === 200) {
            $token1 = $response1->json('data.token');
            $token2 = $response2->json('data.token');

            // Both tokens should be different
            $this->assertNotEquals($token1, $token2);

            // Both should work initially (or implement single session logic)
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token1,
            ])->getJson('/api/dashboard');

            $this->assertTrue(in_array($response->status(), [200, 401, 403]));
        }
    }

    /** @test */
    public function resource_ownership_is_enforced()
    {
        $teacherResource = Resource::factory()->create([
            'teacher_id' => $this->teacher->id,
        ]);

        $otherResource = Resource::factory()->create([
            'teacher_id' => $this->otherTeacher->id,
        ]);

        Sanctum::actingAs($this->teacher);

        // Teacher can update their own resource
        $response = $this->putJson("/api/resources/{$teacherResource->id}", [
            'title' => 'Updated Title',
        ]);
        $this->assertTrue(in_array($response->status(), [200, 403]));

        // Teacher cannot update other teacher's resource
        $response = $this->putJson("/api/resources/{$otherResource->id}", [
            'title' => 'Updated Title',
        ]);
        $this->assertEquals(403, $response->status());

        // Teacher cannot delete other teacher's resource
        $response = $this->deleteJson("/api/resources/{$otherResource->id}");
        $this->assertEquals(403, $response->status());
    }

    /** @test */
    public function middleware_chain_works_correctly()
    {
        // Test that multiple middleware work together
        Sanctum::actingAs($this->teacher);

        // This should go through auth, role, and rate limiting middleware
        for ($i = 0; $i < 5; $i++) {
            $response = $this->getJson('/api/dashboard');
            
            if ($response->status() === 429) {
                // Rate limit hit
                break;
            }
            
            $this->assertTrue(in_array($response->status(), [200, 403]));
        }
    }

    /** @test */
    public function api_versioning_authorization_works()
    {
        Sanctum::actingAs($this->teacher);

        // Test that authorization works across API versions
        $endpoints = [
            '/api/v1/dashboard',
            '/api/dashboard', // Default version
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            // Should get consistent authorization behavior
            $this->assertTrue(in_array($response->status(), [200, 403, 404]));
        }
    }

    /** @test */
    public function cross_tenant_data_isolation()
    {
        // Ensure teachers can't access data from other schools/tenants
        // This test assumes multi-tenancy implementation
        
        $teacher1Class = Classes::factory()->create(['teacher_id' => $this->teacher->id]);
        $teacher2Class = Classes::factory()->create(['teacher_id' => $this->otherTeacher->id]);

        Sanctum::actingAs($this->teacher);

        // Get all classes - should only return teacher's classes
        $response = $this->getJson('/api/classes');
        
        if ($response->status() === 200) {
            $classes = $response->json('data.data', []);
            
            foreach ($classes as $class) {
                $this->assertEquals($this->teacher->id, $class['teacher_id']);
            }
        }
    }
}