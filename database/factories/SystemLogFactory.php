<?php

namespace Database\Factories;

use App\Models\SystemLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SystemLog>
 */
class SystemLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $levels = [SystemLog::LEVEL_DEBUG, SystemLog::LEVEL_INFO, SystemLog::LEVEL_WARNING, SystemLog::LEVEL_ERROR];
        $types = [SystemLog::TYPE_ERROR, SystemLog::TYPE_SECURITY, SystemLog::TYPE_PERFORMANCE, SystemLog::TYPE_SYSTEM];

        return [
            'level' => $this->faker->randomElement($levels),
            'type' => $this->faker->randomElement($types),
            'message' => $this->faker->sentence(),
            'context' => json_encode([
                'user_action' => $this->faker->word(),
                'resource' => $this->faker->word(),
                'additional_info' => $this->faker->sentence()
            ]),
            'file' => $this->faker->optional()->filePath(),
            'line' => $this->faker->optional()->numberBetween(1, 1000),
            'stack_trace' => $this->faker->optional()->text(500),
            'user_id' => $this->faker->optional()->numberBetween(1, 100),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'request_id' => $this->faker->uuid(),
            'session_id' => $this->faker->optional()->uuid(),
            'metadata' => [
                'request_method' => $this->faker->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
                'response_time' => $this->faker->numberBetween(10, 5000),
                'memory_usage' => $this->faker->numberBetween(1024, 104857600)
            ],
            'logged_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Create an error log
     */
    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => SystemLog::LEVEL_ERROR,
            'type' => SystemLog::TYPE_ERROR,
        ]);
    }

    /**
     * Create a security event log
     */
    public function securityEvent(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => SystemLog::LEVEL_WARNING,
            'type' => SystemLog::TYPE_SECURITY,
            'message' => 'Suspicious login attempt detected',
        ]);
    }

    /**
     * Create a performance issue log
     */
    public function performanceIssue(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => SystemLog::LEVEL_WARNING,
            'type' => SystemLog::TYPE_PERFORMANCE,
            'message' => 'Slow query detected',
        ]);
    }

    /**
     * Create a system info log
     */
    public function systemInfo(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => SystemLog::LEVEL_INFO,
            'type' => SystemLog::TYPE_SYSTEM,
        ]);
    }
}
