<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_GRADUATED = 'graduated';
    const STATUS_TRANSFERRED = 'transferred';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'student_id',
        'grade_level',
        'parent_name',
        'parent_email',
        'parent_phone',
        'address',
        'date_of_birth',
        'enrollment_date',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'enrollment_date' => 'date',
    ];

    /**
     * Get the classes that the student belongs to.
     */
    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classes::class, 'class_student', 'student_id', 'classes_id');
    }

    /**
     * Get the assignments for the student.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Check if student is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if student has graduated
     */
    public function hasGraduated(): bool
    {
        return $this->status === self::STATUS_GRADUATED;
    }

    /**
     * Get student's full name with student ID
     */
    public function getFullIdentifierAttribute(): string
    {
        return "{$this->name} ({$this->student_id})";
    }

    /**
     * Scope to get active students only
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get students by grade level
     */
    public function scopeByGrade($query, $gradeLevel)
    {
        return $query->where('grade_level', $gradeLevel);
    }
}
