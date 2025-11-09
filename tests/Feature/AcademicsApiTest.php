<?php

namespace Tests\Feature;

use App\Models\Subject;
use App\Models\Classes;
use App\Models\Event;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AcademicsApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_curriculum_endpoint_returns_subjects_grouped_by_grade(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        
        // Create subjects for different grades
        $mathSubject = Subject::factory()->create([
            'name' => 'Mathematics',
            'code' => 'MATH101',
            'grade_levels' => ['9', '10'],
            'is_active' => true,
        ]);

        $scienceSubject = Subject::factory()->create([
            'name' => 'Science',
            'code' => 'SCI101',
            'grade_levels' => ['9'],
            'is_active' => true,
        ]);

        // Create classes
        Classes::factory()->create([
            'grade_level' => '9',
            'teacher_id' => $teacher->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/public/academics/curriculum');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'curriculum',
                        'available_grades'
                    ]
                ])
                ->assertJsonPath('success', true);

        $curriculum = $response->json('data.curriculum');
        $this->assertArrayHasKey('9', $curriculum);
        $this->assertCount(2, $curriculum['9']); // Math and Science for grade 9
    }

    public function test_curriculum_endpoint_filters_by_grade_level(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        
        $mathSubject = Subject::factory()->create([
            'name' => 'Mathematics',
            'grade_levels' => ['9', '10'],
            'is_active' => true,
        ]);

        $advancedMath = Subject::factory()->create([
            'name' => 'Advanced Mathematics',
            'grade_levels' => ['11', '12'],
            'is_active' => true,
        ]);

        Classes::factory()->create([
            'grade_level' => '9',
            'teacher_id' => $teacher->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/public/academics/curriculum?grade_level=9');

        $response->assertStatus(200);
        
        $curriculum = $response->json('data.curriculum');
        $this->assertArrayHasKey('9', $curriculum);
        $this->assertArrayNotHasKey('11', $curriculum);
    }

    public function test_examinations_endpoint_returns_exam_schedules_and_policies(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create examination events
        Event::factory()->create([
            'title' => 'Mid-term Examination',
            'description' => 'Mathematics mid-term exam',
            'type' => 'academic',
            'event_date' => now()->addWeek(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        Event::factory()->create([
            'title' => 'Final Test',
            'description' => 'Science final test',
            'type' => 'academic',
            'event_date' => now()->addMonth(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/academics/examinations');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'schedules',
                        'policies' => [
                            'general_policies',
                            'grading_system',
                            'examination_types',
                            'important_dates'
                        ]
                    ]
                ])
                ->assertJsonPath('success', true)
                ->assertJsonCount(2, 'data.schedules');
    }

    public function test_examinations_endpoint_filters_upcoming_exams(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create past exam (should not appear)
        Event::factory()->create([
            'title' => 'Past Examination',
            'type' => 'academic',
            'event_date' => now()->subWeek(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        // Create upcoming exam
        Event::factory()->create([
            'title' => 'Upcoming Examination',
            'type' => 'academic',
            'event_date' => now()->addWeek(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/academics/examinations?upcoming=true');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data.schedules')
                ->assertJsonPath('data.schedules.0.title', 'Upcoming Examination');
    }

    public function test_calendar_endpoint_returns_academic_events(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create academic events
        Event::factory()->create([
            'title' => 'Academic Event 1',
            'type' => 'academic',
            'event_date' => now()->addDays(5),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        Event::factory()->create([
            'title' => 'Academic Event 2',
            'type' => 'academic',
            'event_date' => now()->addDays(10),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        // Create non-academic event (should not appear)
        Event::factory()->create([
            'title' => 'Sports Event',
            'type' => 'sports',
            'event_date' => now()->addDays(7),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/academics/calendar');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'calendar',
                        'total_events'
                    ]
                ])
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.total_events', 2);
    }

    public function test_calendar_endpoint_filters_by_year_and_month(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        $targetDate = now()->addMonth();
        
        Event::factory()->create([
            'title' => 'Target Month Event',
            'type' => 'academic',
            'event_date' => $targetDate,
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        Event::factory()->create([
            'title' => 'Different Month Event',
            'type' => 'academic',
            'event_date' => $targetDate->copy()->addMonth(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/academics/calendar?year=' . $targetDate->year . '&month=' . $targetDate->month);

        $response->assertStatus(200)
                ->assertJsonPath('data.total_events', 1);
    }

    public function test_syllabus_endpoint_returns_syllabus_files(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        $class = Classes::factory()->create([
            'grade_level' => '9',
            'teacher_id' => $teacher->id,
        ]);

        // Create syllabus resource
        Resource::factory()->create([
            'title' => 'Math Syllabus Grade 9',
            'resource_type' => 'syllabus',
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'is_public' => true,
            'teacher_id' => $teacher->id,
        ]);

        // Create non-syllabus resource (should not appear)
        Resource::factory()->create([
            'title' => 'Math Worksheet',
            'resource_type' => 'document',
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'is_public' => true,
            'teacher_id' => $teacher->id,
        ]);

        $response = $this->getJson('/api/public/academics/syllabus');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'syllabus',
                        'available_grades'
                    ]
                ])
                ->assertJsonPath('success', true);

        $syllabus = $response->json('data.syllabus');
        $this->assertArrayHasKey('9', $syllabus);
        $this->assertArrayHasKey('Mathematics', $syllabus['9']);
        $this->assertCount(1, $syllabus['9']['Mathematics']);
    }

    public function test_syllabus_endpoint_filters_by_grade_level(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $subject = Subject::factory()->create(['name' => 'Mathematics']);
        
        $grade9Class = Classes::factory()->create([
            'grade_level' => '9',
            'teacher_id' => $teacher->id,
        ]);

        $grade10Class = Classes::factory()->create([
            'grade_level' => '10',
            'teacher_id' => $teacher->id,
        ]);

        Resource::factory()->create([
            'title' => 'Math Syllabus Grade 9',
            'resource_type' => 'syllabus',
            'subject_id' => $subject->id,
            'class_id' => $grade9Class->id,
            'is_public' => true,
            'teacher_id' => $teacher->id,
        ]);

        Resource::factory()->create([
            'title' => 'Math Syllabus Grade 10',
            'resource_type' => 'syllabus',
            'subject_id' => $subject->id,
            'class_id' => $grade10Class->id,
            'is_public' => true,
            'teacher_id' => $teacher->id,
        ]);

        $response = $this->getJson('/api/public/academics/syllabus?grade_level=9');

        $response->assertStatus(200);
        
        $syllabus = $response->json('data.syllabus');
        $this->assertArrayHasKey('9', $syllabus);
        $this->assertArrayNotHasKey('10', $syllabus);
    }

    public function test_grade_levels_endpoint_returns_available_grades(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        
        Classes::factory()->create([
            'grade_level' => '9',
            'teacher_id' => $teacher->id,
            'is_active' => true,
        ]);

        Classes::factory()->create([
            'grade_level' => '10',
            'teacher_id' => $teacher->id,
            'is_active' => true,
        ]);

        // Inactive class (should not appear)
        Classes::factory()->create([
            'grade_level' => '11',
            'teacher_id' => $teacher->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/public/academics/grade-levels');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ])
                ->assertJsonPath('success', true)
                ->assertJsonCount(2, 'data');

        $gradeLevels = $response->json('data');
        $this->assertContains('9', $gradeLevels);
        $this->assertContains('10', $gradeLevels);
        $this->assertNotContains('11', $gradeLevels);
    }

    public function test_academic_years_endpoint_returns_available_years(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        
        Classes::factory()->create([
            'academic_year' => '2023-2024',
            'teacher_id' => $teacher->id,
        ]);

        Classes::factory()->create([
            'academic_year' => '2024-2025',
            'teacher_id' => $teacher->id,
        ]);

        $response = $this->getJson('/api/public/academics/academic-years');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ])
                ->assertJsonPath('success', true)
                ->assertJsonCount(2, 'data');

        $academicYears = $response->json('data');
        $this->assertContains('2023-2024', $academicYears);
        $this->assertContains('2024-2025', $academicYears);
    }

    public function test_subjects_endpoint_returns_active_subjects(): void
    {
        Subject::factory()->create([
            'name' => 'Mathematics',
            'grade_levels' => ['9', '10'],
            'is_active' => true,
        ]);

        Subject::factory()->create([
            'name' => 'Science',
            'grade_levels' => ['9'],
            'is_active' => true,
        ]);

        // Inactive subject (should not appear)
        Subject::factory()->create([
            'name' => 'Inactive Subject',
            'grade_levels' => ['9'],
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/public/academics/subjects');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ])
                ->assertJsonPath('success', true)
                ->assertJsonCount(2, 'data');
    }

    public function test_subjects_endpoint_filters_by_grade_level(): void
    {
        Subject::factory()->create([
            'name' => 'Mathematics',
            'grade_levels' => ['9', '10'],
            'is_active' => true,
        ]);

        Subject::factory()->create([
            'name' => 'Advanced Physics',
            'grade_levels' => ['11', '12'],
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/public/academics/subjects?grade_level=9');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Mathematics');
    }

    public function test_academics_endpoints_exclude_private_content(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create private event (should not appear)
        Event::factory()->create([
            'title' => 'Private Academic Event',
            'type' => 'academic',
            'event_date' => now()->addWeek(),
            'is_public' => false,
            'created_by' => $user->id,
        ]);

        // Create private resource (should not appear)
        Resource::factory()->create([
            'title' => 'Private Syllabus',
            'resource_type' => 'syllabus',
            'is_public' => false,
            'teacher_id' => $user->id,
        ]);

        $calendarResponse = $this->getJson('/api/public/academics/calendar');
        $syllabusResponse = $this->getJson('/api/public/academics/syllabus');

        $calendarResponse->assertStatus(200)
                        ->assertJsonPath('data.total_events', 0);

        $syllabusResponse->assertStatus(200);
        $syllabus = $syllabusResponse->json('data.syllabus');
        $this->assertEmpty($syllabus);
    }
}