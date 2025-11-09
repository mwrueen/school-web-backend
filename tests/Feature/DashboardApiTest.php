<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Classes;
use App\Models\Assignment;
use App\Models\Event;
use App\Models\Announcement;
use App\Models\Student;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        
        // Create test data
        $this->createTestData();
    }

    private function createTestData()
    {
        // Create subjects
        $this->subject = Subject::factory()->create();
        
        // Create class for teacher
        $this->class = Classes::factory()->create([
            'teacher_id' => $this->teacher->id,
            'is_active' => true,
        ]);
        
        // Attach subject to class
        $this->class->subjects()->attach($this->subject->id);
        
        // Create students and attach to class
        $students = Student::factory()->count(3)->create();
        $this->class->students()->attach($students->pluck('id'));
        
        // Create assignments
        Assignment::factory()->count(2)->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'is_published' => true,
            'due_date' => Carbon::now()->addDays(3),
        ]);
        
        // Create overdue assignment
        Assignment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'is_published' => true,
            'due_date' => Carbon::now()->subDays(1),
        ]);
        
        // Create events
        Event::factory()->count(2)->create([
            'event_date' => Carbon::now()->addDays(5),
        ]);
        
        // Create announcements
        Announcement::factory()->count(3)->create([
            'is_public' => true,
            'published_at' => Carbon::now()->subHours(2),
        ]);
    }

    public function test_dashboard_index_requires_authentication()
    {
        $response = $this->getJson('/api/dashboard');
        
        $response->assertStatus(401);
    }

    public function test_teacher_can_access_dashboard_overview()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/dashboard');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['name', 'role', 'email'],
                    'daily_schedule' => [
                        'date',
                        'day_of_week',
                        'classes',
                        'total_classes',
                    ],
                    'pending_tasks',
                    'reminders',
                    'quick_stats' => [
                        'total_classes',
                        'total_students',
                        'total_assignments',
                        'pending_grading',
                    ],
                    'recent_activity',
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertEquals($this->teacher->name, $data['user']['name']);
        $this->assertEquals('teacher', $data['user']['role']);
        $this->assertGreaterThan(0, $data['quick_stats']['total_classes']);
    }

    public function test_admin_can_access_dashboard_overview()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/dashboard');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => ['name', 'role', 'email'],
                    'daily_schedule',
                    'pending_tasks',
                    'reminders',
                    'quick_stats' => [
                        'total_teachers',
                        'total_classes',
                        'total_students',
                        'total_announcements',
                    ],
                    'recent_activity',
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertEquals($this->admin->name, $data['user']['name']);
        $this->assertEquals('admin', $data['user']['role']);
    }

    public function test_teacher_can_get_daily_schedule()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/dashboard/daily-schedule');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'date',
                    'day_of_week',
                    'classes' => [
                        '*' => [
                            'id',
                            'name',
                            'full_name',
                            'grade_level',
                            'section',
                            'student_count',
                            'subjects',
                        ],
                    ],
                    'total_classes',
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertEquals(Carbon::today()->toDateString(), $data['date']);
        $this->assertGreaterThan(0, $data['total_classes']);
    }

    public function test_teacher_can_get_daily_schedule_for_specific_date()
    {
        Sanctum::actingAs($this->teacher);
        
        $date = Carbon::tomorrow()->toDateString();
        $response = $this->getJson("/api/dashboard/daily-schedule?date={$date}");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals($date, $data['date']);
    }

    public function test_teacher_can_get_pending_tasks()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/dashboard/pending-tasks');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'type',
                        'title',
                        'count',
                        'priority',
                        'url',
                    ],
                ],
            ]);
    }

    public function test_teacher_can_get_reminders()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/dashboard/reminders');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'type',
                        'title',
                        'description',
                        'priority',
                    ],
                ],
            ]);
    }

    public function test_teacher_can_get_teacher_specific_data()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/dashboard/teacher-data');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'classes_overview' => [
                        '*' => [
                            'id',
                            'name',
                            'full_name',
                            'grade_level',
                            'section',
                            'student_count',
                            'assignment_count',
                            'subjects',
                            'available_spots',
                        ],
                    ],
                    'assignments_summary' => [
                        'total',
                        'published',
                        'overdue',
                        'upcoming_due',
                    ],
                    'grading_queue',
                    'upcoming_deadlines',
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['assignments_summary']['total']);
        $this->assertGreaterThan(0, $data['assignments_summary']['overdue']);
    }

    public function test_admin_cannot_access_teacher_specific_data()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/dashboard/teacher-data');
        
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. Teacher role required.',
            ]);
    }

    public function test_admin_can_get_admin_specific_data()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/dashboard/admin-data');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'system_overview' => [
                        'total_users',
                        'total_teachers',
                        'total_admins',
                        'total_classes',
                        'active_classes',
                        'total_students',
                        'total_assignments',
                        'published_assignments',
                        'total_announcements',
                        'public_announcements',
                        'total_events',
                        'upcoming_events',
                    ],
                    'recent_announcements',
                    'upcoming_events',
                    'teacher_activity',
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['system_overview']['total_users']);
        $this->assertGreaterThan(0, $data['system_overview']['total_teachers']);
    }

    public function test_teacher_cannot_access_admin_specific_data()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/dashboard/admin-data');
        
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. Admin role required.',
            ]);
    }

    public function test_dashboard_returns_correct_statistics_for_teacher()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/dashboard');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Verify statistics match actual data
        $this->assertEquals(1, $data['quick_stats']['total_classes']);
        $this->assertEquals(3, $data['quick_stats']['total_students']);
        $this->assertEquals(3, $data['quick_stats']['total_assignments']);
    }

    public function test_dashboard_includes_overdue_assignments_in_pending_tasks()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/dashboard/pending-tasks');
        
        $response->assertStatus(200);
        $tasks = $response->json('data');
        
        // Should have overdue assignments task
        $overdueTask = collect($tasks)->firstWhere('type', 'overdue_assignments');
        $this->assertNotNull($overdueTask);
        $this->assertEquals(1, $overdueTask['count']);
    }

    public function test_dashboard_includes_upcoming_events_in_reminders()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/dashboard/reminders');
        
        $response->assertStatus(200);
        $reminders = $response->json('data');
        
        // Should have event reminders
        $eventReminders = collect($reminders)->where('type', 'event');
        $this->assertGreaterThan(0, $eventReminders->count());
    }
}