<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContentRequest;
use App\Models\Content;
use App\Models\ContentVersion;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContentController extends Controller
{


    /**
     * Display a listing of content items
     */
    public function index(Request $request): JsonResponse
    {
        $query = Content::with(['author', 'currentVersion']);

        // Filter by type
        if ($request->has('type')) {
            $query->byType($request->type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by title or content
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 15);
        $contents = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $contents
        ]);
    }

    /**
     * Store a newly created content item
     */
    public function store(StoreContentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['author_id'] = Auth::id();
        
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }
        
        // Set default status if not provided
        if (!isset($data['status'])) {
            $data['status'] = 'draft';
        }

        $content = Content::create($data);

        // Create initial version
        $version = $content->createVersion($data, Auth::user());

        // If status is published, publish the version
        if ($data['status'] === 'published') {
            $content->publishVersion($version);
        }

        $content->load(['author', 'currentVersion', 'versions.createdBy']);

        return response()->json([
            'success' => true,
            'message' => 'Content created successfully',
            'data' => $content
        ], 201);
    }

    /**
     * Display the specified content item
     */
    public function show(string $id): JsonResponse
    {
        $content = Content::with(['author', 'currentVersion', 'versions.createdBy'])
                         ->findOrFail($id);

        // Check permissions
        if (!$content->canEdit(Auth::user()) && Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $content
        ]);
    }

    /**
     * Update the specified content item
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $content = Content::findOrFail($id);

        // Check permissions
        if (!$content->canEdit(Auth::user()) && Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|string|in:page,post,announcement,news',
            'slug' => 'nullable|string|unique:contents,slug,' . $content->id,
            'meta_data' => 'nullable|array',
            'template' => 'nullable|string',
            'is_featured' => 'boolean',
            'status' => 'nullable|in:draft,published,archived',
            'change_summary' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Create new version if content has changed
        $hasContentChanged = $content->title !== $data['title'] || 
                           $content->content !== $data['content'] ||
                           $content->meta_data !== ($data['meta_data'] ?? null);

        if ($hasContentChanged) {
            $version = $content->createVersion($data, Auth::user());
            
            // If status is published, publish the new version
            if (isset($data['status']) && $data['status'] === 'published') {
                $content->publishVersion($version);
            } else {
                // Update content without publishing
                $content->update($data);
            }
        } else {
            // Just update metadata and status
            $content->update($data);
        }

        $content->load(['author', 'currentVersion', 'versions.createdBy']);

        return response()->json([
            'success' => true,
            'message' => 'Content updated successfully',
            'data' => $content
        ]);
    }

    /**
     * Remove the specified content item
     */
    public function destroy(string $id): JsonResponse
    {
        $content = Content::findOrFail($id);

        // Check permissions
        if (!$content->canEdit(Auth::user()) && Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $content->delete();

        return response()->json([
            'success' => true,
            'message' => 'Content deleted successfully'
        ]);
    }

    /**
     * Get content versions
     */
    public function versions(string $id): JsonResponse
    {
        $content = Content::findOrFail($id);

        // Check permissions
        if (!$content->canEdit(Auth::user()) && Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $versions = $content->versions()->with('createdBy')->get();

        return response()->json([
            'success' => true,
            'data' => $versions
        ]);
    }

    /**
     * Publish a specific version
     */
    public function publishVersion(Request $request, string $id): JsonResponse
    {
        $content = Content::findOrFail($id);

        // Check permissions
        if (!$content->canEdit(Auth::user()) && Auth::user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'version_id' => 'required|exists:content_versions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $version = ContentVersion::where('id', $request->version_id)
                                ->where('content_id', $content->id)
                                ->firstOrFail();

        $content->publishVersion($version);
        $content->load(['author', 'currentVersion', 'versions.createdBy']);

        return response()->json([
            'success' => true,
            'message' => 'Version published successfully',
            'data' => $content
        ]);
    }

    /**
     * Get content types
     */
    public function types(): JsonResponse
    {
        $types = [
            'page' => 'Page',
            'post' => 'Blog Post',
            'announcement' => 'Announcement',
            'news' => 'News Article'
        ];

        return response()->json([
            'success' => true,
            'data' => $types
        ]);
    }

    /**
     * Get content templates
     */
    public function templates(): JsonResponse
    {
        $templates = [
            'default' => 'Default Template',
            'full-width' => 'Full Width',
            'sidebar' => 'With Sidebar',
            'landing' => 'Landing Page'
        ];

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    /**
     * Bulk operations
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:publish,unpublish,archive,delete',
            'content_ids' => 'required|array|min:1',
            'content_ids.*' => 'exists:contents,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $contentIds = $request->content_ids;
        $action = $request->action;
        $user = Auth::user();

        // Check permissions for all content items
        $contents = Content::whereIn('id', $contentIds)->get();
        foreach ($contents as $content) {
            if (!$content->canEdit($user) && $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to some content items'
                ], 403);
            }
        }

        $updated = 0;
        foreach ($contents as $content) {
            switch ($action) {
                case 'publish':
                    if ($content->status !== 'published') {
                        $content->update([
                            'status' => 'published',
                            'published_at' => now()
                        ]);
                        $updated++;
                    }
                    break;
                case 'unpublish':
                    if ($content->status === 'published') {
                        $content->update(['status' => 'draft']);
                        $updated++;
                    }
                    break;
                case 'archive':
                    if ($content->status !== 'archived') {
                        $content->update(['status' => 'archived']);
                        $updated++;
                    }
                    break;
                case 'delete':
                    $content->delete();
                    $updated++;
                    break;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$updated} content items {$action}d successfully"
        ]);
    }
}
