<?php

namespace Tests\Unit\Unit;

use App\Models\Classes;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_has_many_to_many_classes()
    {
        $subject = Subject::factory()->create();
        $class1 = Classes::factory()->create();
        $class2 = Classes::factory()->create();

        $subject->classes()->attach([$class1->id, $class2->id]);

        $this->assertCount(2, $subject->classes);
        $this->assertTrue($subject->classes->contains($class1));
        $this->assertTrue($subject->classes->contains($class2));
    }

    public function test_is_active_method()
    {
        $activeSubject = Subject::factory()->create(['is_active' => true]);
        $inactiveSubject = Subject::factory()->create(['is_active' => false]);

        $this->assertTrue($activeSubject->isActive());
        $this->assertFalse($inactiveSubject->isActive());
    }

    public function test_is_taught_in_grade_method()
    {
        $subject = Subject::factory()->create([
            'grade_levels' => ['9', '10', '11']
        ]);

        $this->assertTrue($subject->isTaughtInGrade('9'));
        $this->assertTrue($subject->isTaughtInGrade('10'));
        $this->assertTrue($subject->isTaughtInGrade('11'));
        $this->assertFalse($subject->isTaughtInGrade('12'));
        $this->assertFalse($subject->isTaughtInGrade('8'));
    }

    public function test_active_scope()
    {
        Subject::factory()->create(['is_active' => true]);
        Subject::factory()->create(['is_active' => false]);
        Subject::factory()->create(['is_active' => true]);

        $activeSubjects = Subject::active()->get();
        $this->assertCount(2, $activeSubjects);
    }

    public function test_for_grade_scope()
    {
        Subject::factory()->create(['grade_levels' => ['9', '10']]);
        Subject::factory()->create(['grade_levels' => ['10', '11']]);
        Subject::factory()->create(['grade_levels' => ['11', '12']]);

        $grade10Subjects = Subject::forGrade('10')->get();
        $this->assertCount(2, $grade10Subjects);

        $grade11Subjects = Subject::forGrade('11')->get();
        $this->assertCount(2, $grade11Subjects);

        $grade9Subjects = Subject::forGrade('9')->get();
        $this->assertCount(1, $grade9Subjects);
    }

    public function test_fillable_attributes()
    {
        $subjectData = [
            'name' => 'Mathematics',
            'code' => 'MATH101',
            'description' => 'Basic Mathematics',
            'grade_levels' => ['9', '10'],
            'credits' => 3,
            'is_active' => true,
        ];

        $subject = Subject::create($subjectData);

        $this->assertEquals('Mathematics', $subject->name);
        $this->assertEquals('MATH101', $subject->code);
        $this->assertEquals('Basic Mathematics', $subject->description);
        $this->assertEquals(['9', '10'], $subject->grade_levels);
        $this->assertEquals(3, $subject->credits);
        $this->assertTrue($subject->is_active);
    }

    public function test_grade_levels_cast_to_array()
    {
        $subject = Subject::factory()->create([
            'grade_levels' => ['9', '10', '11']
        ]);

        $this->assertIsArray($subject->grade_levels);
        $this->assertEquals(['9', '10', '11'], $subject->grade_levels);
    }

    public function test_is_active_cast_to_boolean()
    {
        $subject = Subject::factory()->create(['is_active' => 1]);
        $this->assertIsBool($subject->is_active);
        $this->assertTrue($subject->is_active);

        $subject = Subject::factory()->create(['is_active' => 0]);
        $this->assertIsBool($subject->is_active);
        $this->assertFalse($subject->is_active);
    }
}
