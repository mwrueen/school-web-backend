<?php

namespace Database\Factories;

use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'student_id' => fake()->unique()->numerify('STU####'),
            'grade_level' => fake()->randomElement(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']),
            'parent_name' => fake()->name(),
            'parent_email' => fake()->safeEmail(),
            'parent_phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'date_of_birth' => fake()->dateTimeBetween('-18 years', '-5 years')->format('Y-m-d'),
            'enrollment_date' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
            'status' => fake()->randomElement([
                Student::STATUS_ACTIVE,
                Student::STATUS_INACTIVE,
                Student::STATUS_GRADUATED,
                Student::STATUS_TRANSFERRED
            ]),
        ];
    }

    /**
     * Indicate that the student is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Student::STATUS_ACTIVE,
        ]);
    }

    /**
     * Indicate that the student has graduated.
     */
    public function graduated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Student::STATUS_GRADUATED,
        ]);
    }
}
