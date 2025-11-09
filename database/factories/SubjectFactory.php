<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subjects = [
            'Mathematics', 'English', 'Science', 'History', 'Geography', 
            'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Art',
            'Physical Education', 'Music', 'Literature', 'Economics', 'Psychology'
        ];
        
        $subject = $this->faker->randomElement($subjects);
        $gradeLevels = $this->faker->randomElements(['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'], 
                                                   $this->faker->numberBetween(1, 4));
        
        return [
            'name' => $subject,
            'code' => strtoupper(substr($subject, 0, 3)) . $this->faker->unique()->numberBetween(100, 999),
            'description' => $this->faker->optional()->sentence(),
            'grade_levels' => $gradeLevels,
            'credits' => $this->faker->numberBetween(1, 4),
            'is_active' => $this->faker->boolean(95), // 95% chance of being active
        ];
    }
}
