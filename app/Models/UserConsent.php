<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserConsent extends Model
{
    protected $fillable = [
        'user_id',
        'consent_type',
        'purposes',
        'granted_at',
        'revoked_at',
        'revocation_reason',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'purposes' => 'array',
    ];

    /**
     * Get the user that owns the consent
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if consent is currently active
     */
    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * Check if consent covers a specific purpose
     */
    public function coversPurpose(string $purpose): bool
    {
        return in_array($purpose, $this->purposes ?? []);
    }

    /**
     * Scope to get active consents
     */
    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * Scope to get consents for specific purpose
     */
    public function scopeForPurpose($query, string $purpose)
    {
        return $query->whereJsonContains('purposes', $purpose);
    }

    /**
     * Get consent types
     */
    public static function getConsentTypes(): array
    {
        return [
            'data_processing' => 'Data Processing',
            'marketing' => 'Marketing Communications',
            'analytics' => 'Analytics and Statistics',
            'third_party' => 'Third Party Services',
        ];
    }

    /**
     * Get available purposes
     */
    public static function getAvailablePurposes(): array
    {
        return [
            'academic_records' => 'Academic Records Management',
            'communication' => 'Educational Communications',
            'analytics' => 'Usage Analytics',
            'system_logs' => 'System Logging',
            'marketing' => 'Marketing Communications',
            'third_party_integrations' => 'Third Party Service Integrations',
        ];
    }
}
