<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Classes>
 */
class ClassesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gradeLevel = $this->faker->randomElement(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']);
        $section = $this->faker->randomElement(['A', 'B', 'C', 'D']);
        
        return [
            'name' => "Grade {$gradeLevel} - Section {$section}",
            'code' => "CLS{$gradeLevel}{$section}" . $this->faker->unique()->numberBetween(100, 999),
            'grade_level' => $gradeLevel,
            'section' => $section,
            'academic_year' => $this->faker->randomElement(['2023-2024', '2024-2025', '2025-2026']),
            'teacher_id' => \App\Models\User::factory(),
            'description' => $this->faker->optional()->sentence(),
            'max_students' => $this->faker->numberBetween(20, 35),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }
}
