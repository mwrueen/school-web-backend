<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'type',
        'is_public',
        'published_at',
        'created_by'
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $dates = [
        'published_at',
    ];

    // Define announcement types
    const TYPE_NEWS = 'news';
    const TYPE_NOTICE = 'notice';
    const TYPE_RESULT = 'result';
    const TYPE_HOLIDAY = 'holiday';
    const TYPE_EVENT = 'event';
    const TYPE_ACHIEVEMENT = 'achievement';

    public static function getTypes(): array
    {
        return [
            self::TYPE_NEWS,
            self::TYPE_NOTICE,
            self::TYPE_RESULT,
            self::TYPE_HOLIDAY,
            self::TYPE_EVENT,
            self::TYPE_ACHIEVEMENT,
        ];
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
                    ->where('published_at', '<=', now());
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('published_at', '>=', now()->subDays($days));
    }

    // Accessors & Mutators
    public function getIsPublishedAttribute(): bool
    {
        return $this->published_at !== null && $this->published_at <= now();
    }

    public function getExcerptAttribute(): string
    {
        return str_limit(strip_tags($this->content), 150);
    }

    // Methods
    public function publish(): bool
    {
        $this->published_at = now();
        return $this->save();
    }

    public function unpublish(): bool
    {
        $this->published_at = null;
        return $this->save();
    }

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
}
