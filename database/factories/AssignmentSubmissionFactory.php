<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => \App\Models\Assignment::factory(),
            'student_id' => \App\Models\Student::factory(),
            'content' => $this->faker->paragraph(3),
            'attachments' => null,
            'submitted_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'is_late' => $this->faker->boolean(20),
            'grade' => null,
            'points_earned' => null,
            'feedback' => null,
            'graded_by' => null,
            'graded_at' => null,
            'status' => $this->faker->randomElement(['draft', 'submitted', 'graded', 'returned']),
        ];
    }

    /**
     * Create a draft submission
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'submitted_at' => null,
            'is_late' => false,
        ]);
    }

    /**
     * Create a submitted submission
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create a graded submission
     */
    public function graded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'graded',
            'submitted_at' => $this->faker->dateTimeBetween('-2 weeks', '-1 week'),
            'grade' => $this->faker->numberBetween(60, 100),
            'points_earned' => $this->faker->numberBetween(60, 100),
            'feedback' => $this->faker->sentence(),
            'graded_by' => \App\Models\User::factory()->create(['role' => 'teacher'])->id,
            'graded_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create a late submission
     */
    public function late(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_late' => true,
        ]);
    }
}
