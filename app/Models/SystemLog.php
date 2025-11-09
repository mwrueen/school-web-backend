<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SystemLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'level',
        'type',
        'message',
        'context',
        'file',
        'line',
        'stack_trace',
        'user_id',
        'ip_address',
        'user_agent',
        'request_id',
        'session_id',
        'metadata',
        'logged_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'logged_at' => 'datetime'
    ];

    // Log levels
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    // Log types
    const TYPE_ERROR = 'error';
    const TYPE_SECURITY = 'security';
    const TYPE_PERFORMANCE = 'performance';
    const TYPE_SYSTEM = 'system';

    // Scopes
    public function scopeErrors($query)
    {
        return $query->where('level', self::LEVEL_ERROR);
    }

    public function scopeWarnings($query)
    {
        return $query->where('level', self::LEVEL_WARNING);
    }

    public function scopeSecurityEvents($query)
    {
        return $query->where('type', self::TYPE_SECURITY);
    }

    public function scopePerformanceIssues($query)
    {
        return $query->where('type', self::TYPE_PERFORMANCE);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('logged_at', [$startDate, $endDate]);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
