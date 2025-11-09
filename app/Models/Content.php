<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Content extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'slug',
        'content',
        'type',
        'status',
        'meta_data',
        'template',
        'sort_order',
        'is_featured',
        'published_at',
        'author_id',
        'current_version_id'
    ];

    protected $casts = [
        'meta_data' => 'array',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
        'sort_order' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($content) {
            if (empty($content->slug)) {
                $content->slug = Str::slug($content->title);
            }
        });
        
        static::updating(function ($content) {
            if ($content->isDirty('title') && empty($content->slug)) {
                $content->slug = Str::slug($content->title);
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContentVersion::class)->orderBy('version_number', 'desc');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(ContentVersion::class, 'id', 'current_version_id');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(ContentVersion::class)->latest('version_number');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->where('published_at', '<=', now());
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    // Helper methods
    public function isPublished(): bool
    {
        return $this->status === 'published' && 
               $this->published_at && 
               $this->published_at <= now();
    }

    public function canEdit(User $user): bool
    {
        return $user->role === 'admin' || $user->id === $this->author_id;
    }

    public function createVersion(array $data, User $user): ContentVersion
    {
        $latestVersion = $this->versions()->first();
        $versionNumber = $latestVersion ? $latestVersion->version_number + 1 : 1;

        $version = $this->versions()->create([
            'title' => $data['title'] ?? $this->title,
            'content' => $data['content'] ?? $this->content,
            'meta_data' => $data['meta_data'] ?? $this->meta_data,
            'template' => $data['template'] ?? $this->template,
            'version_number' => $versionNumber,
            'change_summary' => $data['change_summary'] ?? null,
            'created_by' => $user->id,
            'is_current' => false
        ]);

        return $version;
    }

    public function publishVersion(ContentVersion $version): void
    {
        // Mark all versions as not current
        $this->versions()->update(['is_current' => false]);
        
        // Mark the specified version as current
        $version->update(['is_current' => true]);
        
        // Update the content with version data
        $this->update([
            'title' => $version->title,
            'content' => $version->content,
            'meta_data' => $version->meta_data,
            'template' => $version->template,
            'current_version_id' => $version->id,
            'status' => 'published',
            'published_at' => now()
        ]);
    }
}
