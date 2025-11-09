<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Assignment extends Model
{
    use HasFactory;

    const TYPE_HOMEWORK = 'homework';
    const TYPE_QUIZ = 'quiz';
    const TYPE_EXAM = 'exam';
    const TYPE_PROJECT = 'project';
    const TYPE_LAB = 'lab';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'instructions',
        'class_id',
        'subject_id',
        'teacher_id',
        'type',
        'max_points',
        'due_date',
        'available_from',
        'available_until',
        'allow_late_submission',
        'late_penalty_percent',
        'attachments',
        'is_published',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'due_date' => 'datetime',
        'available_from' => 'datetime',
        'available_until' => 'datetime',
        'allow_late_submission' => 'boolean',
        'is_published' => 'boolean',
        'attachments' => 'array',
        'max_points' => 'integer',
        'late_penalty_percent' => 'integer',
    ];

    /**
     * Get the class that owns the assignment.
     */
    public function class(): BelongsTo
    {
        return $this->belongsTo(Classes::class, 'class_id');
    }

    /**
     * Get the subject that owns the assignment.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the teacher that created the assignment.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get the submissions for this assignment.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /**
     * Check if assignment is currently available for submission
     */
    public function isAvailable(): bool
    {
        $now = Carbon::now();
        
        if (!$this->is_published) {
            return false;
        }
        
        if ($this->available_from && $now->lt($this->available_from)) {
            return false;
        }
        
        if ($this->available_until && $now->gt($this->available_until)) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if assignment is overdue
     */
    public function isOverdue(): bool
    {
        return Carbon::now()->gt($this->due_date);
    }

    /**
     * Check if late submissions are allowed and assignment is not past final deadline
     */
    public function canSubmitLate(): bool
    {
        if (!$this->allow_late_submission) {
            return false;
        }
        
        if ($this->available_until && Carbon::now()->gt($this->available_until)) {
            return false;
        }
        
        return true;
    }

    /**
     * Get the number of days until due date (negative if overdue)
     */
    public function getDaysUntilDueAttribute(): int
    {
        return Carbon::now()->diffInDays($this->due_date, false);
    }

    /**
     * Get submission statistics for this assignment
     */
    public function getSubmissionStats(): array
    {
        $totalStudents = $this->class->students()->count();
        $submittedCount = $this->submissions()->where('status', '!=', 'draft')->count();
        $gradedCount = $this->submissions()->whereNotNull('grade')->count();
        $lateCount = $this->submissions()->where('is_late', true)->count();
        
        return [
            'total_students' => $totalStudents,
            'submitted_count' => $submittedCount,
            'graded_count' => $gradedCount,
            'late_count' => $lateCount,
            'submission_rate' => $totalStudents > 0 ? round(($submittedCount / $totalStudents) * 100, 2) : 0,
            'grading_progress' => $submittedCount > 0 ? round(($gradedCount / $submittedCount) * 100, 2) : 0,
        ];
    }

    /**
     * Get average grade for this assignment
     */
    public function getAverageGrade(): ?float
    {
        return $this->submissions()
            ->whereNotNull('grade')
            ->avg('grade');
    }

    /**
     * Scope to get published assignments only
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope to get assignments by type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get assignments due within a date range
     */
    public function scopeDueBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('due_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get overdue assignments
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', Carbon::now());
    }

    /**
     * Scope to get assignments for a specific class
     */
    public function scopeForClass($query, int $classId)
    {
        return $query->where('class_id', $classId);
    }

    /**
     * Scope to get assignments for a specific subject
     */
    public function scopeForSubject($query, int $subjectId)
    {
        return $query->where('subject_id', $subjectId);
    }

    /**
     * Scope to get assignments by teacher
     */
    public function scopeByTeacher($query, int $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }
}
