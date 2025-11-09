<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = [
            ['name' => 'Mathematics', 'code' => 'MATH', 'description' => 'Mathematics and problem solving', 'grade_levels' => ['1', '2', '3', '4', '5']],
            ['name' => 'English Language', 'code' => 'ENG', 'description' => 'English language and literature', 'grade_levels' => ['1', '2', '3', '4', '5']],
            ['name' => 'Science', 'code' => 'SCI', 'description' => 'General science and experiments', 'grade_levels' => ['1', '2', '3', '4', '5']],
            ['name' => 'History', 'code' => 'HIST', 'description' => 'World and local history', 'grade_levels' => ['3', '4', '5']],
            ['name' => 'Geography', 'code' => 'GEO', 'description' => 'Physical and human geography', 'grade_levels' => ['3', '4', '5']],
            ['name' => 'Physical Education', 'code' => 'PE', 'description' => 'Physical fitness and sports', 'grade_levels' => ['1', '2', '3', '4', '5']],
            ['name' => 'Art', 'code' => 'ART', 'description' => 'Visual arts and creativity', 'grade_levels' => ['1', '2', '3', '4', '5']],
            ['name' => 'Music', 'code' => 'MUS', 'description' => 'Music theory and practice', 'grade_levels' => ['1', '2', '3', '4', '5']],
        ];

        foreach ($subjects as $subject) {
            Subject::create($subject);
        }
    }
}