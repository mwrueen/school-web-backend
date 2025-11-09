<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentVersion extends Model
{
    protected $fillable = [
        'content_id',
        'title',
        'content',
        'meta_data',
        'template',
        'version_number',
        'change_summary',
        'created_by',
        'is_current'
    ];

    protected $casts = [
        'meta_data' => 'array',
        'is_current' => 'boolean',
        'version_number' => 'integer'
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeByVersion($query, $versionNumber)
    {
        return $query->where('version_number', $versionNumber);
    }

    // Helper methods
    public function isCurrent(): bool
    {
        return $this->is_current;
    }

    public function getVersionLabel(): string
    {
        return "v{$this->version_number}";
    }

    public function getDifferences(ContentVersion $otherVersion): array
    {
        $differences = [];

        if ($this->title !== $otherVersion->title) {
            $differences['title'] = [
                'old' => $otherVersion->title,
                'new' => $this->title
            ];
        }

        if ($this->content !== $otherVersion->content) {
            $differences['content'] = [
                'old' => $otherVersion->content,
                'new' => $this->content
            ];
        }

        if ($this->meta_data !== $otherVersion->meta_data) {
            $differences['meta_data'] = [
                'old' => $otherVersion->meta_data,
                'new' => $this->meta_data
            ];
        }

        return $differences;
    }
}
