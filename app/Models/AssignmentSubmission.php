<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AssignmentSubmission extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_GRADED = 'graded';
    const STATUS_RETURNED = 'returned';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assignment_id',
        'student_id',
        'content',
        'attachments',
        'submitted_at',
        'is_late',
        'grade',
        'points_earned',
        'feedback',
        'graded_by',
        'graded_at',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'is_late' => 'boolean',
        'attachments' => 'array',
        'grade' => 'decimal:2',
        'points_earned' => 'integer',
    ];

    /**
     * Get the assignment that owns the submission.
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Get the student that owns the submission.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the teacher who graded the submission.
     */
    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /**
     * Check if submission is submitted (not draft)
     */
    public function isSubmitted(): bool
    {
        return $this->status !== self::STATUS_DRAFT;
    }

    /**
     * Check if submission has been graded
     */
    public function isGraded(): bool
    {
        return $this->status === self::STATUS_GRADED || $this->status === self::STATUS_RETURNED;
    }

    /**
     * Check if submission was submitted late
     */
    public function wasSubmittedLate(): bool
    {
        return $this->is_late;
    }

    /**
     * Calculate and set if submission is late based on assignment due date
     */
    public function calculateLateness(): void
    {
        if ($this->submitted_at && $this->assignment) {
            $this->is_late = $this->submitted_at->gt($this->assignment->due_date);
        }
    }

    /**
     * Calculate points earned based on grade and max points
     */
    public function calculatePointsEarned(): void
    {
        if ($this->grade !== null && $this->assignment) {
            $maxPoints = $this->assignment->max_points;
            $this->points_earned = round(($this->grade / 100) * $maxPoints);
            
            // Apply late penalty if applicable
            if ($this->is_late && $this->assignment->late_penalty_percent > 0) {
                $penalty = ($this->assignment->late_penalty_percent / 100) * $this->points_earned;
                $this->points_earned = max(0, $this->points_earned - $penalty);
            }
        }
    }

    /**
     * Get the percentage grade
     */
    public function getPercentageGradeAttribute(): ?float
    {
        return $this->grade;
    }

    /**
     * Get letter grade based on percentage
     */
    public function getLetterGradeAttribute(): ?string
    {
        if ($this->grade === null) {
            return null;
        }

        if ($this->grade >= 90) return 'A';
        if ($this->grade >= 80) return 'B';
        if ($this->grade >= 70) return 'C';
        if ($this->grade >= 60) return 'D';
        return 'F';
    }

    /**
     * Get days late (0 if not late)
     */
    public function getDaysLateAttribute(): int
    {
        if (!$this->is_late || !$this->submitted_at || !$this->assignment) {
            return 0;
        }

        return $this->assignment->due_date->diffInDays($this->submitted_at, false);
    }

    /**
     * Submit the assignment (change status from draft to submitted)
     */
    public function submit(): bool
    {
        if ($this->status !== self::STATUS_DRAFT) {
            return false;
        }

        $this->submitted_at = Carbon::now();
        $this->calculateLateness();
        $this->status = self::STATUS_SUBMITTED;
        
        return $this->save();
    }

    /**
     * Grade the submission
     */
    public function grade(float $grade, ?string $feedback = null, ?int $gradedBy = null): bool
    {
        if (!$this->isSubmitted()) {
            return false;
        }

        $this->grade = max(0, min(100, $grade)); // Ensure grade is between 0-100
        $this->feedback = $feedback;
        $this->graded_by = $gradedBy;
        $this->graded_at = Carbon::now();
        $this->status = self::STATUS_GRADED;
        
        $this->calculatePointsEarned();
        
        return $this->save();
    }

    /**
     * Return the graded submission to student
     */
    public function returnToStudent(): bool
    {
        if (!$this->isGraded()) {
            return false;
        }

        $this->status = self::STATUS_RETURNED;
        return $this->save();
    }

    /**
     * Scope to get submitted assignments only
     */
    public function scopeSubmitted($query)
    {
        return $query->where('status', '!=', self::STATUS_DRAFT);
    }

    /**
     * Scope to get graded submissions only
     */
    public function scopeGraded($query)
    {
        return $query->whereIn('status', [self::STATUS_GRADED, self::STATUS_RETURNED]);
    }

    /**
     * Scope to get late submissions only
     */
    public function scopeLate($query)
    {
        return $query->where('is_late', true);
    }

    /**
     * Scope to get submissions by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get submissions for a specific assignment
     */
    public function scopeForAssignment($query, int $assignmentId)
    {
        return $query->where('assignment_id', $assignmentId);
    }

    /**
     * Scope to get submissions by a specific student
     */
    public function scopeByStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Scope to get submissions graded by a specific teacher
     */
    public function scopeGradedByTeacher($query, int $teacherId)
    {
        return $query->where('graded_by', $teacherId);
    }
}
