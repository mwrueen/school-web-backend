<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Analytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'page_url',
        'page_title',
        'referrer',
        'user_agent',
        'ip_address',
        'session_id',
        'metadata',
        'viewed_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'viewed_at' => 'datetime'
    ];

    // Scopes for common queries
    public function scopePageViews($query)
    {
        return $query->where('event_type', 'page_view');
    }

    public function scopeContentViews($query)
    {
        return $query->where('event_type', 'content_view');
    }

    public function scopeDownloads($query)
    {
        return $query->where('event_type', 'download');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('viewed_at', [$startDate, $endDate]);
    }

    public function scopeForPage($query, $pageUrl)
    {
        return $query->where('page_url', $pageUrl);
    }
}
