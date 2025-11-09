<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Carbon\Carbon;

class EventApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_public_events_can_be_retrieved_without_authentication(): void
    {
        // Create public events
        Event::factory()->create([
            'title' => 'Public Event',
            'type' => 'academic',
            'is_public' => true,
            'event_date' => now()->addDay(),
        ]);

        // Create private event (should not appear)
        Event::factory()->create([
            'title' => 'Private Event',
            'type' => 'meeting',
            'is_public' => false,
            'event_date' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/public/events');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'title',
                                'description',
                                'event_date',
                                'type',
                                'is_public',
                                'location',
                                'all_day',
                                'created_at',
                                'updated_at'
                            ]
                        ]
                    ]
                ])
                ->assertJsonPath('data.data.0.title', 'Public Event')
                ->assertJsonCount(1, 'data.data');
    }

    public function test_public_events_can_be_filtered_by_type(): void
    {
        Event::factory()->create([
            'type' => 'academic',
            'is_public' => true,
            'event_date' => now()->addDay(),
        ]);

        Event::factory()->create([
            'type' => 'sports',
            'is_public' => true,
            'event_date' => now()->addDay(),
        ]);

        $response = $this->getJson('/api/public/events?type=academic');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data.data')
                ->assertJsonPath('data.data.0.type', 'academic');
    }

    public function test_calendar_endpoint_returns_events_for_specific_month(): void
    {
        $year = 2025;
        $month = 12;
        
        // Create event in the specified month
        Event::factory()->create([
            'title' => 'December Event',
            'is_public' => true,
            'event_date' => Carbon::create($year, $month, 15),
        ]);

        // Create event in different month (should not appear)
        Event::factory()->create([
            'title' => 'January Event',
            'is_public' => true,
            'event_date' => Carbon::create($year + 1, 1, 15),
        ]);

        $response = $this->getJson("/api/public/events/calendar?year={$year}&month={$month}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'year',
                        'month',
                        'events'
                    ]
                ])
                ->assertJsonPath('data.year', $year)
                ->assertJsonPath('data.month', $month);

        $events = $response->json('data.events');
        $this->assertArrayHasKey('2025-12-15', $events);
        $this->assertEquals('December Event', $events['2025-12-15'][0]['title']);
    }

    public function test_authenticated_user_can_create_event(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $eventData = [
            'title' => 'Test Event',
            'description' => 'This is a test event.',
            'event_date' => now()->addWeek()->toISOString(),
            'type' => 'academic',
            'is_public' => true,
            'location' => 'Main Hall',
        ];

        $response = $this->postJson('/api/events', $eventData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'title',
                        'description',
                        'event_date',
                        'type',
                        'location',
                        'created_by',
                        'creator'
                    ]
                ])
                ->assertJsonPath('data.title', 'Test Event')
                ->assertJsonPath('data.created_by', $user->id);

        $this->assertDatabaseHas('events', [
            'title' => 'Test Event',
            'created_by' => $user->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_create_event(): void
    {
        $eventData = [
            'title' => 'Test Event',
            'description' => 'This is a test event.',
            'event_date' => now()->addWeek()->toISOString(),
            'type' => 'academic',
        ];

        $response = $this->postJson('/api/events', $eventData);

        $response->assertStatus(401);
    }

    public function test_event_creation_validates_required_fields(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/events', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'event_date', 'type']);
    }

    public function test_event_creation_validates_type_field(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/events', [
            'title' => 'Test Event',
            'event_date' => now()->addWeek()->toISOString(),
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['type']);
    }

    public function test_event_creation_validates_end_date_after_start_date(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $startDate = now()->addWeek();
        $endDate = $startDate->copy()->subHour(); // End before start

        $response = $this->postJson('/api/events', [
            'title' => 'Test Event',
            'event_date' => $startDate->toISOString(),
            'end_date' => $endDate->toISOString(),
            'type' => 'academic',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['end_date']);
    }

    public function test_authenticated_user_can_update_event(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $event = Event::factory()->create(['created_by' => $user->id]);
        
        Sanctum::actingAs($user);

        $updateData = [
            'title' => 'Updated Event Title',
            'location' => 'Updated Location',
        ];

        $response = $this->putJson("/api/events/{$event->id}", $updateData);

        $response->assertStatus(200)
                ->assertJsonPath('data.title', 'Updated Event Title')
                ->assertJsonPath('data.location', 'Updated Location');

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Updated Event Title',
        ]);
    }

    public function test_authenticated_user_can_delete_event(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $event = Event::factory()->create(['created_by' => $user->id]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/events/{$event->id}");

        $response->assertStatus(200)
                ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('events', [
            'id' => $event->id,
        ]);
    }

    public function test_can_retrieve_event_types(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/event-types');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data'
                ])
                ->assertJsonPath('success', true);

        $types = $response->json('data');
        $this->assertContains('academic', $types);
        $this->assertContains('cultural', $types);
        $this->assertContains('sports', $types);
    }

    public function test_upcoming_events_endpoint_returns_future_events(): void
    {
        // Create past event
        Event::factory()->create([
            'title' => 'Past Event',
            'is_public' => true,
            'event_date' => now()->subWeek(),
        ]);

        // Create upcoming event
        Event::factory()->create([
            'title' => 'Upcoming Event',
            'is_public' => true,
            'event_date' => now()->addWeek(),
        ]);

        $response = $this->getJson('/api/public/events/upcoming');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.title', 'Upcoming Event');
    }

    public function test_event_search_finds_matching_events(): void
    {
        Event::factory()->create([
            'title' => 'Mathematics Competition',
            'is_public' => true,
            'event_date' => now()->addWeek(),
        ]);

        Event::factory()->create([
            'title' => 'Science Fair',
            'is_public' => true,
            'event_date' => now()->addWeek(),
        ]);

        $response = $this->getJson('/api/public/events/search?query=Math');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data.data')
                ->assertJsonPath('data.data.0.title', 'Mathematics Competition');
    }

    public function test_public_event_show_returns_public_event(): void
    {
        $event = Event::factory()->create([
            'title' => 'Public Event',
            'is_public' => true,
            'event_date' => now()->addWeek(),
        ]);

        $response = $this->getJson("/api/public/events/{$event->id}");

        $response->assertStatus(200)
                ->assertJsonPath('data.title', 'Public Event');
    }

    public function test_public_event_show_hides_private_event(): void
    {
        $event = Event::factory()->create([
            'is_public' => false,
            'event_date' => now()->addWeek(),
        ]);

        $response = $this->getJson("/api/public/events/{$event->id}");

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_view_all_events(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create both public and private events
        Event::factory()->create(['is_public' => true, 'event_date' => now()->addWeek()]);
        Event::factory()->create(['is_public' => false, 'event_date' => now()->addWeek()]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/events');

        $response->assertStatus(200)
                ->assertJsonCount(2, 'data.data');
    }

    public function test_calendar_endpoint_validates_year_and_month(): void
    {
        $response = $this->getJson('/api/public/events/calendar?year=invalid&month=invalid');

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['year', 'month']);
    }

    public function test_search_endpoint_validates_query_parameter(): void
    {
        $response = $this->getJson('/api/public/events/search?query=a');

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['query']);
    }
}
