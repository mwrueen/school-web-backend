<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'grade_levels',
        'credits',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'grade_levels' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the classes that teach this subject.
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classes::class, 'class_subject');
    }

    /**
     * Get the assignments for this subject.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Get the resources for this subject.
     */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    /**
     * Check if subject is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if subject is taught in a specific grade level
     */
    public function isTaughtInGrade(string $gradeLevel): bool
    {
        return in_array($gradeLevel, $this->grade_levels ?? []);
    }

    /**
     * Scope to get active subjects only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get subjects by grade level
     */
    public function scopeForGrade($query, string $gradeLevel)
    {
        return $query->whereJsonContains('grade_levels', $gradeLevel);
    }
}
