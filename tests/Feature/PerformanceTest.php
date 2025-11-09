<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Student;
use App\Models\Classes;
use App\Models\Subject;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->createTestData();
    }

    /**
     * Test home page API performance
     */
    public function test_home_page_api_performance(): void
    {
        $startTime = microtime(true);
        
        $response = $this->getJson('/api/public/home');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        
        // Assert response time is under 500ms
        $this->assertLessThan(500, $executionTime, 
            "Home page API took {$executionTime}ms, should be under 500ms");
        
        // Test caching - second request should be faster
        $startTime = microtime(true);
        $response = $this->getJson('/api/public/home');
        $endTime = microtime(true);
        $cachedExecutionTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        $response->assertHeader('X-Cache-Status', 'HIT');
        
        // Cached response should be significantly faster
        $this->assertLessThan($executionTime / 2, $cachedExecutionTime,
            "Cached response took {$cachedExecutionTime}ms, should be less than half of uncached time");
    }

    /**
     * Test database query performance with large datasets
     */
    public function test_database_query_performance(): void
    {
        // Create a large dataset of students only (avoid class constraint issues)
        Student::factory(500)->create();
        
        $startTime = microtime(true);
        
        // Test a query that should use indexes
        $students = Student::where('status', 'active')
            ->where('grade_level', '10')
            ->orderBy('name')
            ->limit(20)
            ->get();
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;
        
        $this->assertLessThan(200, $executionTime,
            "Student query took {$executionTime}ms, should be under 200ms");
    }

    /**
     * Test API response caching effectiveness
     */
    public function test_api_caching_effectiveness(): void
    {
        // Clear cache
        Cache::flush();
        
        // First request - should miss cache
        $response = $this->getJson('/api/public/academics/curriculum');
        $response->assertStatus(200);
        $response->assertHeader('X-Cache-Status', 'MISS');
        
        // Second request - should hit cache
        $response = $this->getJson('/api/public/academics/curriculum');
        $response->assertStatus(200);
        $response->assertHeader('X-Cache-Status', 'HIT');
        
        // Test cache invalidation with different parameters
        $response = $this->getJson('/api/public/academics/curriculum?grade_level=10');
        $response->assertStatus(200);
        $response->assertHeader('X-Cache-Status', 'MISS');
    }

    /**
     * Test memory usage during large data operations
     */
    public function test_memory_usage_optimization(): void
    {
        $initialMemory = memory_get_usage(true);
        
        // Create and process a large dataset
        $createdCount = 500;
        Announcement::factory($createdCount)->create();
        
        // Fetch data using chunking to optimize memory
        $count = 0;
        Announcement::chunk(100, function ($announcements) use (&$count) {
            $count += $announcements->count();
        });
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // Check that we processed at least the number we created
        $this->assertGreaterThanOrEqual($createdCount, $count);
        
        // Memory increase should be reasonable (less than 50MB)
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease,
            "Memory usage increased by " . ($memoryIncrease / 1024 / 1024) . "MB, should be under 50MB");
    }

    /**
     * Test database connection pooling and query optimization
     */
    public function test_database_connection_efficiency(): void
    {
        $startTime = microtime(true);
        
        // Perform multiple database operations
        for ($i = 0; $i < 10; $i++) {
            DB::table('announcements')->where('is_public', true)->count();
            DB::table('events')->where('type', 'academic')->count();
            DB::table('students')->where('status', 'active')->count();
        }
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;
        
        // Multiple queries should complete quickly due to connection pooling
        $this->assertLessThan(200, $executionTime,
            "Multiple database queries took {$executionTime}ms, should be under 200ms");
    }

    /**
     * Test API rate limiting performance
     */
    public function test_rate_limiting_performance(): void
    {
        $responses = [];
        $startTime = microtime(true);
        
        // Make multiple requests quickly
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->getJson('/api/public/home/news');
        }
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;
        
        // All requests should succeed (within rate limit)
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
        
        // Rate limiting shouldn't significantly impact performance
        $this->assertLessThan(1000, $executionTime,
            "Rate limited requests took {$executionTime}ms, should be under 1000ms");
    }

    /**
     * Create test data for performance tests
     */
    private function createTestData(): void
    {
        // Create users
        User::factory(10)->create();
        
        // Create announcements
        Announcement::factory(50)->create([
            'is_public' => true,
            'published_at' => now(),
        ]);
        
        // Create events
        Event::factory(30)->create([
            'is_public' => true,
        ]);
        
        // Create students
        Student::factory(100)->create();
        
        // Create subjects
        Subject::factory(15)->create();
    }
}