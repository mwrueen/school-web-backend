<?php

namespace Tests\Unit\Unit;

use App\Models\Classes;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassesModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_classes_belongs_to_teacher()
    {
        $teacher = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $class = Classes::factory()->create(['teacher_id' => $teacher->id]);

        $this->assertInstanceOf(User::class, $class->teacher);
        $this->assertEquals($teacher->id, $class->teacher->id);
    }

    public function test_classes_has_many_to_many_subjects()
    {
        $class = Classes::factory()->create();
        $subject1 = Subject::factory()->create();
        $subject2 = Subject::factory()->create();

        $class->subjects()->attach([$subject1->id, $subject2->id]);

        $this->assertCount(2, $class->subjects);
        $this->assertTrue($class->subjects->contains($subject1));
        $this->assertTrue($class->subjects->contains($subject2));
    }

    public function test_classes_has_many_to_many_students()
    {
        $class = Classes::factory()->create();
        $student1 = Student::factory()->create();
        $student2 = Student::factory()->create();

        $class->students()->attach([$student1->id, $student2->id]);

        $this->assertCount(2, $class->students);
        $this->assertTrue($class->students->contains($student1));
        $this->assertTrue($class->students->contains($student2));
    }

    public function test_is_active_method()
    {
        $activeClass = Classes::factory()->create(['is_active' => true]);
        $inactiveClass = Classes::factory()->create(['is_active' => false]);

        $this->assertTrue($activeClass->isActive());
        $this->assertFalse($inactiveClass->isActive());
    }

    public function test_is_full_method()
    {
        $class = Classes::factory()->create(['max_students' => 2]);
        $student1 = Student::factory()->create();
        $student2 = Student::factory()->create();
        $student3 = Student::factory()->create();

        $this->assertFalse($class->isFull());

        $class->students()->attach([$student1->id, $student2->id]);
        $this->assertTrue($class->isFull());

        $class->students()->attach($student3->id);
        $this->assertTrue($class->isFull());
    }

    public function test_available_spots_attribute()
    {
        $class = Classes::factory()->create(['max_students' => 3]);
        $student1 = Student::factory()->create();
        $student2 = Student::factory()->create();

        $this->assertEquals(3, $class->available_spots);

        $class->students()->attach($student1->id);
        $class->refresh();
        $this->assertEquals(2, $class->available_spots);

        $class->students()->attach($student2->id);
        $class->refresh();
        $this->assertEquals(1, $class->available_spots);
    }

    public function test_full_name_attribute()
    {
        $class = Classes::factory()->create([
            'grade_level' => '10',
            'section' => 'B'
        ]);

        $this->assertEquals('Grade 10 - Section B', $class->full_name);
    }

    public function test_active_scope()
    {
        Classes::factory()->create(['is_active' => true]);
        Classes::factory()->create(['is_active' => false]);
        Classes::factory()->create(['is_active' => true]);

        $activeClasses = Classes::active()->get();
        $this->assertCount(2, $activeClasses);
    }

    public function test_by_grade_scope()
    {
        Classes::factory()->create(['grade_level' => '9']);
        Classes::factory()->create(['grade_level' => '10']);
        Classes::factory()->create(['grade_level' => '10']);

        $grade10Classes = Classes::byGrade('10')->get();
        $this->assertCount(2, $grade10Classes);
    }

    public function test_by_academic_year_scope()
    {
        Classes::factory()->create(['academic_year' => '2023-2024']);
        Classes::factory()->create(['academic_year' => '2024-2025']);
        Classes::factory()->create(['academic_year' => '2024-2025']);

        $currentYearClasses = Classes::byAcademicYear('2024-2025')->get();
        $this->assertCount(2, $currentYearClasses);
    }

    public function test_by_teacher_scope()
    {
        $teacher1 = User::factory()->create(['role' => User::ROLE_TEACHER]);
        $teacher2 = User::factory()->create(['role' => User::ROLE_TEACHER]);

        Classes::factory()->create(['teacher_id' => $teacher1->id]);
        Classes::factory()->create(['teacher_id' => $teacher2->id]);
        Classes::factory()->create(['teacher_id' => $teacher1->id]);

        $teacher1Classes = Classes::byTeacher($teacher1->id)->get();
        $this->assertCount(2, $teacher1Classes);
    }
}
