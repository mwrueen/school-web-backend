<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Content>
 */
class ContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = $this->faker->sentence(4);
        
        return [
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title),
            'content' => $this->faker->paragraphs(3, true),
            'type' => $this->faker->randomElement(['page', 'post', 'announcement', 'news']),
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'meta_data' => [
                'description' => $this->faker->sentence(),
                'keywords' => implode(', ', $this->faker->words(5))
            ],
            'template' => $this->faker->randomElement(['default', 'full-width', 'sidebar', 'landing']),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_featured' => $this->faker->boolean(20), // 20% chance of being featured
            'published_at' => $this->faker->optional(0.7)->dateTimeBetween('-1 month', '+1 week'),
            'author_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the content is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    /**
     * Indicate that the content is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    /**
     * Indicate that the content is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the content is of a specific type.
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }
}
