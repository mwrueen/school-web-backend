<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'instructions' => $this->faker->paragraph(2),
            'class_id' => \App\Models\Classes::factory(),
            'subject_id' => \App\Models\Subject::factory(),
            'teacher_id' => \App\Models\User::factory()->create(['role' => 'teacher'])->id,
            'type' => $this->faker->randomElement(['homework', 'quiz', 'exam', 'project', 'lab']),
            'max_points' => $this->faker->numberBetween(50, 100),
            'due_date' => $this->faker->dateTimeBetween('now', '+2 weeks'),
            'available_from' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'available_until' => $this->faker->dateTimeBetween('+2 weeks', '+4 weeks'),
            'allow_late_submission' => $this->faker->boolean(70),
            'late_penalty_percent' => $this->faker->numberBetween(0, 20),
            'attachments' => null,
            'is_published' => $this->faker->boolean(80),
        ];
    }
}
