<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Student;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DatabaseIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /** @test */
    public function it_can_create_and_retrieve_users_with_relationships()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        
        $class = Classes::factory()->create(['teacher_id' => $teacher->id]);
        $subject = Subject::factory()->create();
        
        // Test relationship creation
        $class->subjects()->attach($subject->id);
        
        // Test data retrieval with relationships
        $retrievedTeacher = User::with(['classes.subjects'])->find($teacher->id);
        
        $this->assertInstanceOf(User::class, $retrievedTeacher);
        $this->assertEquals('teacher', $retrievedTeacher->role);
        $this->assertCount(1, $retrievedTeacher->classes);
        $this->assertCount(1, $retrievedTeacher->classes->first()->subjects);
    }

    /** @test */
    public function it_can_handle_complex_student_class_relationships()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = Classes::factory()->create(['teacher_id' => $teacher->id]);
        $students = Student::factory()->count(5)->create();
        
        // Enroll students in class
        $class->students()->attach($students->pluck('id'));
        
        // Test retrieval
        $classWithStudents = Classes::with('students')->find($class->id);
        
        $this->assertCount(5, $classWithStudents->students);
        
        // Test student can be in multiple classes
        $anotherClass = Classes::factory()->create(['teacher_id' => $teacher->id]);
        $anotherClass->students()->attach($students->first()->id);
        
        $student = Student::with('classes')->find($students->first()->id);
        $this->assertCount(2, $student->classes);
    }

    /** @test */
    public function it_can_handle_assignment_submission_workflow()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = Classes::factory()->create(['teacher_id' => $teacher->id]);
        $subject = Subject::factory()->create();
        $student = Student::factory()->create();
        
        // Create assignment
        $assignment = Assignment::factory()->create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'due_date' => now()->addDays(7),
        ]);
        
        // Create submission
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'submitted_at' => now(),
        ]);
        
        // Test relationships
        $assignmentWithSubmissions = Assignment::with('submissions.student')->find($assignment->id);
        
        $this->assertCount(1, $assignmentWithSubmissions->submissions);
        $this->assertEquals($student->id, $assignmentWithSubmissions->submissions->first()->student->id);
    }

    /** @test */
    public function it_can_handle_cascading_deletes_properly()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = Classes::factory()->create(['teacher_id' => $teacher->id]);
        $assignment = Assignment::factory()->create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
        ]);
        
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $assignment->id,
        ]);
        
        // Delete assignment should cascade to submissions
        $assignment->delete();
        
        $this->assertDatabaseMissing('assignments', ['id' => $assignment->id]);
        $this->assertDatabaseMissing('assignment_submissions', ['id' => $submission->id]);
    }

    /** @test */
    public function it_can_handle_bulk_operations_efficiently()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = Classes::factory()->create(['teacher_id' => $teacher->id]);
        
        // Bulk create announcements
        $announcementData = [];
        for ($i = 0; $i < 100; $i++) {
            $announcementData[] = [
                'title' => "Announcement {$i}",
                'content' => "Content for announcement {$i}",
                'type' => 'news',
                'is_public' => true,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        Announcement::insert($announcementData);
        
        $this->assertEquals(100, Announcement::count());
        
        // Bulk update
        Announcement::where('type', 'news')->update(['is_public' => false]);
        
        $this->assertEquals(0, Announcement::where('is_public', true)->count());
    }

    /** @test */
    public function it_can_handle_database_transactions()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        
        try {
            \DB::transaction(function () use ($teacher) {
                $class = Classes::factory()->create(['teacher_id' => $teacher->id]);
                $assignment = Assignment::factory()->create([
                    'teacher_id' => $teacher->id,
                    'class_id' => $class->id,
                ]);
                
                // Simulate an error
                throw new \Exception('Simulated error');
            });
        } catch (\Exception $e) {
            // Transaction should be rolled back
        }
        
        // Verify no data was inserted due to rollback
        $this->assertEquals(0, Classes::where('teacher_id', $teacher->id)->count());
        $this->assertEquals(0, Assignment::where('teacher_id', $teacher->id)->count());
    }

    /** @test */
    public function it_can_handle_complex_queries_with_joins()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = Classes::factory()->create(['teacher_id' => $teacher->id]);
        $subject = Subject::factory()->create();
        $students = Student::factory()->count(3)->create();
        
        $class->subjects()->attach($subject->id);
        $class->students()->attach($students->pluck('id'));
        
        // Create assignments for the class
        $assignments = Assignment::factory()->count(2)->create([
            'teacher_id' => $teacher->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
        ]);
        
        // Create submissions for each assignment
        foreach ($assignments as $assignment) {
            foreach ($students as $student) {
                AssignmentSubmission::factory()->create([
                    'assignment_id' => $assignment->id,
                    'student_id' => $student->id,
                ]);
            }
        }
        
        // Complex query: Get all students with their submission count for a specific class
        $studentsWithSubmissions = Student::select('students.*')
            ->selectRaw('COUNT(assignment_submissions.id) as submission_count')
            ->join('class_student', 'students.id', '=', 'class_student.student_id')
            ->join('assignments', 'class_student.class_id', '=', 'assignments.class_id')
            ->leftJoin('assignment_submissions', function ($join) {
                $join->on('students.id', '=', 'assignment_submissions.student_id')
                     ->on('assignments.id', '=', 'assignment_submissions.assignment_id');
            })
            ->where('class_student.class_id', $class->id)
            ->groupBy('students.id')
            ->get();
        
        $this->assertCount(3, $studentsWithSubmissions);
        $this->assertEquals(2, $studentsWithSubmissions->first()->submission_count);
    }

    /** @test */
    public function it_can_handle_database_constraints_and_validation()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        
        // Test unique constraint
        $class1 = Classes::factory()->create([
            'teacher_id' => $teacher->id,
            'grade_level' => 10,
            'section' => 'A',
            'academic_year' => '2024-2025',
        ]);
        
        // This should fail due to unique constraint
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Classes::factory()->create([
            'teacher_id' => $teacher->id,
            'grade_level' => 10,
            'section' => 'A',
            'academic_year' => '2024-2025',
        ]);
    }

    /** @test */
    public function it_can_handle_soft_deletes()
    {
        $announcement = Announcement::factory()->create();
        
        // Soft delete
        $announcement->delete();
        
        // Should not appear in normal queries
        $this->assertEquals(0, Announcement::count());
        
        // Should appear in withTrashed queries
        $this->assertEquals(1, Announcement::withTrashed()->count());
        
        // Can be restored
        $announcement->restore();
        $this->assertEquals(1, Announcement::count());
    }

    /** @test */
    public function it_can_handle_model_events_and_observers()
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        
        // Test model creation event
        $class = Classes::factory()->create(['teacher_id' => $teacher->id]);
        
        // Verify the class code was generated (assuming this happens in a model event)
        $this->assertNotNull($class->code);
        $this->assertStringStartsWith('CLS', $class->code);
    }
}