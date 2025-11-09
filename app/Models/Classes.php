<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classes extends Model
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
        'grade_level',
        'section',
        'academic_year',
        'teacher_id',
        'description',
        'max_students',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'max_students' => 'integer',
    ];

    /**
     * Get the teacher that owns the class.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the subjects taught in this class.
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject');
    }

    /**
     * Get the students enrolled in this class.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'class_student', 'classes_id', 'student_id');
    }

    /**
     * Get the assignments for this class.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'class_id');
    }

    /**
     * Get the resources for this class.
     */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class, 'class_id');
    }

    /**
     * Get the schedules for this class.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'class_id');
    }

    /**
     * Check if class is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if class has reached maximum capacity
     */
    public function isFull(): bool
    {
        return $this->students()->count() >= $this->max_students;
    }

    /**
     * Get available spots in the class
     */
    public function getAvailableSpotsAttribute(): int
    {
        return max(0, $this->max_students - $this->students()->count());
    }

    /**
     * Get class full name with grade and section
     */
    public function getFullNameAttribute(): string
    {
        return "Grade {$this->grade_level} - Section {$this->section}";
    }

    /**
     * Scope to get active classes only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get classes by grade level
     */
    public function scopeByGrade($query, string $gradeLevel)
    {
        return $query->where('grade_level', $gradeLevel);
    }

    /**
     * Scope to get classes by academic year
     */
    public function scopeByAcademicYear($query, string $academicYear)
    {
        return $query->where('academic_year', $academicYear);
    }

    /**
     * Scope to get classes by teacher
     */
    public function scopeByTeacher($query, int $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }
}
