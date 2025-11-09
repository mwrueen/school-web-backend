<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EventController extends Controller
{
    /**
     * Display a listing of events (admin/teacher view)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Event::with('creator');

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        // Filter by visibility
        if ($request->has('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $query->inDateRange($startDate, $endDate);
        }

        // Filter by month
        if ($request->has('year') && $request->has('month')) {
            $query->inMonth($request->year, $request->month);
        }

        // Filter upcoming/past events
        if ($request->has('upcoming')) {
            if ($request->boolean('upcoming')) {
                $query->upcoming();
            } else {
                $query->past();
            }
        }

        // Search by title or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $events = $query->orderBy('event_date', 'asc')
                       ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Display public events (for public website and calendar)
     */
    public function public(Request $request): JsonResponse
    {
        $query = Event::public();

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $query->inDateRange($startDate, $endDate);
        }

        // Filter by month for calendar view
        if ($request->has('year') && $request->has('month')) {
            $query->inMonth($request->year, $request->month);
        }

        // Filter upcoming events only
        if ($request->has('upcoming') && $request->boolean('upcoming')) {
            $query->upcoming();
        }

        // Quick filters
        if ($request->has('filter')) {
            switch ($request->filter) {
                case 'today':
                    $query->today();
                    break;
                case 'this_week':
                    $query->thisWeek();
                    break;
                case 'upcoming':
                    $query->upcoming();
                    break;
            }
        }

        $events = $query->orderBy('event_date', 'asc')
                       ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Get calendar data for a specific month
     */
    public function calendar(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $events = Event::public()
                      ->inMonth($request->year, $request->month)
                      ->orderBy('event_date', 'asc')
                      ->get();

        // Group events by date for calendar display
        $calendarData = [];
        foreach ($events as $event) {
            $date = $event->event_date->format('Y-m-d');
            if (!isset($calendarData[$date])) {
                $calendarData[$date] = [];
            }
            $calendarData[$date][] = [
                'id' => $event->id,
                'title' => $event->title,
                'type' => $event->type,
                'time' => $event->all_day ? null : $event->event_date->format('H:i'),
                'all_day' => $event->all_day,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'year' => (int) $request->year,
                'month' => (int) $request->month,
                'events' => $calendarData,
            ],
        ]);
    }

    /**
     * Store a newly created event
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'required|date',
            'end_date' => 'nullable|date|after:event_date',
            'location' => 'nullable|string|max:255',
            'type' => ['required', Rule::in(Event::getTypes())],
            'is_public' => 'boolean',
            'all_day' => 'boolean',
        ]);

        $validated['created_by'] = Auth::id();

        $event = Event::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'data' => $event->load('creator'),
        ], 201);
    }

    /**
     * Display the specified event
     */
    public function show(string $id): JsonResponse
    {
        $event = Event::with('creator')->findOrFail($id);

        // Check if user can view this event
        if (!$event->is_public && !Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $event,
        ]);
    }

    /**
     * Update the specified event
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'event_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after:event_date',
            'location' => 'nullable|string|max:255',
            'type' => ['sometimes', 'required', Rule::in(Event::getTypes())],
            'is_public' => 'sometimes|boolean',
            'all_day' => 'sometimes|boolean',
        ]);

        $event->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => $event->load('creator'),
        ]);
    }

    /**
     * Remove the specified event
     */
    public function destroy(string $id): JsonResponse
    {
        $event = Event::findOrFail($id);
        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully',
        ]);
    }

    /**
     * Get event types
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Event::getTypes(),
        ]);
    }

    /**
     * Get upcoming events
     */
    public function upcoming(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 5);
        
        $events = Event::public()
                      ->upcoming()
                      ->orderBy('event_date', 'asc')
                      ->limit($limit)
                      ->get();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * Search events
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2',
        ]);

        $query = $request->get('query');
        
        $events = Event::public()
                      ->where(function ($q) use ($query) {
                          $q->where('title', 'like', "%{$query}%")
                            ->orWhere('description', 'like', "%{$query}%")
                            ->orWhere('location', 'like', "%{$query}%");
                      })
                      ->orderBy('event_date', 'asc')
                      ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }
}
