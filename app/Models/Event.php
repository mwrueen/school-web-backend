<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'event_date',
        'end_date',
        'location',
        'type',
        'is_public',
        'all_day',
        'created_by'
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'end_date' => 'datetime',
        'is_public' => 'boolean',
        'all_day' => 'boolean',
    ];

    // Define event types
    const TYPE_ACADEMIC = 'academic';
    const TYPE_CULTURAL = 'cultural';
    const TYPE_SPORTS = 'sports';
    const TYPE_HOLIDAY = 'holiday';
    const TYPE_MEETING = 'meeting';
    const TYPE_EXAMINATION = 'examination';

    public static function getTypes(): array
    {
        return [
            self::TYPE_ACADEMIC,
            self::TYPE_CULTURAL,
            self::TYPE_SPORTS,
            self::TYPE_HOLIDAY,
            self::TYPE_MEETING,
            self::TYPE_EXAMINATION,
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

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('event_date', '>=', now());
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('event_date', '<', now());
    }

    public function scopeInDateRange(Builder $query, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query->whereBetween('event_date', [$startDate, $endDate]);
    }

    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        
        return $query->whereBetween('event_date', [$startDate, $endDate]);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('event_date', today());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('event_date', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    // Accessors & Mutators
    public function getIsUpcomingAttribute(): bool
    {
        return $this->event_date >= now();
    }

    public function getIsPastAttribute(): bool
    {
        return $this->event_date < now();
    }

    public function getIsTodayAttribute(): bool
    {
        return $this->event_date->isToday();
    }

    public function getDurationAttribute(): ?int
    {
        if (!$this->end_date) {
            return null;
        }
        
        return $this->event_date->diffInMinutes($this->end_date);
    }

    public function getFormattedDateAttribute(): string
    {
        if ($this->all_day) {
            return $this->event_date->format('M j, Y');
        }
        
        return $this->event_date->format('M j, Y g:i A');
    }

    public function getFormattedDateRangeAttribute(): string
    {
        if (!$this->end_date) {
            return $this->formatted_date;
        }

        if ($this->all_day) {
            if ($this->event_date->isSameDay($this->end_date)) {
                return $this->event_date->format('M j, Y');
            }
            return $this->event_date->format('M j') . ' - ' . $this->end_date->format('M j, Y');
        }

        if ($this->event_date->isSameDay($this->end_date)) {
            return $this->event_date->format('M j, Y g:i A') . ' - ' . $this->end_date->format('g:i A');
        }

        return $this->event_date->format('M j, Y g:i A') . ' - ' . $this->end_date->format('M j, Y g:i A');
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

    public function isConflictingWith(Event $otherEvent): bool
    {
        if (!$this->end_date || !$otherEvent->end_date) {
            return false;
        }

        return $this->event_date < $otherEvent->end_date && 
               $this->end_date > $otherEvent->event_date;
    }
}
