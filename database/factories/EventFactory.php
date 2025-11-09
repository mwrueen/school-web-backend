<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventDate = $this->faker->dateTimeBetween('-1 month', '+3 months');
        $endDate = $this->faker->optional(0.7)->dateTimeBetween($eventDate, $eventDate->format('Y-m-d H:i:s') . ' +4 hours');

        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional(0.8)->paragraphs(2, true),
            'event_date' => $eventDate,
            'end_date' => $endDate,
            'location' => $this->faker->optional(0.9)->randomElement([
                'Main Hall',
                'Auditorium',
                'Library',
                'Gymnasium',
                'Classroom A',
                'Computer Lab',
                'Science Lab',
                'Playground',
                'Conference Room'
            ]),
            'type' => $this->faker->randomElement(['academic', 'cultural', 'sports', 'holiday', 'meeting', 'examination']),
            'is_public' => $this->faker->boolean(85), // 85% chance of being public
            'all_day' => $this->faker->boolean(30), // 30% chance of being all day
            'created_by' => \App\Models\User::factory(),
        ];
    }

    /**
     * Indicate that the event is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Indicate that the event is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    /**
     * Indicate that the event is all day.
     */
    public function allDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'all_day' => true,
            'end_date' => null,
        ]);
    }

    /**
     * Indicate that the event is upcoming.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_date' => $this->faker->dateTimeBetween('now', '+3 months'),
        ]);
    }

    /**
     * Indicate that the event is in the past.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ]);
    }

    /**
     * Indicate that the event is of a specific type.
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
        ]);
    }

    /**
     * Indicate that the event is today.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_date' => now()->setTime(
                $this->faker->numberBetween(9, 17),
                $this->faker->randomElement([0, 15, 30, 45])
            ),
        ]);
    }
}
