<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\Student;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->otherTeacher = User::factory()->create(['role' => 'teacher']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        
        // Create test data
        $this->createTestData();
    }

    private function createTestData()
    {
        // Create subjects
        $this->subject = Subject::factory()->create();
        
        // Create students
        $this->students = Student::factory()->count(3)->create([
            'status' => 'active',
            'grade_level' => '10',
        ]);
        
        // Create class for teacher
        $this->class = Classes::factory()->create([
            'teacher_id' => $this->teacher->id,
            'is_active' => true,
            'grade_level' => '10',
            'section' => 'A',
            'academic_year' => '2024-2025',
        ]);
        
        // Create class for other teacher
        $this->otherClass = Classes::factory()->create([
            'teacher_id' => $this->otherTeacher->id,
            'is_active' => true,
            'grade_level' => '10',
            'section' => 'B',
            'academic_year' => '2024-2025',
        ]);
        
        // Attach subject and students to class
        $this->class->subjects()->attach($this->subject->id);
        $this->class->students()->attach($this->students->pluck('id'));
        
        // Create assignments
        $this->assignment = Assignment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'is_published' => true,
            'due_date' => Carbon::now()->addDays(7),
        ]);
        
        $this->otherAssignment = Assignment::factory()->create([
            'teacher_id' => $this->otherTeacher->id,
            'class_id' => $this->otherClass->id,
            'subject_id' => $this->subject->id,
            'is_published' => true,
        ]);
        
        // Create some submissions (one per student to avoid unique constraint violation)
        AssignmentSubmission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id' => $this->students->get(0)->id,
            'status' => 'submitted',
            'grade' => null,
        ]);
        
        AssignmentSubmission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id' => $this->students->get(1)->id,
            'status' => 'submitted',
            'grade' => null,
        ]);
    }

    public function test_assignment_management_requires_authentication()
    {
        $response = $this->getJson('/api/assignments');
        $response->assertStatus(401);
    }

    public function test_teacher_can_view_their_assignments()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/assignments');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'type',
                        'max_points',
                        'due_date',
                        'is_published',
                        'is_overdue',
                        'class',
                        'subject',
                        'teacher',
                        'submissions_count',
                        'submission_stats',
                    ],
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->assignment->id, $data[0]['id']);
    }

    public function test_admin_can_view_all_assignments()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/assignments');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data); // Should see both assignments
    }

    public function test_teacher_can_create_assignment_for_their_class()
    {
        Sanctum::actingAs($this->teacher);
        
        $assignmentData = [
            'title' => 'Mathematics Quiz 1',
            'description' => 'Quiz on algebra basics',
            'instructions' => 'Complete all questions within 30 minutes',
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'type' => 'quiz',
            'max_points' => 50,
            'due_date' => Carbon::now()->addDays(3)->toISOString(),
            'allow_late_submission' => true,
            'late_penalty_percent' => 10,
            'is_published' => false,
        ];
        
        $response = $this->postJson('/api/assignments', $assignmentData);
        
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment created successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'type',
                    'max_points',
                    'class',
                    'subject',
                    'teacher',
                ],
            ]);
        
        $this->assertDatabaseHas('assignments', [
            'title' => 'Mathematics Quiz 1',
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
        ]);
    }

    public function test_teacher_cannot_create_assignment_for_other_teacher_class()
    {
        Sanctum::actingAs($this->teacher);
        
        $assignmentData = [
            'title' => 'Physics Quiz',
            'description' => 'Quiz on physics',
            'class_id' => $this->otherClass->id,
            'subject_id' => $this->subject->id,
            'type' => 'quiz',
            'max_points' => 50,
            'due_date' => Carbon::now()->addDays(3)->toISOString(),
        ];
        
        $response = $this->postJson('/api/assignments', $assignmentData);
        
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. You can only create assignments for your own classes.',
            ]);
    }

    public function test_admin_can_create_assignment_for_any_class()
    {
        Sanctum::actingAs($this->admin);
        
        $assignmentData = [
            'title' => 'Chemistry Lab Report',
            'description' => 'Lab report on chemical reactions',
            'class_id' => $this->otherClass->id,
            'subject_id' => $this->subject->id,
            'type' => 'lab',
            'max_points' => 100,
            'due_date' => Carbon::now()->addDays(5)->toISOString(),
        ];
        
        $response = $this->postJson('/api/assignments', $assignmentData);
        
        $response->assertStatus(201);
        
        $this->assertDatabaseHas('assignments', [
            'title' => 'Chemistry Lab Report',
            'class_id' => $this->otherClass->id,
        ]);
    }

    public function test_teacher_can_view_their_assignment_details()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson("/api/assignments/{$this->assignment->id}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'description',
                    'instructions',
                    'type',
                    'max_points',
                    'due_date',
                    'is_published',
                    'is_available',
                    'is_overdue',
                    'class',
                    'subject',
                    'teacher',
                    'submissions_count',
                    'submission_stats',
                    'average_grade',
                    'submissions',
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertEquals($this->assignment->id, $data['id']);
        $this->assertCount(2, $data['submissions']);
    }

    public function test_teacher_cannot_view_other_teacher_assignment()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson("/api/assignments/{$this->otherAssignment->id}");
        
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. You can only view your own assignments.',
            ]);
    }

    public function test_teacher_can_update_their_assignment()
    {
        Sanctum::actingAs($this->teacher);
        
        $updateData = [
            'title' => 'Updated Assignment Title',
            'max_points' => 75,
            'allow_late_submission' => false,
        ];
        
        $response = $this->putJson("/api/assignments/{$this->assignment->id}", $updateData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment updated successfully.',
            ]);
        
        $this->assertDatabaseHas('assignments', [
            'id' => $this->assignment->id,
            'title' => 'Updated Assignment Title',
            'max_points' => 75,
        ]);
    }

    public function test_teacher_can_publish_assignment()
    {
        Sanctum::actingAs($this->teacher);
        
        // Create unpublished assignment
        $unpublishedAssignment = Assignment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'is_published' => false,
        ]);
        
        $response = $this->postJson("/api/assignments/{$unpublishedAssignment->id}/publish");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment published successfully.',
            ]);
        
        $this->assertDatabaseHas('assignments', [
            'id' => $unpublishedAssignment->id,
            'is_published' => true,
        ]);
    }

    public function test_teacher_can_unpublish_assignment()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->postJson("/api/assignments/{$this->assignment->id}/unpublish");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment unpublished successfully.',
            ]);
        
        $this->assertDatabaseHas('assignments', [
            'id' => $this->assignment->id,
            'is_published' => false,
        ]);
    }

    public function test_teacher_can_get_assignment_submissions()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson("/api/assignments/{$this->assignment->id}/submissions");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'assignment_id',
                    'assignment_title',
                    'submissions' => [
                        '*' => [
                            'id',
                            'student',
                            'status',
                            'content',
                            'grade',
                            'feedback',
                            'is_late',
                            'submitted_at',
                        ],
                    ],
                    'total_submissions',
                    'submission_stats',
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertEquals($this->assignment->id, $data['assignment_id']);
        $this->assertCount(2, $data['submissions']);
    }

    public function test_teacher_can_grade_submission()
    {
        Sanctum::actingAs($this->teacher);
        
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $this->assignment->id,
            'student_id' => $this->students->get(2)->id, // Use third student to avoid unique constraint
            'status' => 'submitted',
            'grade' => null,
        ]);
        
        $gradeData = [
            'grade' => 70, // Use a grade within the assignment's max_points
            'feedback' => 'Good work! Could improve on question 3.',
        ];
        
        $response = $this->postJson("/api/assignments/{$this->assignment->id}/submissions/{$submission->id}/grade", $gradeData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Submission graded successfully.',
            ]);
        
        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'grade' => 70,
            'feedback' => 'Good work! Could improve on question 3.',
        ]);
    }

    public function test_cannot_grade_submission_from_different_assignment()
    {
        Sanctum::actingAs($this->teacher);
        
        $otherSubmission = AssignmentSubmission::factory()->create([
            'assignment_id' => $this->otherAssignment->id,
            'student_id' => $this->students->first()->id,
        ]);
        
        $gradeData = [
            'grade' => 85,
            'feedback' => 'Good work!',
        ];
        
        $response = $this->postJson("/api/assignments/{$this->assignment->id}/submissions/{$otherSubmission->id}/grade", $gradeData);
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Submission does not belong to this assignment.',
            ]);
    }

    public function test_teacher_can_get_grading_queue()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/grading-queue');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'type',
                        'due_date',
                        'is_overdue',
                        'class',
                        'subject',
                        'pending_submissions',
                    ],
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data)); // Should have assignments with ungraded submissions
    }

    public function test_teacher_can_get_assignment_analytics()
    {
        Sanctum::actingAs($this->teacher);
        
        // Create a new assignment for analytics to avoid conflicts
        $analyticsAssignment = Assignment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'is_published' => true,
        ]);
        
        // Create graded submissions for analytics
        AssignmentSubmission::factory()->create([
            'assignment_id' => $analyticsAssignment->id,
            'student_id' => $this->students->get(0)->id,
            'grade' => 85,
            'status' => 'graded',
        ]);
        
        AssignmentSubmission::factory()->create([
            'assignment_id' => $analyticsAssignment->id,
            'student_id' => $this->students->get(1)->id,
            'grade' => 92,
            'status' => 'graded',
        ]);
        
        $response = $this->getJson("/api/assignments/{$analyticsAssignment->id}/analytics");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'assignment_id',
                    'assignment_title',
                    'total_students',
                    'submission_stats',
                    'grade_analytics' => [
                        'average',
                        'median',
                        'min',
                        'max',
                        'std_deviation',
                    ],
                    'grade_distribution',
                    'performance_insights' => [
                        'pass_rate',
                        'excellence_rate',
                        'late_submission_rate',
                    ],
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertEquals($analyticsAssignment->id, $data['assignment_id']);
        $this->assertEquals(88.5, $data['grade_analytics']['average']); // (85 + 92) / 2
    }

    public function test_can_get_assignment_types()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/assignment-types');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'value',
                        'label',
                    ],
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertCount(5, $data); // Should have 5 assignment types
        
        $types = collect($data)->pluck('value')->toArray();
        $this->assertContains('homework', $types);
        $this->assertContains('quiz', $types);
        $this->assertContains('exam', $types);
        $this->assertContains('project', $types);
        $this->assertContains('lab', $types);
    }

    public function test_can_filter_assignments_by_class()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson("/api/assignments?class_id={$this->class->id}");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $assignment) {
            $this->assertEquals($this->class->id, $assignment['class']['id']);
        }
    }

    public function test_can_filter_assignments_by_type()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/assignments?type=homework');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $assignment) {
            $this->assertEquals('homework', $assignment['type']);
        }
    }

    public function test_can_filter_assignments_by_status()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/assignments?status=published');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $assignment) {
            $this->assertTrue($assignment['is_published']);
        }
    }

    public function test_cannot_delete_assignment_with_submissions()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->deleteJson("/api/assignments/{$this->assignment->id}");
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete assignment with submissions. Please remove submissions first.',
            ]);
    }

    public function test_can_delete_assignment_without_submissions()
    {
        Sanctum::actingAs($this->teacher);
        
        $assignmentWithoutSubmissions = Assignment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
        ]);
        
        $response = $this->deleteJson("/api/assignments/{$assignmentWithoutSubmissions->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Assignment deleted successfully.',
            ]);
        
        $this->assertDatabaseMissing('assignments', ['id' => $assignmentWithoutSubmissions->id]);
    }

    public function test_assignment_creation_validation()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->postJson('/api/assignments', []);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'title',
                'description',
                'class_id',
                'subject_id',
                'type',
                'max_points',
                'due_date',
            ]);
    }

    public function test_grade_validation()
    {
        Sanctum::actingAs($this->teacher);
        
        // Create a new assignment for validation test to avoid conflicts
        $validationAssignment = Assignment::factory()->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
            'subject_id' => $this->subject->id,
            'max_points' => 100,
        ]);
        
        $submission = AssignmentSubmission::factory()->create([
            'assignment_id' => $validationAssignment->id,
            'student_id' => $this->students->first()->id,
        ]);
        
        // Test grade exceeding max points
        $response = $this->postJson("/api/assignments/{$validationAssignment->id}/submissions/{$submission->id}/grade", [
            'grade' => $validationAssignment->max_points + 10,
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['grade']);
    }
}