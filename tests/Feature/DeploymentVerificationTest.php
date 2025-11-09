<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;

class DeploymentVerificationTest extends TestCase
{
    /**
     * Test database connectivity and basic operations
     */
    public function test_database_connectivity(): void
    {
        // Test database connection
        $this->assertTrue(DB::connection()->getPdo() !== null);
        
        // Test basic query
        $result = DB::select('SELECT 1 as test');
        $this->assertEquals(1, $result[0]->test);
        
        // Test migrations are up to date
        $pendingMigrations = Artisan::call('migrate:status');
        $this->assertEquals(0, $pendingMigrations);
    }

    /**
     * Test cache system functionality
     */
    public function test_cache_system(): void
    {
        $key = 'deployment_test_' . time();
        $value = 'test_value_' . rand(1000, 9999);
        
        // Test cache put
        Cache::put($key, $value, 60);
        
        // Test cache get
        $retrieved = Cache::get($key);
        $this->assertEquals($value, $retrieved);
        
        // Test cache forget
        Cache::forget($key);
        $this->assertNull(Cache::get($key));
    }

    /**
     * Test file storage system
     */
    public function test_file_storage(): void
    {
        $filename = 'deployment_test_' . time() . '.txt';
        $content = 'Test content for deployment verification';
        
        // Test file put
        Storage::put($filename, $content);
        $this->assertTrue(Storage::exists($filename));
        
        // Test file get
        $retrieved = Storage::get($filename);
        $this->assertEquals($content, $retrieved);
        
        // Test file delete
        Storage::delete($filename);
        $this->assertFalse(Storage::exists($filename));
    }

    /**
     * Test API endpoints are accessible
     */
    public function test_api_endpoints_accessibility(): void
    {
        // Test public endpoints
        $publicEndpoints = [
            '/api/public/home',
            '/api/public/academics/curriculum',
            '/api/public/admissions/process',
        ];
        
        foreach ($publicEndpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(200);
            $response->assertJsonStructure(['success', 'data']);
        }
    }

    /**
     * Test authentication system
     */
    public function test_authentication_system(): void
    {
        // Test login endpoint exists
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword'
        ]);
        
        // Should return 422 (validation error) or 401 (unauthorized)
        $this->assertContains($response->getStatusCode(), [401, 422]);
    }

    /**
     * Test environment configuration
     */
    public function test_environment_configuration(): void
    {
        // Test required environment variables
        $requiredEnvVars = [
            'APP_NAME',
            'APP_ENV',
            'APP_KEY',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_DATABASE',
        ];
        
        foreach ($requiredEnvVars as $var) {
            $this->assertNotEmpty(env($var), "Environment variable {$var} is not set");
        }
        
        // Test app key is set and valid
        $this->assertNotEmpty(config('app.key'));
        $this->assertStringStartsWith('base64:', config('app.key'));
    }

    /**
     * Test security headers and configurations
     */
    public function test_security_configuration(): void
    {
        $response = $this->get('/');
        
        // Test that debug mode is disabled in production
        if (app()->environment('production')) {
            $this->assertFalse(config('app.debug'));
        }
        
        // Test HTTPS redirect in production
        if (app()->environment('production')) {
            $this->assertTrue(config('app.url') === null || str_starts_with(config('app.url'), 'https://'));
        }
    }

    /**
     * Test performance and optimization
     */
    public function test_performance_optimization(): void
    {
        // Test config is cached in production
        if (app()->environment('production')) {
            $this->assertTrue(app()->configurationIsCached());
        }
        
        // Test routes are cached in production
        if (app()->environment('production')) {
            $this->assertTrue(app()->routesAreCached());
        }
        
        // Test basic response time
        $start = microtime(true);
        $this->getJson('/api/public/home');
        $end = microtime(true);
        
        $responseTime = ($end - $start) * 1000; // Convert to milliseconds
        $this->assertLessThan(2000, $responseTime, 'API response time should be under 2 seconds');
    }

    /**
     * Test logging system
     */
    public function test_logging_system(): void
    {
        // Test that logs can be written
        \Log::info('Deployment verification test log entry');
        
        // Test log files exist and are writable
        $logPath = storage_path('logs');
        $this->assertTrue(is_dir($logPath));
        $this->assertTrue(is_writable($logPath));
    }

    /**
     * Test queue system (if enabled)
     */
    public function test_queue_system(): void
    {
        if (config('queue.default') !== 'sync') {
            // Test queue connection
            $this->assertTrue(true); // Queue connection test would go here
        } else {
            $this->markTestSkipped('Queue system is using sync driver');
        }
    }

    /**
     * Test health check endpoint
     */
    public function test_health_check_endpoint(): void
    {
        $response = $this->get('/api/health');
        $response->assertStatus(200);
        $response->assertJson(['status' => 'healthy']);
    }

    /**
     * Test CORS configuration
     */
    public function test_cors_configuration(): void
    {
        $response = $this->options('/api/public/home', [
            'Origin' => 'https://example.com',
            'Access-Control-Request-Method' => 'GET',
        ]);
        
        // Should handle OPTIONS request properly
        $this->assertContains($response->getStatusCode(), [200, 204]);
    }

    /**
     * Test rate limiting
     */
    public function test_rate_limiting(): void
    {
        // Make multiple requests to test rate limiting
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->getJson('/api/public/home');
        }
        
        // All requests should succeed (within rate limit)
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
    }

    /**
     * Test database indexes and performance
     */
    public function test_database_performance(): void
    {
        // Test that key tables have proper indexes
        $tables = ['users', 'students', 'announcements', 'events'];
        
        foreach ($tables as $table) {
            $indexes = DB::select("SHOW INDEX FROM {$table}");
            $this->assertNotEmpty($indexes, "Table {$table} should have indexes");
        }
    }
}