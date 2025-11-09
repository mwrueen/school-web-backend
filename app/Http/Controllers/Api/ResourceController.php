<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResourceController extends Controller
{
    /**
     * Display a listing of resources (admin/teacher view)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Resource::with(['subject', 'class', 'teacher']);

        // Filter by resource type
        if ($request->has('resource_type')) {
            $query->byType($request->resource_type);
        }

        // Filter by subject
        if ($request->has('subject_id')) {
            $query->bySubject($request->subject_id);
        }

        // Filter by class
        if ($request->has('class_id')) {
            $query->byClass($request->class_id);
        }

        // Filter by teacher
        if ($request->has('teacher_id')) {
            $query->byTeacher($request->teacher_id);
        }

        // Filter by visibility
        if ($request->has('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }

        // Search by title or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%");
            });
        }

        $resources = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $resources,
        ]);
    }

    /**
     * Display public resources (for public website)
     */
    public function public(Request $request): JsonResponse
    {
        $query = Resource::public()->with(['subject', 'class']);

        // Filter by resource type
        if ($request->has('resource_type')) {
            $query->byType($request->resource_type);
        }

        // Filter by subject
        if ($request->has('subject_id')) {
            $query->bySubject($request->subject_id);
        }

        // Filter by class
        if ($request->has('class_id')) {
            $query->byClass($request->class_id);
        }

        $resources = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $resources,
        ]);
    }

    /**
     * Store a newly created resource with file upload
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'file' => [
                'required',
                'file',
                'max:' . (Resource::getMaxFileSize() / 1024), // Convert to KB for validation
                'mimes:pdf,doc,docx,txt,rtf,xls,xlsx,csv,ppt,pptx,jpg,jpeg,png,gif,webp,svg,mp3,wav,ogg,mp4,mov,webm,zip,rar',
            ],
            'subject_id' => 'nullable|exists:subjects,id',
            'class_id' => 'nullable|exists:classes,id',
            'is_public' => 'boolean',
        ]);

        $file = $request->file('file');
        
        // Security checks
        if (!$this->isFileSecure($file)) {
            return response()->json([
                'success' => false,
                'message' => 'File failed security validation',
            ], 422);
        }

        // Generate unique filename
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;
        
        // Store file in private storage
        $filePath = $file->storeAs('resources', $filename, 'private');
        
        if (!$filePath) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
            ], 500);
        }

        // Determine resource type based on MIME type
        $mimeType = $file->getMimeType();
        $resourceType = Resource::determineResourceType($mimeType);

        $resource = Resource::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'file_name' => $originalName,
            'file_path' => $filePath,
            'file_type' => $extension,
            'mime_type' => $mimeType,
            'file_size' => $file->getSize(),
            'resource_type' => $resourceType,
            'is_public' => $validated['is_public'] ?? false,
            'subject_id' => $validated['subject_id'] ?? null,
            'class_id' => $validated['class_id'] ?? null,
            'teacher_id' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Resource uploaded successfully',
            'data' => $resource->load(['subject', 'class', 'teacher']),
        ], 201);
    }

    /**
     * Display the specified resource
     */
    public function show(string $id): JsonResponse
    {
        $resource = Resource::with(['subject', 'class', 'teacher'])->findOrFail($id);

        // Check if user can view this resource
        if (!$resource->is_public && !Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $resource,
        ]);
    }

    /**
     * Update the specified resource
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $resource = Resource::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'subject_id' => 'nullable|exists:subjects,id',
            'class_id' => 'nullable|exists:classes,id',
            'is_public' => 'sometimes|boolean',
        ]);

        $resource->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Resource updated successfully',
            'data' => $resource->load(['subject', 'class', 'teacher']),
        ]);
    }

    /**
     * Remove the specified resource
     */
    public function destroy(string $id): JsonResponse
    {
        $resource = Resource::findOrFail($id);
        
        // Delete the file and the record
        $resource->delete();

        return response()->json([
            'success' => true,
            'message' => 'Resource deleted successfully',
        ]);
    }

    /**
     * Download the specified resource file
     */
    public function download(string $id): StreamedResponse
    {
        $resource = Resource::findOrFail($id);

        // Check if user can download this resource
        if (!$resource->is_public && !Auth::check()) {
            abort(404);
        }

        if (!Storage::disk('private')->exists($resource->file_path)) {
            abort(404, 'File not found');
        }

        return Storage::disk('private')->download(
            $resource->file_path,
            $resource->file_name
        );
    }

    /**
     * Get resource types
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Resource::getResourceTypes(),
        ]);
    }

    /**
     * Get allowed file types and size limits
     */
    public function uploadLimits(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'allowed_mime_types' => Resource::getAllowedMimeTypes(),
                'max_file_size' => Resource::getMaxFileSize(),
                'max_file_size_human' => $this->formatBytes(Resource::getMaxFileSize()),
            ],
        ]);
    }

    /**
     * Security validation for uploaded files
     */
    private function isFileSecure($file): bool
    {
        // Check if file is actually uploaded
        if (!$file->isValid()) {
            return false;
        }

        // Check MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, Resource::getAllowedMimeTypes())) {
            return false;
        }

        // Check file size
        if ($file->getSize() > Resource::getMaxFileSize()) {
            return false;
        }

        // Additional security checks can be added here
        // e.g., virus scanning, content validation, etc.

        return true;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
