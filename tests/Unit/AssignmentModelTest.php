<?php

namespace Tests\Unit;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\User;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentModelTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Classes $class;
    private Subject $subject;
    private Assignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $this->class = Classes::factory()->create(['teacher_id' => $this->teacher->id]);
        $this->subject = Subject::factory()->create();
        
        $this->assignment = Assignment::factory()->create([
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'due_date' => Carbon::now()->addDays(7),
            'is_published' => true,
        ]);
    }

    public function test_assignment_belongs_to_class(): void
    {
        $this->assertInstanceOf(Classes::class, $this->assignment->class);
        $this->assertEquals($this->class->id, $this->assignment->class->id);
    }

    public function test_assignment_belongs_to_subject(): void
    {
        $this->assertInstanceOf(Subject::class, $this->assignment->subject);
        $this->assertEquals($this->subject->id, $this->assignment->subject->id);
    }

    public function test_assignment_belongs_to_teacher(): void
    {
        $this->assertInstanceOf(User::class, $this->assignment->teacher);
        $this->assertEquals($this->teacher->id, $this->assignment->teacher->id);
    }

    public function test_assignment_has_many_submissions(): void
    {
        $student = Student::factory()->create();
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id' => $student->id,
        ]);

        $this->assertTrue($this->assignment->submissions->contains($submission));
    }

    public function test_assignment_is_available_when_published_and_within_timeframe(): void
    {
        $assignment = Assignment::factory()->create([
            'is_published' => true,
            'available_from' => Carbon::now()->subHour(),
            'available_until' => Carbon::now()->addHour(),
        ]);

        $this->assertTrue($assignment->isAvailable());
    }

    public function test_assignment_is_not_available_when_not_published(): void
    {
        $assignment = Assignment::factory()->create([
            'is_published' => false,
            'available_from' => Carbon::now()->subHour(),
            'available_until' => Carbon::now()->addHour(),
        ]);

        $this->assertFalse($assignment->isAvailable());
    }

    public function test_assignment_is_not_available_before_available_from(): void
    {
        $assignment = Assignment::factory()->create([
            'is_published' => true,
            'available_from' => Carbon::now()->addHour(),
            'available_until' => Carbon::now()->addDays(2),
        ]);

        $this->assertFalse($assignment->isAvailable());
    }

    public function test_assignment_is_not_available_after_available_until(): void
    {
        $assignment = Assignment::factory()->create([
            'is_published' => true,
            'available_from' => Carbon::now()->subDays(2),
            'available_until' => Carbon::now()->subHour(),
        ]);

        $this->assertFalse($assignment->isAvailable());
    }

    public function test_assignment_is_overdue_after_due_date(): void
    {
        $assignment = Assignment::factory()->create([
            'due_date' => Carbon::now()->subHour(),
        ]);

        $this->assertTrue($assignment->isOverdue());
    }

    public function test_assignment_is_not_overdue_before_due_date(): void
    {
        $assignment = Assignment::factory()->create([
            'due_date' => Carbon::now()->addHour(),
        ]);

        $this->assertFalse($assignment->isOverdue());
    }

    public function test_can_submit_late_when_allowed_and_within_final_deadline(): void
    {
        $assignment = Assignment::factory()->create([
            'due_date' => Carbon::now()->subHour(),
            'allow_late_submission' => true,
            'available_until' => Carbon::now()->addHour(),
        ]);

        $this->assertTrue($assignment->canSubmitLate());
    }

    public function test_cannot_submit_late_when_not_allowed(): void
    {
        $assignment = Assignment::factory()->create([
            'due_date' => Carbon::now()->subHour(),
            'allow_late_submission' => false,
        ]);

        $this->assertFalse($assignment->canSubmitLate());
    }

    public function test_cannot_submit_late_after_final_deadline(): void
    {
        $assignment = Assignment::factory()->create([
            'due_date' => Carbon::now()->subDays(2),
            'allow_late_submission' => true,
            'available_until' => Carbon::now()->subHour(),
        ]);

        $this->assertFalse($assignment->canSubmitLate());
    }

    public function test_days_until_due_calculation(): void
    {
        $dueDate = Carbon::now()->addDays(5)->startOfDay();
        $assignment = Assignment::factory()->create([
            'due_date' => $dueDate,
        ]);

        // Use absolute value since timing can vary slightly
        $daysDiff = abs($assignment->days_until_due - 5);
        $this->assertLessThanOrEqual(1, $daysDiff);
    }

    public function test_days_until_due_negative_when_overdue(): void
    {
        $assignment = Assignment::factory()->create([
            'due_date' => Carbon::now()->subDays(3),
        ]);

        $this->assertEquals(-3, $assignment->days_until_due);
    }

    public function test_submission_stats_calculation(): void
    {
        // Create students and enroll them in the class
        $students = Student::factory()->count(5)->create();
        $this->class->students()->attach($students->pluck('id'));

        // Create submissions for 3 students with explicit is_late = false
        $submittedStudents = $students->take(3);
        foreach ($submittedStudents as $student) {
            AssignmentSubmission::factory()->create([
                'assignment_id' => $this->assignment->id,
                'student_id' => $student->id,
                'status' => AssignmentSubmission::STATUS_SUBMITTED,
                'is_late' => false,
            ]);
        }

        // Grade 2 of the submissions
        $gradedSubmissions = $this->assignment->submissions()->take(2)->get();
        foreach ($gradedSubmissions as $submission) {
            $submission->update([
                'grade' => 85.0,
                'status' => AssignmentSubmission::STATUS_GRADED,
            ]);
        }

        // Mark exactly 1 submission as late
        $this->assignment->submissions()->first()->update(['is_late' => true]);

        $stats = $this->assignment->getSubmissionStats();

        $this->assertEquals(5, $stats['total_students']);
        $this->assertEquals(3, $stats['submitted_count']);
        $this->assertEquals(2, $stats['graded_count']);
        $this->assertEquals(1, $stats['late_count']);
        $this->assertEquals(60.0, $stats['submission_rate']);
        $this->assertEquals(66.67, $stats['grading_progress']);
    }

    public function test_average_grade_calculation(): void
    {
        $students = Student::factory()->count(3)->create();
        
        // Create submissions with different grades
        AssignmentSubmission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id' => $students[0]->id,
            'grade' => 90.0,
        ]);
        
        AssignmentSubmission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id' => $students[1]->id,
            'grade' => 80.0,
        ]);
        
        AssignmentSubmission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id' => $students[2]->id,
            'grade' => 70.0,
        ]);

        $averageGrade = $this->assignment->getAverageGrade();
        $this->assertEquals(80.0, $averageGrade);
    }

    public function test_published_scope(): void
    {
        Assignment::factory()->create(['is_published' => false]);
        Assignment::factory()->create(['is_published' => true]);

        $publishedAssignments = Assignment::published()->get();
        
        $this->assertEquals(2, $publishedAssignments->count()); // Including the one from setUp
        $this->assertTrue($publishedAssignments->every(fn($assignment) => $assignment->is_published));
    }

    public function test_by_type_scope(): void
    {
        // Create assignments with specific classes to avoid unique constraint issues
        $class1 = Classes::factory()->create(['grade_level' => '1', 'section' => 'A', 'academic_year' => '2024-2025']);
        $class2 = Classes::factory()->create(['grade_level' => '2', 'section' => 'A', 'academic_year' => '2024-2025']);
        
        Assignment::factory()->create(['type' => Assignment::TYPE_QUIZ, 'class_id' => $class1->id]);
        Assignment::factory()->create(['type' => Assignment::TYPE_EXAM, 'class_id' => $class2->id]);

        $quizzes = Assignment::byType(Assignment::TYPE_QUIZ)->get();
        
        $this->assertEquals(1, $quizzes->count());
        $this->assertEquals(Assignment::TYPE_QUIZ, $quizzes->first()->type);
    }

    public function test_overdue_scope(): void
    {
        Assignment::factory()->create(['due_date' => Carbon::now()->subHour()]);
        Assignment::factory()->create(['due_date' => Carbon::now()->addHour()]);

        $overdueAssignments = Assignment::overdue()->get();
        
        $this->assertEquals(1, $overdueAssignments->count());
        $this->assertTrue($overdueAssignments->first()->isOverdue());
    }

    public function test_for_class_scope(): void
    {
        $otherClass = Classes::factory()->create();
        Assignment::factory()->create(['class_id' => $otherClass->id]);

        $classAssignments = Assignment::forClass($this->class->id)->get();
        
        $this->assertEquals(1, $classAssignments->count());
        $this->assertEquals($this->class->id, $classAssignments->first()->class_id);
    }

    public function test_by_teacher_scope(): void
    {
        $otherTeacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        Assignment::factory()->create(['teacher_id' => $otherTeacher->id]);

        $teacherAssignments = Assignment::byTeacher($this->teacher->id)->get();
        
        $this->assertEquals(1, $teacherAssignments->count());
        $this->assertEquals($this->teacher->id, $teacherAssignments->first()->teacher_id);
    }

    public function test_assignment_fillable_attributes(): void
    {
        $fillable = [
            'title', 'description', 'instructions', 'class_id', 'subject_id', 
            'teacher_id', 'type', 'max_points', 'due_date', 'available_from', 
            'available_until', 'allow_late_submission', 'late_penalty_percent', 
            'attachments', 'is_published'
        ];

        $this->assertEquals($fillable, $this->assignment->getFillable());
    }

    public function test_assignment_casts(): void
    {
        $expectedCasts = [
            'due_date' => 'datetime',
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'allow_late_submission' => 'boolean',
            'is_published' => 'boolean',
            'attachments' => 'array',
            'max_points' => 'integer',
            'late_penalty_percent' => 'integer',
        ];

        foreach ($expectedCasts as $attribute => $cast) {
            $this->assertEquals($cast, $this->assignment->getCasts()[$attribute]);
        }
    }
}
