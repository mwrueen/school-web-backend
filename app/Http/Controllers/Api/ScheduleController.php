<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Classes;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    /**
     * Display a listing of schedules
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isTeacher() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Teacher or admin role required.',
            ], 403);
        }

        $query = Schedule::with(['class', 'subject'])
            ->active()
            ->orderBy('day_of_week')
            ->orderBy('start_time');

        // If teacher, only show schedules for their classes
        if ($user->isTeacher()) {
            $query->whereHas('class', function ($q) use ($user) {
                $q->where('teacher_id', $user->id);
            });
        }

        // Filter by class if provided
        if ($request->has('class_id')) {
            $query->byClass($request->class_id);
        }

        // Filter by day if provided
        if ($request->has('day_of_week')) {
            $query->byDay($request->day_of_week);
        }

        $schedules = $query->get()->map(function ($schedule) {
            return [
                'id' => $schedule->id,
                'class' => [
                    'id' => $schedule->class->id,
                    'name' => $schedule->class->name,
                    'code' => $schedule->class->code,
                    'grade_level' => $schedule->class->grade_level,
                    'section' => $schedule->class->section,
                ],
                'subject' => [
                    'id' => $schedule->subject->id,
                    'name' => $schedule->subject->name,
                    'code' => $schedule->subject->code,
                ],
                'day_of_week' => $schedule->day_of_week,
                'day_name' => $schedule->day_name,
                'start_time' => $schedule->start_time->format('H:i'),
                'end_time' => $schedule->end_time->format('H:i'),
                'room' => $schedule->room,
                'is_active' => $schedule->is_active,
                'created_at' => $schedule->created_at->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }

    /**
     * Store a newly created schedule
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isTeacher() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Teacher or admin role required.',
            ], 403);
        }

        $validated = $request->validate([
            'class_id' => 'required|exists:classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'day_of_week' => 'required|integer|min:0|max:6',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'room' => 'nullable|string|max:50',
        ]);

        // Check if user has permission to create schedule for this class
        $class = Classes::findOrFail($validated['class_id']);
        if ($user->isTeacher() && $class->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only create schedules for your own classes.',
            ], 403);
        }

        // Check if subject is assigned to the class
        if (!$class->subjects()->where('subjects.id', $validated['subject_id'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Subject is not assigned to this class.',
            ], 422);
        }

        // Check for time conflicts
        $conflictingSchedule = Schedule::where('class_id', $validated['class_id'])
            ->where('day_of_week', $validated['day_of_week'])
            ->where(function ($query) use ($validated) {
                $query->whereBetween('start_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhereBetween('end_time', [$validated['start_time'], $validated['end_time']])
                    ->orWhere(function ($q) use ($validated) {
                        $q->where('start_time', '<=', $validated['start_time'])
                          ->where('end_time', '>=', $validated['end_time']);
                    });
            })
            ->first();

        if ($conflictingSchedule) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule conflicts with existing schedule.',
                'data' => [
                    'conflicting_schedule' => [
                        'id' => $conflictingSchedule->id,
                        'subject' => $conflictingSchedule->subject->name,
                        'start_time' => $conflictingSchedule->start_time->format('H:i'),
                        'end_time' => $conflictingSchedule->end_time->format('H:i'),
                    ],
                ],
            ], 422);
        }

        $schedule = Schedule::create($validated);
        $schedule->load(['class', 'subject']);

        return response()->json([
            'success' => true,
            'message' => 'Schedule created successfully.',
            'data' => [
                'id' => $schedule->id,
                'class' => [
                    'id' => $schedule->class->id,
                    'name' => $schedule->class->name,
                    'code' => $schedule->class->code,
                ],
                'subject' => [
                    'id' => $schedule->subject->id,
                    'name' => $schedule->subject->name,
                    'code' => $schedule->subject->code,
                ],
                'day_of_week' => $schedule->day_of_week,
                'day_name' => $schedule->day_name,
                'start_time' => $schedule->start_time->format('H:i'),
                'end_time' => $schedule->end_time->format('H:i'),
                'room' => $schedule->room,
                'is_active' => $schedule->is_active,
            ],
        ], 201);
    }

    /**
     * Display the specified schedule
     */
    public function show(Schedule $schedule): JsonResponse
    {
        $user = Auth::user();
        
        // Load relationships first
        $schedule->load(['class', 'subject']);
        
        // Check if relationships exist
        if (!$schedule->class || !$schedule->subject) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule data is incomplete.',
            ], 404);
        }
        
        // Check access permissions
        if ($user->isTeacher() && $schedule->class->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only view schedules for your own classes.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $schedule->id,
                'class' => [
                    'id' => $schedule->class->id,
                    'name' => $schedule->class->name,
                    'code' => $schedule->class->code,
                    'grade_level' => $schedule->class->grade_level,
                    'section' => $schedule->class->section,
                ],
                'subject' => [
                    'id' => $schedule->subject->id,
                    'name' => $schedule->subject->name,
                    'code' => $schedule->subject->code,
                    'description' => $schedule->subject->description,
                ],
                'day_of_week' => $schedule->day_of_week,
                'day_name' => $schedule->day_name,
                'start_time' => $schedule->start_time->format('H:i'),
                'end_time' => $schedule->end_time->format('H:i'),
                'room' => $schedule->room,
                'is_active' => $schedule->is_active,
                'created_at' => $schedule->created_at->toISOString(),
                'updated_at' => $schedule->updated_at->toISOString(),
            ],
        ]);
    }

    /**
     * Update the specified schedule
     */
    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $schedule->class->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only update schedules for your own classes.',
            ], 403);
        }

        $validated = $request->validate([
            'subject_id' => 'sometimes|required|exists:subjects,id',
            'day_of_week' => 'sometimes|required|integer|min:0|max:6',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
            'room' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
        ]);

        // Check if subject is assigned to the class (if subject_id is being updated)
        if (isset($validated['subject_id'])) {
            if (!$schedule->class->subjects()->where('subjects.id', $validated['subject_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subject is not assigned to this class.',
                ], 422);
            }
        }

        // Check for time conflicts (if time or day is being updated)
        if (isset($validated['day_of_week']) || isset($validated['start_time']) || isset($validated['end_time'])) {
            $dayOfWeek = $validated['day_of_week'] ?? $schedule->day_of_week;
            $startTime = $validated['start_time'] ?? $schedule->start_time->format('H:i');
            $endTime = $validated['end_time'] ?? $schedule->end_time->format('H:i');

            $conflictingSchedule = Schedule::where('class_id', $schedule->class_id)
                ->where('day_of_week', $dayOfWeek)
                ->where('id', '!=', $schedule->id)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->whereBetween('start_time', [$startTime, $endTime])
                        ->orWhereBetween('end_time', [$startTime, $endTime])
                        ->orWhere(function ($q) use ($startTime, $endTime) {
                            $q->where('start_time', '<=', $startTime)
                              ->where('end_time', '>=', $endTime);
                        });
                })
                ->first();

            if ($conflictingSchedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule conflicts with existing schedule.',
                    'data' => [
                        'conflicting_schedule' => [
                            'id' => $conflictingSchedule->id,
                            'subject' => $conflictingSchedule->subject->name,
                            'start_time' => $conflictingSchedule->start_time->format('H:i'),
                            'end_time' => $conflictingSchedule->end_time->format('H:i'),
                        ],
                    ],
                ], 422);
            }
        }

        $schedule->update($validated);
        $schedule->load(['class', 'subject']);

        return response()->json([
            'success' => true,
            'message' => 'Schedule updated successfully.',
            'data' => [
                'id' => $schedule->id,
                'class' => [
                    'id' => $schedule->class->id,
                    'name' => $schedule->class->name,
                    'code' => $schedule->class->code,
                ],
                'subject' => [
                    'id' => $schedule->subject->id,
                    'name' => $schedule->subject->name,
                    'code' => $schedule->subject->code,
                ],
                'day_of_week' => $schedule->day_of_week,
                'day_name' => $schedule->day_name,
                'start_time' => $schedule->start_time->format('H:i'),
                'end_time' => $schedule->end_time->format('H:i'),
                'room' => $schedule->room,
                'is_active' => $schedule->is_active,
            ],
        ]);
    }

    /**
     * Remove the specified schedule
     */
    public function destroy(Schedule $schedule): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $schedule->class->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only delete schedules for your own classes.',
            ], 403);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Schedule deleted successfully.',
        ]);
    }
}
