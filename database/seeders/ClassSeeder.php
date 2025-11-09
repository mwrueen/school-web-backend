<?php

namespace Database\Seeders;

use App\Models\Classes;
use App\Models\User;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get teachers
        $teachers = User::where('role', User::ROLE_TEACHER)->get();
        
        if ($teachers->isEmpty()) {
            $this->command->warn('No teachers found. Please run UserSeeder first.');
            return;
        }

        // Get subjects
        $subjects = Subject::all();
        
        if ($subjects->isEmpty()) {
            $this->command->warn('No subjects found. Please run SubjectSeeder first.');
            return;
        }

        $classes = [
            [
                'name' => 'Grade 1A',
                'code' => 'G1A',
                'grade_level' => '1',
                'section' => 'A',
                'academic_year' => '2024-2025',
                'description' => 'Grade 1 Section A',
                'max_students' => 25,
                'teacher_id' => $teachers->first()->id,
            ],
            [
                'name' => 'Grade 2B',
                'code' => 'G2B',
                'grade_level' => '2',
                'section' => 'B',
                'academic_year' => '2024-2025',
                'description' => 'Grade 2 Section B',
                'max_students' => 30,
                'teacher_id' => $teachers->count() > 1 ? $teachers->skip(1)->first()->id : $teachers->first()->id,
            ],
            [
                'name' => 'Grade 3C',
                'code' => 'G3C',
                'grade_level' => '3',
                'section' => 'C',
                'academic_year' => '2024-2025',
                'description' => 'Grade 3 Section C',
                'max_students' => 28,
                'teacher_id' => $teachers->count() > 2 ? $teachers->skip(2)->first()->id : $teachers->first()->id,
            ],
        ];

        foreach ($classes as $classData) {
            $class = Classes::create($classData);
            
            // Assign random subjects to each class (3-5 subjects)
            $randomSubjects = $subjects->random(rand(3, 5));
            $class->subjects()->attach($randomSubjects->pluck('id'));
        }
    }
}