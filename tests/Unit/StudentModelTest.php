<?php

namespace Tests\Unit;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_has_correct_fillable_attributes(): void
    {
        $student = new Student();
        $fillable = $student->getFillable();
        
        $expectedFillable = [
            'name',
            'email',
            'student_id',
            'grade_level',
            'parent_name',
            'parent_email',
            'parent_phone',
            'address',
            'date_of_birth',
            'enrollment_date',
            'status',
        ];

        foreach ($expectedFillable as $field) {
            $this->assertContains($field, $fillable);
        }
    }

    public function test_student_status_constants(): void
    {
        $this->assertEquals('active', Student::STATUS_ACTIVE);
        $this->assertEquals('inactive', Student::STATUS_INACTIVE);
        $this->assertEquals('graduated', Student::STATUS_GRADUATED);
        $this->assertEquals('transferred', Student::STATUS_TRANSFERRED);
    }

    public function test_student_is_active_method(): void
    {
        $activeStudent = Student::factory()->create(['status' => Student::STATUS_ACTIVE]);
        $inactiveStudent = Student::factory()->create(['status' => Student::STATUS_INACTIVE]);
        
        $this->assertTrue($activeStudent->isActive());
        $this->assertFalse($inactiveStudent->isActive());
    }

    public function test_student_has_graduated_method(): void
    {
        $graduatedStudent = Student::factory()->create(['status' => Student::STATUS_GRADUATED]);
        $activeStudent = Student::factory()->create(['status' => Student::STATUS_ACTIVE]);
        
        $this->assertTrue($graduatedStudent->hasGraduated());
        $this->assertFalse($activeStudent->hasGraduated());
    }

    public function test_student_full_identifier_attribute(): void
    {
        $student = Student::factory()->create([
            'name' => 'John Doe',
            'student_id' => 'STU001'
        ]);
        
        $this->assertEquals('John Doe (STU001)', $student->full_identifier);
    }

    public function test_student_active_scope(): void
    {
        Student::factory()->create(['status' => Student::STATUS_ACTIVE]);
        Student::factory()->create(['status' => Student::STATUS_INACTIVE]);
        Student::factory()->create(['status' => Student::STATUS_GRADUATED]);
        
        $activeStudents = Student::active()->get();
        
        $this->assertCount(1, $activeStudents);
        $this->assertEquals(Student::STATUS_ACTIVE, $activeStudents->first()->status);
    }

    public function test_student_by_grade_scope(): void
    {
        Student::factory()->create(['grade_level' => '10']);
        Student::factory()->create(['grade_level' => '11']);
        Student::factory()->create(['grade_level' => '10']);
        
        $grade10Students = Student::byGrade('10')->get();
        
        $this->assertCount(2, $grade10Students);
        foreach ($grade10Students as $student) {
            $this->assertEquals('10', $student->grade_level);
        }
    }

    public function test_student_date_casting(): void
    {
        $student = Student::factory()->create([
            'date_of_birth' => '2010-05-15',
            'enrollment_date' => '2023-09-01'
        ]);
        
        $this->assertInstanceOf(\Carbon\Carbon::class, $student->date_of_birth);
        $this->assertInstanceOf(\Carbon\Carbon::class, $student->enrollment_date);
    }
}
