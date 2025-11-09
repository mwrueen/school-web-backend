<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class AnnouncementController extends Controller
{

    /**
     * Display a listing of announcements (admin/teacher view)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Announcement::with('creator');

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        // Filter by visibility
        if ($request->has('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }

        // Filter by published status
        if ($request->has('published')) {
            if ($request->boolean('published')) {
                $query->published();
            } else {
                $query->whereNull('published_at');
            }
        }

        // Search by title or content
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $announcements = $query->orderBy('published_at', 'desc')
                              ->orderBy('created_at', 'desc')
                              ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $announcements,
        ]);
    }

    /**
     * Display public announcements (for public website)
     */
    public function public(Request $request): JsonResponse
    {
        $query = Announcement::public()->published();

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        // Limit recent announcements
        if ($request->has('recent')) {
            $days = $request->get('recent_days', 30);
            $query->recent($days);
        }

        $announcements = $query->orderBy('published_at', 'desc')
                              ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $announcements,
        ]);
    }

    /**
     * Store a newly created announcement
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id();

        $announcement = Announcement::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Announcement created successfully',
            'data' => $announcement->load('creator'),
        ], 201);
    }

    /**
     * Display the specified announcement
     */
    public function show(string $id): JsonResponse
    {
        $announcement = Announcement::with('creator')->findOrFail($id);

        // Check if user can view this announcement
        if (!$announcement->is_public && !Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Announcement not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $announcement,
        ]);
    }

    /**
     * Update the specified announcement
     */
    public function update(UpdateAnnouncementRequest $request, string $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $validated = $request->validated();

        $announcement->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Announcement updated successfully',
            'data' => $announcement->load('creator'),
        ]);
    }

    /**
     * Remove the specified announcement
     */
    public function destroy(string $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Announcement deleted successfully',
        ]);
    }

    /**
     * Publish an announcement
     */
    public function publish(string $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->publish();

        return response()->json([
            'success' => true,
            'message' => 'Announcement published successfully',
            'data' => $announcement->load('creator'),
        ]);
    }

    /**
     * Unpublish an announcement
     */
    public function unpublish(string $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->unpublish();

        return response()->json([
            'success' => true,
            'message' => 'Announcement unpublished successfully',
            'data' => $announcement->load('creator'),
        ]);
    }

    /**
     * Get announcement types
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Announcement::getTypes(),
        ]);
    }
}
