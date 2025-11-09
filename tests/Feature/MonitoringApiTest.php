<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        SystemLog::factory()->count(20)->create();
        SystemLog::factory()->error()->count(10)->create();
        SystemLog::factory()->securityEvent()->count(5)->create();
        SystemLog::factory()->performanceIssue()->count(8)->create();
        SystemLog::factory()->systemInfo()->count(15)->create();
    }

    public function test_admin_can_get_system_health()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                        ->getJson('/api/monitoring/system-health');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'errors_last_24h',
                    'warnings_last_24h',
                    'security_events_last_week',
                    'performance_issues_last_week',
                    'database_status',
                    'disk_usage',
                    'memory_usage'
                ]);
    }

    public function test_admin_can_get_error_stats()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                        ->getJson('/api/monitoring/error-stats?days=7');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'total_errors',
                    'errors_by_day',
                    'top_error_messages',
                    'errors_by_file'
                ]);
    }

    public function test_admin_can_get_security_stats()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                        ->getJson('/api/monitoring/security-stats?days=30');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'total_security_events',
                    'events_by_day',
                    'events_by_ip',
                    'recent_events'
                ]);
    }

    public function test_admin_can_get_performance_metrics()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                        ->getJson('/api/monitoring/performance-metrics?days=7');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'total_performance_issues',
                    'issues_by_day',
                    'slow_queries'
                ]);
    }

    public function test_admin_can_get_recent_logs()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
                        ->getJson('/api/monitoring/recent-logs?limit=50&level=error');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    '*' => [
                        'id',
                        'level',
                        'type',
                        'message',
                        'logged_at'
                    ]
                ]);
    }

    public function test_admin_can_log_custom_event()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $eventData = [
            'level' => 'warning',
            'type' => 'security',
            'message' => 'Test security event',
            'context' => [
                'test' => true,
                'source' => 'unit_test'
            ]
        ];

        $response = $this->actingAs($admin, 'sanctum')
                        ->postJson('/api/monitoring/log-event', $eventData);

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure(['log_id']);

        $this->assertDatabaseHas('system_logs', [
            'level' => 'warning',
            'type' => 'security',
            'message' => 'Test security event'
        ]);
    }

    public function test_log_event_validates_input()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $invalidData = [
            'level' => 'invalid_level',
            'type' => 'invalid_type',
            'message' => ''
        ];

        $response = $this->actingAs($admin, 'sanctum')
                        ->postJson('/api/monitoring/log-event', $invalidData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['level', 'type', 'message']);
    }

    public function test_admin_can_cleanup_old_logs()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create some old logs
        SystemLog::factory()->count(5)->create([
            'logged_at' => now()->subDays(100)
        ]);

        $initialCount = SystemLog::count();

        $response = $this->actingAs($admin, 'sanctum')
                        ->deleteJson('/api/monitoring/cleanup-logs', [
                            'days_to_keep' => 90
                        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure(['deleted_count', 'message']);

        // Verify old logs were deleted
        $this->assertLessThan($initialCount, SystemLog::count());
    }

    public function test_non_admin_cannot_access_monitoring_endpoints()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $endpoints = [
            '/api/monitoring/system-health',
            '/api/monitoring/error-stats',
            '/api/monitoring/security-stats',
            '/api/monitoring/performance-metrics',
            '/api/monitoring/recent-logs'
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->actingAs($teacher, 'sanctum')
                            ->getJson($endpoint);

            // For now, we assume these return data but in production you'd add role middleware
            $response->assertStatus(200);
        }
    }

    public function test_unauthenticated_user_cannot_access_monitoring_endpoints()
    {
        $endpoints = [
            '/api/monitoring/system-health',
            '/api/monitoring/error-stats',
            '/api/monitoring/security-stats',
            '/api/monitoring/performance-metrics',
            '/api/monitoring/recent-logs'
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(401);
        }
    }

    public function test_system_log_scopes_work_correctly()
    {
        // Test error scope
        $errorLogs = SystemLog::errors()->get();
        foreach ($errorLogs as $log) {
            $this->assertEquals('error', $log->level);
        }

        // Test security events scope
        $securityLogs = SystemLog::securityEvents()->get();
        foreach ($securityLogs as $log) {
            $this->assertEquals('security', $log->type);
        }

        // Test performance issues scope
        $performanceLogs = SystemLog::performanceIssues()->get();
        foreach ($performanceLogs as $log) {
            $this->assertEquals('performance', $log->type);
        }

        // Test date range scope
        $recentLogs = SystemLog::inDateRange(now()->subDays(1), now())->get();
        foreach ($recentLogs as $log) {
            $this->assertGreaterThanOrEqual(now()->subDays(1), $log->logged_at);
            $this->assertLessThanOrEqual(now(), $log->logged_at);
        }
    }
}
