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

class AssignmentSubmissionModelTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Student $student;
    private Assignment $assignment;
    private AssignmentSubmission $submission;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $class = Classes::factory()->create(['teacher_id' => $this->teacher->id]);
        $subject = Subject::factory()->create();
        $this->student = Student::factory()->create();
        
        $this->assignment = Assignment::factory()->create([
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'due_date' => Carbon::now()->addDays(7),
            'max_points' => 100,
            'late_penalty_percent' => 10,
        ]);
        
        $this->submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id' => $this->student->id,
            'status' => AssignmentSubmission::STATUS_DRAFT,
        ]);
    }

    public function test_submission_belongs_to_assignment(): void
    {
        $this->assertInstanceOf(Assignment::class, $this->submission->assignment);
        $this->assertEquals($this->assignment->id, $this->submission->assignment->id);
    }

    public function test_submission_belongs_to_student(): void
    {
        $this->assertInstanceOf(Student::class, $this->submission->student);
        $this->assertEquals($this->student->id, $this->submission->student->id);
    }

    public function test_submission_belongs_to_graded_by_user(): void
    {
        $this->submission->update(['graded_by' => $this->teacher->id]);
        
        $this->assertInstanceOf(User::class, $this->submission->gradedBy);
        $this->assertEquals($this->teacher->id, $this->submission->gradedBy->id);
    }

    public function test_is_submitted_returns_false_for_draft(): void
    {
        $this->submission->update(['status' => AssignmentSubmission::STATUS_DRAFT]);
        
        $this->assertFalse($this->submission->isSubmitted());
    }

    public function test_is_submitted_returns_true_for_non_draft_status(): void
    {
        $this->submission->update(['status' => AssignmentSubmission::STATUS_SUBMITTED]);
        
        $this->assertTrue($this->submission->isSubmitted());
    }

    public function test_is_graded_returns_true_for_graded_status(): void
    {
        $this->submission->update(['status' => AssignmentSubmission::STATUS_GRADED]);
        
        $this->assertTrue($this->submission->isGraded());
    }

    public function test_is_graded_returns_true_for_returned_status(): void
    {
        $this->submission->update(['status' => AssignmentSubmission::STATUS_RETURNED]);
        
        $this->assertTrue($this->submission->isGraded());
    }

    public function test_is_graded_returns_false_for_submitted_status(): void
    {
        $this->submission->update(['status' => AssignmentSubmission::STATUS_SUBMITTED]);
        
        $this->assertFalse($this->submission->isGraded());
    }

    public function test_was_submitted_late_returns_is_late_value(): void
    {
        $this->submission->update(['is_late' => true]);
        
        $this->assertTrue($this->submission->wasSubmittedLate());
        
        $this->submission->update(['is_late' => false]);
        
        $this->assertFalse($this->submission->wasSubmittedLate());
    }

    public function test_calculate_lateness_sets_is_late_true_when_submitted_after_due_date(): void
    {
        $this->submission->update([
            'submitted_at' => $this->assignment->due_date->addHour(),
        ]);
        
        $this->submission->calculateLateness();
        
        $this->assertTrue($this->submission->is_late);
    }

    public function test_calculate_lateness_sets_is_late_false_when_submitted_before_due_date(): void
    {
        $this->submission->update([
            'submitted_at' => $this->assignment->due_date->subHour(),
        ]);
        
        $this->submission->calculateLateness();
        
        $this->assertFalse($this->submission->is_late);
    }

    public function test_calculate_points_earned_without_late_penalty(): void
    {
        $this->submission->update([
            'grade' => 85.0,
            'is_late' => false,
        ]);
        
        $this->submission->calculatePointsEarned();
        
        $this->assertEquals(85, $this->submission->points_earned);
    }

    public function test_calculate_points_earned_with_late_penalty(): void
    {
        $this->submission->update([
            'grade' => 80.0,
            'is_late' => true,
        ]);
        
        $this->submission->calculatePointsEarned();
        
        // 80 points - 10% penalty = 72 points
        $this->assertEquals(72, $this->submission->points_earned);
    }

    public function test_calculate_points_earned_minimum_zero(): void
    {
        $this->assignment->update(['late_penalty_percent' => 150]); // 150% penalty
        $this->submission->update([
            'grade' => 50.0,
            'is_late' => true,
        ]);
        
        $this->submission->calculatePointsEarned();
        
        $this->assertEquals(0, $this->submission->points_earned);
    }

    public function test_percentage_grade_attribute(): void
    {
        $this->submission->update(['grade' => 87.5]);
        
        $this->assertEquals(87.5, $this->submission->percentage_grade);
    }

    public function test_letter_grade_attribute_a(): void
    {
        $this->submission->update(['grade' => 95.0]);
        
        $this->assertEquals('A', $this->submission->letter_grade);
    }

    public function test_letter_grade_attribute_b(): void
    {
        $this->submission->update(['grade' => 85.0]);
        
        $this->assertEquals('B', $this->submission->letter_grade);
    }

    public function test_letter_grade_attribute_c(): void
    {
        $this->submission->update(['grade' => 75.0]);
        
        $this->assertEquals('C', $this->submission->letter_grade);
    }

    public function test_letter_grade_attribute_d(): void
    {
        $this->submission->update(['grade' => 65.0]);
        
        $this->assertEquals('D', $this->submission->letter_grade);
    }

    public function test_letter_grade_attribute_f(): void
    {
        $this->submission->update(['grade' => 55.0]);
        
        $this->assertEquals('F', $this->submission->letter_grade);
    }

    public function test_letter_grade_attribute_null_when_no_grade(): void
    {
        $this->submission->update(['grade' => null]);
        
        $this->assertNull($this->submission->letter_grade);
    }

    public function test_days_late_attribute_when_not_late(): void
    {
        $this->submission->update(['is_late' => false]);
        
        $this->assertEquals(0, $this->submission->days_late);
    }

    public function test_days_late_attribute_when_late(): void
    {
        $this->submission->update([
            'is_late' => true,
            'submitted_at' => $this->assignment->due_date->addDays(3),
        ]);
        
        $this->assertEquals(3, $this->submission->days_late);
    }

    public function test_submit_method_changes_status_and_sets_submitted_at(): void
    {
        $this->submission->update(['status' => AssignmentSubmission::STATUS_DRAFT]);
        
        $result = $this->submission->submit();
        
        $this->assertTrue($result);
        $this->assertEquals(AssignmentSubmission::STATUS_SUBMITTED, $this->submission->status);
        $this->assertNotNull($this->submission->submitted_at);
    }

    public function test_submit_method_calculates_lateness(): void
    {
        $this->assignment->update(['due_date' => Carbon::now()->subHour()]);
        $this->submission->update(['status' => AssignmentSubmission::STATUS_DRAFT]);
        
        $this->submission->submit();
        
        $this->assertTrue($this->submission->is_late);
    }

    public function test_submit_method_fails_when_not_draft(): void
    {
        $this->submission->update(['status' => AssignmentSubmission::STATUS_SUBMITTED]);
        
        $result = $this->submission->submit();
        
        $this->assertFalse($result);
    }

    public function test_grade_method_sets_grade_and_feedback(): void
    {
        $this->submission->update(['status' => AssignmentSubmission::STATUS_SUBMITTED]);
        
        $result = $this->submission->grade(85.5, 'Good work!', $this->teacher->id);
        
        $this->assertTrue($result);
        $this->assertEquals(85.5, $this->submission->grade);
        $this->assertEquals('Good work!', $this->submission->feedback);
        $this->assertEquals($this->teacher->id, $this->submission->graded_by);
        $this->assertEquals(AssignmentSubmission::STATUS_GRADED, $this->submission->status);
        $this->assertNotNull($this->submission->graded_at);
    }

    public function test_grade_method_clamps_grade_to_valid_range(): void
    {
        $this->submission->update(['status' => AssignmentSubmission::STATUS_SUBMITTED]);
        
        // Test upper bound
        $this->submission->grade(150.0);
        $this->assertEquals(100.0, $this->submission->grade);
        
        // Test lower bound
        $this->submission->grade(-10.0);
        $this->assertEquals(0.0, $this->submission->grade);
    }

    public function test_grade_method_fails_when_not_submitted(): void
    {
        $this->submission->update(['status' => AssignmentSubmission::STATUS_DRAFT]);
        
        $result = $this->submission->grade(85.0);
        
        $this->assertFalse($result);
    }

    public function test_return_to_student_method(): void
    {
        $this->submission->update(['status' => AssignmentSubmission::STATUS_GRADED]);
        
        $result = $this->submission->returnToStudent();
        
        $this->assertTrue($result);
        $this->assertEquals(AssignmentSubmission::STATUS_RETURNED, $this->submission->status);
    }

    public function test_return_to_student_method_fails_when_not_graded(): void
    {
        $this->submission->update(['status' => AssignmentSubmission::STATUS_SUBMITTED]);
        
        $result = $this->submission->returnToStudent();
        
        $this->assertFalse($result);
    }

    public function test_submitted_scope(): void
    {
        AssignmentSubmission::factory()->create(['status' => AssignmentSubmission::STATUS_DRAFT]);
        AssignmentSubmission::factory()->create(['status' => AssignmentSubmission::STATUS_SUBMITTED]);
        AssignmentSubmission::factory()->create(['status' => AssignmentSubmission::STATUS_GRADED]);

        $submittedSubmissions = AssignmentSubmission::submitted()->get();
        
        // Should exclude drafts, so 2 submissions (submitted + graded, not the draft from setUp or the new draft)
        $this->assertEquals(2, $submittedSubmissions->count());
        $this->assertTrue($submittedSubmissions->every(fn($sub) => $sub->status !== AssignmentSubmission::STATUS_DRAFT));
    }

    public function test_graded_scope(): void
    {
        AssignmentSubmission::factory()->create(['status' => AssignmentSubmission::STATUS_SUBMITTED]);
        AssignmentSubmission::factory()->create(['status' => AssignmentSubmission::STATUS_GRADED]);
        AssignmentSubmission::factory()->create(['status' => AssignmentSubmission::STATUS_RETURNED]);

        $gradedSubmissions = AssignmentSubmission::graded()->get();
        
        $this->assertEquals(2, $gradedSubmissions->count());
        $this->assertTrue($gradedSubmissions->every(fn($sub) => 
            $sub->status === AssignmentSubmission::STATUS_GRADED || 
            $sub->status === AssignmentSubmission::STATUS_RETURNED
        ));
    }

    public function test_late_scope(): void
    {
        AssignmentSubmission::factory()->create(['is_late' => false]);
        AssignmentSubmission::factory()->create(['is_late' => true]);

        $lateSubmissions = AssignmentSubmission::late()->get();
        
        $this->assertEquals(1, $lateSubmissions->count());
        $this->assertTrue($lateSubmissions->first()->is_late);
    }

    public function test_by_status_scope(): void
    {
        AssignmentSubmission::factory()->create(['status' => AssignmentSubmission::STATUS_SUBMITTED]);
        AssignmentSubmission::factory()->create(['status' => AssignmentSubmission::STATUS_GRADED]);

        $submittedSubmissions = AssignmentSubmission::byStatus(AssignmentSubmission::STATUS_SUBMITTED)->get();
        
        $this->assertEquals(1, $submittedSubmissions->count());
        $this->assertEquals(AssignmentSubmission::STATUS_SUBMITTED, $submittedSubmissions->first()->status);
    }

    public function test_for_assignment_scope(): void
    {
        $otherAssignment = Assignment::factory()->create();
        AssignmentSubmission::factory()->create(['assignment_id' => $otherAssignment->id]);

        $assignmentSubmissions = AssignmentSubmission::forAssignment($this->assignment->id)->get();
        
        $this->assertEquals(1, $assignmentSubmissions->count());
        $this->assertEquals($this->assignment->id, $assignmentSubmissions->first()->assignment_id);
    }

    public function test_by_student_scope(): void
    {
        $otherStudent = Student::factory()->create();
        AssignmentSubmission::factory()->create(['student_id' => $otherStudent->id]);

        $studentSubmissions = AssignmentSubmission::byStudent($this->student->id)->get();
        
        $this->assertEquals(1, $studentSubmissions->count());
        $this->assertEquals($this->student->id, $studentSubmissions->first()->student_id);
    }

    public function test_graded_by_teacher_scope(): void
    {
        $otherTeacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        AssignmentSubmission::factory()->create(['graded_by' => $otherTeacher->id]);

        $teacherGradedSubmissions = AssignmentSubmission::gradedByTeacher($this->teacher->id)->get();
        
        $this->assertEquals(0, $teacherGradedSubmissions->count()); // None graded by this teacher yet
    }

    public function test_submission_fillable_attributes(): void
    {
        $fillable = [
            'assignment_id', 'student_id', 'content', 'attachments', 'submitted_at',
            'is_late', 'grade', 'points_earned', 'feedback', 'graded_by', 'graded_at', 'status'
        ];

        $this->assertEquals($fillable, $this->submission->getFillable());
    }

    public function test_submission_casts(): void
    {
        $expectedCasts = [
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'is_late' => 'boolean',
            'attachments' => 'array',
            'grade' => 'decimal:2',
            'points_earned' => 'integer',
        ];

        foreach ($expectedCasts as $attribute => $cast) {
            $this->assertEquals($cast, $this->submission->getCasts()[$attribute]);
        }
    }
}
