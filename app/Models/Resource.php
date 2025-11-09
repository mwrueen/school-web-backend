<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class Resource extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'file_name',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'resource_type',
        'is_public',
        'subject_id',
        'class_id',
        'teacher_id'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'file_size' => 'integer',
    ];

    // Define resource types
    const TYPE_DOCUMENT = 'document';
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    const TYPE_AUDIO = 'audio';
    const TYPE_PRESENTATION = 'presentation';
    const TYPE_SPREADSHEET = 'spreadsheet';
    const TYPE_SYLLABUS = 'syllabus';
    const TYPE_OTHER = 'other';

    public static function getResourceTypes(): array
    {
        return [
            self::TYPE_DOCUMENT,
            self::TYPE_IMAGE,
            self::TYPE_VIDEO,
            self::TYPE_AUDIO,
            self::TYPE_PRESENTATION,
            self::TYPE_SPREADSHEET,
            self::TYPE_SYLLABUS,
            self::TYPE_OTHER,
        ];
    }

    // Define allowed file types for security
    public static function getAllowedMimeTypes(): array
    {
        return [
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'text/rtf',
            
            // Spreadsheets
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            
            // Presentations
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            
            // Images
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            
            // Audio
            'audio/mpeg',
            'audio/wav',
            'audio/ogg',
            'audio/mp4',
            
            // Video
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/webm',
            
            // Archives
            'application/zip',
            'application/x-rar-compressed',
        ];
    }

    public static function getMaxFileSize(): int
    {
        return 50 * 1024 * 1024; // 50MB in bytes
    }

    // Relationships
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function class()
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    // Scopes
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('resource_type', $type);
    }

    public function scopeBySubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeByClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }

    public function scopeByTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where('teacher_id', $teacherId);
    }

    // Accessors & Mutators
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getFileUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    public function getDownloadUrlAttribute(): string
    {
        return route('resources.download', $this->id);
    }

    public function getIsImageAttribute(): bool
    {
        return $this->resource_type === self::TYPE_IMAGE;
    }

    public function getIsDocumentAttribute(): bool
    {
        return in_array($this->resource_type, [
            self::TYPE_DOCUMENT,
            self::TYPE_PRESENTATION,
            self::TYPE_SPREADSHEET
        ]);
    }

    public function getIsMediaAttribute(): bool
    {
        return in_array($this->resource_type, [
            self::TYPE_VIDEO,
            self::TYPE_AUDIO
        ]);
    }

    // Methods
    public function makePublic(): bool
    {
        $this->is_public = true;
        return $this->save();
    }

    public function makePrivate(): bool
    {
        $this->is_public = false;
        return $this->save();
    }

    public function deleteFile(): bool
    {
        if (Storage::exists($this->file_path)) {
            return Storage::delete($this->file_path);
        }
        return true;
    }

    public static function determineResourceType(string $mimeType): string
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'text/rtf',
        ];

        $spreadsheetTypes = [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
        ];

        $presentationTypes = [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        $imageTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ];

        $audioTypes = [
            'audio/mpeg',
            'audio/wav',
            'audio/ogg',
            'audio/mp4',
        ];

        $videoTypes = [
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/webm',
        ];

        if (in_array($mimeType, $documentTypes)) {
            return self::TYPE_DOCUMENT;
        } elseif (in_array($mimeType, $spreadsheetTypes)) {
            return self::TYPE_SPREADSHEET;
        } elseif (in_array($mimeType, $presentationTypes)) {
            return self::TYPE_PRESENTATION;
        } elseif (in_array($mimeType, $imageTypes)) {
            return self::TYPE_IMAGE;
        } elseif (in_array($mimeType, $audioTypes)) {
            return self::TYPE_AUDIO;
        } elseif (in_array($mimeType, $videoTypes)) {
            return self::TYPE_VIDEO;
        }

        return self::TYPE_OTHER;
    }

    // Boot method to handle model events
    protected static function boot()
    {
        parent::boot();

        // Delete file when resource is deleted
        static::deleting(function ($resource) {
            $resource->deleteFile();
        });
    }
}
