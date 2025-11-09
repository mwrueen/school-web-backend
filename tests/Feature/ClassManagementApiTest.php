<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\Student;
use App\Models\Assignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClassManagementApiTest extends TestCase
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
        $this->subjects = Subject::factory()->count(3)->create();
        
        // Create students
        $this->students = Student::factory()->count(5)->create([
            'status' => 'active',
            'grade_level' => '10',
        ]);
        
        // Create class for teacher
        $this->class = Classes::factory()->create([
            'teacher_id' => $this->teacher->id,
            'is_active' => true,
            'max_students' => 30,
            'grade_level' => '10',
            'section' => 'A',
            'academic_year' => '2024-2025',
        ]);
        
        // Create class for other teacher with different section
        $this->otherClass = Classes::factory()->create([
            'teacher_id' => $this->otherTeacher->id,
            'is_active' => true,
            'grade_level' => '10',
            'section' => 'B',
            'academic_year' => '2024-2025',
        ]);
        
        // Attach subjects and students to class
        $this->class->subjects()->attach($this->subjects->take(2)->pluck('id'));
        $this->class->students()->attach($this->students->take(3)->pluck('id'));
    }

    public function test_class_management_requires_authentication()
    {
        $response = $this->getJson('/api/classes');
        $response->assertStatus(401);
    }

    public function test_teacher_can_view_their_classes()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/classes');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'full_name',
                        'grade_level',
                        'section',
                        'academic_year',
                        'teacher',
                        'subjects',
                        'students_count',
                        'assignments_count',
                        'available_spots',
                    ],
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->class->id, $data[0]['id']);
    }

    public function test_admin_can_view_all_classes()
    {
        Sanctum::actingAs($this->admin);
        
        $response = $this->getJson('/api/classes');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data); // Should see both classes
    }

    public function test_teacher_can_create_class_for_themselves()
    {
        Sanctum::actingAs($this->teacher);
        
        $classData = [
            'name' => 'Mathematics Advanced',
            'code' => 'MATH-ADV-11',
            'grade_level' => '11',
            'section' => 'A',
            'academic_year' => '2024-2025',
            'description' => 'Advanced mathematics class',
            'max_students' => 25,
            'teacher_id' => $this->teacher->id,
            'subject_ids' => [$this->subjects->first()->id],
        ];
        
        $response = $this->postJson('/api/classes', $classData);
        
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Class created successfully.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'code',
                    'teacher',
                    'subjects',
                ],
            ]);
        
        $this->assertDatabaseHas('classes', [
            'name' => 'Mathematics Advanced',
            'code' => 'MATH-ADV-11',
            'teacher_id' => $this->teacher->id,
        ]);
    }

    public function test_teacher_cannot_create_class_for_other_teacher()
    {
        Sanctum::actingAs($this->teacher);
        
        $classData = [
            'name' => 'Physics Class',
            'code' => 'PHY-101',
            'grade_level' => '10',
            'section' => 'B',
            'academic_year' => '2024-2025',
            'max_students' => 25,
            'teacher_id' => $this->otherTeacher->id,
        ];
        
        $response = $this->postJson('/api/classes', $classData);
        
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Teachers can only create classes for themselves.',
            ]);
    }

    public function test_admin_can_create_class_for_any_teacher()
    {
        Sanctum::actingAs($this->admin);
        
        $classData = [
            'name' => 'Chemistry Lab',
            'code' => 'CHEM-LAB-10',
            'grade_level' => '10',
            'section' => 'C',
            'academic_year' => '2024-2025',
            'max_students' => 20,
            'teacher_id' => $this->otherTeacher->id,
        ];
        
        $response = $this->postJson('/api/classes', $classData);
        
        $response->assertStatus(201);
        
        $this->assertDatabaseHas('classes', [
            'name' => 'Chemistry Lab',
            'teacher_id' => $this->otherTeacher->id,
        ]);
    }

    public function test_teacher_can_view_their_class_details()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson("/api/classes/{$this->class->id}");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'teacher',
                    'subjects',
                    'students',
                    'assignments',
                    'students_count',
                    'assignments_count',
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertEquals($this->class->id, $data['id']);
        $this->assertCount(2, $data['subjects']);
        $this->assertCount(3, $data['students']);
    }

    public function test_teacher_cannot_view_other_teacher_class()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson("/api/classes/{$this->otherClass->id}");
        
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied. You can only view your own classes.',
            ]);
    }

    public function test_teacher_can_update_their_class()
    {
        Sanctum::actingAs($this->teacher);
        
        $updateData = [
            'name' => 'Updated Class Name',
            'description' => 'Updated description',
            'max_students' => 35,
        ];
        
        $response = $this->putJson("/api/classes/{$this->class->id}", $updateData);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Class updated successfully.',
            ]);
        
        $this->assertDatabaseHas('classes', [
            'id' => $this->class->id,
            'name' => 'Updated Class Name',
            'max_students' => 35,
        ]);
    }

    public function test_teacher_can_assign_subjects_to_their_class()
    {
        Sanctum::actingAs($this->teacher);
        
        $newSubject = $this->subjects->last();
        
        $response = $this->postJson("/api/classes/{$this->class->id}/assign-subjects", [
            'subject_ids' => [$newSubject->id],
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Subjects assigned successfully.',
            ]);
        
        $this->assertTrue($this->class->subjects()->where('subject_id', $newSubject->id)->exists());
    }

    public function test_teacher_can_enroll_students_in_their_class()
    {
        Sanctum::actingAs($this->teacher);
        
        $newStudent = $this->students->last();
        
        $response = $this->postJson("/api/classes/{$this->class->id}/enroll-students", [
            'student_ids' => [$newStudent->id],
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Students enrolled successfully.',
            ]);
        
        $this->assertTrue($this->class->students()->where('students.id', $newStudent->id)->exists());
    }

    public function test_cannot_enroll_students_beyond_class_capacity()
    {
        Sanctum::actingAs($this->teacher);
        
        // Set class to nearly full
        $this->class->update(['max_students' => 4]);
        
        // Try to enroll 2 more students (would exceed capacity)
        $newStudents = $this->students->slice(3, 2);
        
        $response = $this->postJson("/api/classes/{$this->class->id}/enroll-students", [
            'student_ids' => $newStudents->pluck('id')->toArray(),
        ]);
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot enroll students. Class capacity exceeded.',
            ]);
    }

    public function test_cannot_enroll_already_enrolled_students()
    {
        Sanctum::actingAs($this->teacher);
        
        $enrolledStudent = $this->students->first();
        
        $response = $this->postJson("/api/classes/{$this->class->id}/enroll-students", [
            'student_ids' => [$enrolledStudent->id],
        ]);
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Some students are already enrolled in this class.',
            ]);
    }

    public function test_teacher_can_remove_students_from_their_class()
    {
        Sanctum::actingAs($this->teacher);
        
        $studentToRemove = $this->students->first();
        
        $response = $this->postJson("/api/classes/{$this->class->id}/remove-students", [
            'student_ids' => [$studentToRemove->id],
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Students removed successfully.',
            ]);
        
        $this->assertFalse($this->class->students()->where('students.id', $studentToRemove->id)->exists());
    }

    public function test_teacher_can_get_class_resources()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson("/api/classes/{$this->class->id}/resources");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'class_id',
                    'class_name',
                    'resources',
                    'total_resources',
                ],
            ]);
    }

    public function test_can_get_available_subjects()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/available-subjects');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'description',
                        'grade_level',
                    ],
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_can_get_available_students()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/available-students');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'student_id',
                        'email',
                        'grade_level',
                        'status',
                    ],
                ],
            ]);
    }

    public function test_can_filter_available_students_by_grade()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson('/api/available-students?grade_level=10');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $student) {
            $this->assertEquals('10', $student['grade_level']);
        }
    }

    public function test_can_exclude_students_from_specific_class()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->getJson("/api/available-students?exclude_class_id={$this->class->id}");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should return students not enrolled in the class
        $this->assertCount(2, $data); // 5 total - 3 enrolled = 2 available
    }

    public function test_cannot_delete_class_with_students()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->deleteJson("/api/classes/{$this->class->id}");
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete class with enrolled students. Please remove students first.',
            ]);
    }

    public function test_cannot_delete_class_with_assignments()
    {
        Sanctum::actingAs($this->teacher);
        
        // Remove students first
        $this->class->students()->detach();
        
        // Add an assignment
        Assignment::factory()->create([
            'class_id' => $this->class->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subjects->first()->id,
        ]);
        
        $response = $this->deleteJson("/api/classes/{$this->class->id}");
        
        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete class with assignments. Please remove assignments first.',
            ]);
    }

    public function test_can_delete_empty_class()
    {
        Sanctum::actingAs($this->teacher);
        
        // Remove students and ensure no assignments
        $this->class->students()->detach();
        
        $response = $this->deleteJson("/api/classes/{$this->class->id}");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Class deleted successfully.',
            ]);
        
        $this->assertDatabaseMissing('classes', ['id' => $this->class->id]);
    }

    public function test_class_creation_validation()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->postJson('/api/classes', []);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'code',
                'grade_level',
                'section',
                'academic_year',
                'max_students',
                'teacher_id',
            ]);
    }

    public function test_unique_class_code_validation()
    {
        Sanctum::actingAs($this->teacher);
        
        $response = $this->postJson('/api/classes', [
            'name' => 'Test Class',
            'code' => $this->class->code, // Use existing code
            'grade_level' => '10',
            'section' => 'A',
            'academic_year' => '2024-2025',
            'max_students' => 25,
            'teacher_id' => $this->teacher->id,
        ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }
}