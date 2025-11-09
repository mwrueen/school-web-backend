<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class MediaController extends Controller
{


    /**
     * Display a listing of media files
     */
    public function index(Request $request): JsonResponse
    {
        $directory = $request->get('directory', 'media');
        $type = $request->get('type'); // image, document, video, etc.

        try {
            $files = Storage::disk('public')->files($directory);
            $mediaFiles = [];

            foreach ($files as $file) {
                $fileInfo = $this->getFileInfo($file);
                
                // Filter by type if specified
                if ($type && $fileInfo['type'] !== $type) {
                    continue;
                }

                $mediaFiles[] = $fileInfo;
            }

            // Sort by last modified
            usort($mediaFiles, function ($a, $b) {
                return $b['modified'] <=> $a['modified'];
            });

            return response()->json([
                'success' => true,
                'data' => $mediaFiles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve media files',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload a new media file
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'directory' => 'nullable|string',
            'alt_text' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $directory = $request->get('directory', 'media');
            
            // Validate file type
            $allowedTypes = [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain', 'text/csv'
            ];

            if (!in_array($file->getMimeType(), $allowedTypes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File type not allowed'
                ], 422);
            }

            // Generate unique filename
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $filename = Str::slug($originalName) . '_' . time() . '.' . $extension;

            // Store file
            $path = $file->storeAs($directory, $filename, 'public');
            
            $fileInfo = $this->getFileInfo($path);
            $fileInfo['alt_text'] = $request->get('alt_text');
            $fileInfo['caption'] = $request->get('caption');

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $fileInfo
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified media file info
     */
    public function show(string $filename): JsonResponse
    {
        try {
            $path = 'media/' . $filename;
            
            if (!Storage::disk('public')->exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            $fileInfo = $this->getFileInfo($path);

            return response()->json([
                'success' => true,
                'data' => $fileInfo
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve file info',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified media file
     */
    public function destroy(string $filename): JsonResponse
    {
        try {
            $path = 'media/' . $filename;
            
            if (!Storage::disk('public')->exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            Storage::disk('public')->delete($path);

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a directory
     */
    public function createDirectory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'parent' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $parent = $request->get('parent', 'media');
            $name = Str::slug($request->get('name'));
            $path = $parent . '/' . $name;

            if (Storage::disk('public')->exists($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Directory already exists'
                ], 422);
            }

            Storage::disk('public')->makeDirectory($path);

            return response()->json([
                'success' => true,
                'message' => 'Directory created successfully',
                'data' => [
                    'name' => $name,
                    'path' => $path
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create directory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get directories
     */
    public function directories(Request $request): JsonResponse
    {
        try {
            $parent = $request->get('parent', 'media');
            $directories = Storage::disk('public')->directories($parent);

            $dirList = [];
            foreach ($directories as $dir) {
                $dirList[] = [
                    'name' => basename($dir),
                    'path' => $dir,
                    'full_path' => Storage::disk('public')->path($dir)
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $dirList
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve directories',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete files
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array|min:1',
            'files.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $files = $request->get('files');
            $deleted = 0;
            $errors = [];

            foreach ($files as $filename) {
                $path = 'media/' . $filename;
                
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                    $deleted++;
                } else {
                    $errors[] = "File not found: {$filename}";
                }
            }

            return response()->json([
                'success' => true,
                'message' => "{$deleted} files deleted successfully",
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete files',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file information
     */
    private function getFileInfo(string $path): array
    {
        $disk = Storage::disk('public');
        $filename = basename($path);
        $size = $disk->size($path);
        $lastModified = $disk->lastModified($path);
        $mimeType = $disk->mimeType($path);
        
        // Determine file type category
        $type = 'document';
        if (str_starts_with($mimeType, 'image/')) {
            $type = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $type = 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            $type = 'audio';
        }

        return [
            'filename' => $filename,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'size' => $size,
            'size_human' => $this->formatBytes($size),
            'type' => $type,
            'mime_type' => $mimeType,
            'modified' => $lastModified,
            'modified_human' => date('Y-m-d H:i:s', $lastModified)
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
