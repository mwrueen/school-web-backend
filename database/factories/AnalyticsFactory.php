<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Analytics>
 */
class AnalyticsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = ['page_view', 'content_view', 'download', 'search', 'form_submit'];
        $pages = [
            '/api/public/home',
            '/api/public/announcements',
            '/api/public/events',
            '/api/public/academics/curriculum',
            '/api/public/admissions/process',
            '/api/resources/1/download'
        ];

        return [
            'event_type' => $this->faker->randomElement($eventTypes),
            'page_url' => $this->faker->randomElement($pages),
            'page_title' => $this->faker->sentence(3),
            'referrer' => $this->faker->optional()->url(),
            'user_agent' => $this->faker->userAgent(),
            'ip_address' => $this->faker->ipv4(),
            'session_id' => $this->faker->uuid(),
            'metadata' => [
                'content_id' => $this->faker->optional()->numberBetween(1, 100),
                'content_type' => $this->faker->optional()->randomElement(['announcement', 'event', 'resource']),
                'content_title' => $this->faker->optional()->sentence(4),
                'file_name' => $this->faker->optional()->word() . '.pdf',
                'file_type' => $this->faker->optional()->randomElement(['pdf', 'doc', 'jpg', 'png'])
            ],
            'viewed_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Create a page view event
     */
    public function pageView(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'page_view',
        ]);
    }

    /**
     * Create a content view event
     */
    public function contentView(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'content_view',
            'metadata' => [
                'content_id' => $this->faker->numberBetween(1, 100),
                'content_type' => $this->faker->randomElement(['announcement', 'event', 'resource']),
                'content_title' => $this->faker->sentence(4),
            ]
        ]);
    }

    /**
     * Create a download event
     */
    public function download(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'download',
            'metadata' => [
                'file_name' => $this->faker->word() . '.pdf',
                'file_type' => $this->faker->randomElement(['pdf', 'doc', 'docx']),
                'file_size' => $this->faker->numberBetween(1024, 5242880), // 1KB to 5MB
            ]
        ]);
    }
}
