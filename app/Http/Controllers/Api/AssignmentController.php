<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssignmentRequest;
use App\Http\Requests\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Classes;
use App\Models\Subject;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    /**
     * Get all assignments for the authenticated teacher
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isTeacher() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Teacher or admin role required.',
            ], 403);
        }

        $query = Assignment::with(['class', 'subject', 'teacher'])
            ->withCount(['submissions']);

        // If teacher, only show their assignments
        if ($user->isTeacher()) {
            $query->byTeacher($user->id);
        }

        // Apply filters
        if ($request->has('class_id')) {
            $query->forClass($request->class_id);
        }

        if ($request->has('subject_id')) {
            $query->forSubject($request->subject_id);
        }

        if ($request->has('type')) {
            $query->byType($request->type);
        }

        if ($request->has('status')) {
            switch ($request->status) {
                case 'published':
                    $query->published();
                    break;
                case 'draft':
                    $query->where('is_published', false);
                    break;
                case 'overdue':
                    $query->overdue()->published();
                    break;
                case 'upcoming':
                    $query->dueBetween(Carbon::now(), Carbon::now()->addDays(7))->published();
                    break;
            }
        }

        $assignments = $query->orderBy('due_date', 'desc')
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'type' => $assignment->type,
                    'max_points' => $assignment->max_points,
                    'due_date' => $assignment->due_date->toISOString(),
                    'is_published' => $assignment->is_published,
                    'is_overdue' => $assignment->isOverdue(),
                    'days_until_due' => $assignment->days_until_due,
                    'class' => [
                        'id' => $assignment->class->id,
                        'name' => $assignment->class->name,
                        'full_name' => $assignment->class->full_name,
                    ],
                    'subject' => [
                        'id' => $assignment->subject->id,
                        'name' => $assignment->subject->name,
                        'code' => $assignment->subject->code,
                    ],
                    'teacher' => [
                        'id' => $assignment->teacher->id,
                        'name' => $assignment->teacher->name,
                    ],
                    'submissions_count' => $assignment->submissions_count,
                    'submission_stats' => $assignment->getSubmissionStats(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    /**
     * Create a new assignment
     */
    public function store(StoreAssignmentRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        // Check if teacher has access to the class
        if ($user->isTeacher()) {
            $class = Classes::find($validated['class_id']);
            if ($class->teacher_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You can only create assignments for your own classes.',
                ], 403);
            }
        }

        $validated['teacher_id'] = $user->id;

        $assignment = Assignment::create($validated);
        $assignment->load(['class', 'subject', 'teacher']);

        return response()->json([
            'success' => true,
            'message' => 'Assignment created successfully.',
            'data' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'type' => $assignment->type,
                'max_points' => $assignment->max_points,
                'due_date' => $assignment->due_date->toISOString(),
                'is_published' => $assignment->is_published,
                'class' => [
                    'id' => $assignment->class->id,
                    'name' => $assignment->class->name,
                    'full_name' => $assignment->class->full_name,
                ],
                'subject' => [
                    'id' => $assignment->subject->id,
                    'name' => $assignment->subject->name,
                    'code' => $assignment->subject->code,
                ],
                'teacher' => [
                    'id' => $assignment->teacher->id,
                    'name' => $assignment->teacher->name,
                ],
            ],
        ], 201);
    }

    /**
     * Get a specific assignment
     */
    public function show(Assignment $assignment): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $assignment->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only view your own assignments.',
            ], 403);
        }

        $assignment->load(['class', 'subject', 'teacher', 'submissions.student'])
            ->loadCount(['submissions']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'instructions' => $assignment->instructions,
                'type' => $assignment->type,
                'max_points' => $assignment->max_points,
                'due_date' => $assignment->due_date->toISOString(),
                'available_from' => $assignment->available_from?->toISOString(),
                'available_until' => $assignment->available_until?->toISOString(),
                'allow_late_submission' => $assignment->allow_late_submission,
                'late_penalty_percent' => $assignment->late_penalty_percent,
                'attachments' => $assignment->attachments,
                'is_published' => $assignment->is_published,
                'is_available' => $assignment->isAvailable(),
                'is_overdue' => $assignment->isOverdue(),
                'days_until_due' => $assignment->days_until_due,
                'class' => [
                    'id' => $assignment->class->id,
                    'name' => $assignment->class->name,
                    'full_name' => $assignment->class->full_name,
                    'grade_level' => $assignment->class->grade_level,
                ],
                'subject' => [
                    'id' => $assignment->subject->id,
                    'name' => $assignment->subject->name,
                    'code' => $assignment->subject->code,
                ],
                'teacher' => [
                    'id' => $assignment->teacher->id,
                    'name' => $assignment->teacher->name,
                ],
                'submissions_count' => $assignment->submissions_count,
                'submission_stats' => $assignment->getSubmissionStats(),
                'average_grade' => $assignment->getAverageGrade(),
                'submissions' => $assignment->submissions->map(function ($submission) {
                    return [
                        'id' => $submission->id,
                        'student' => [
                            'id' => $submission->student->id,
                            'name' => $submission->student->name,
                            'student_id' => $submission->student->student_id,
                        ],
                        'status' => $submission->status,
                        'grade' => $submission->grade,
                        'feedback' => $submission->feedback,
                        'is_late' => $submission->is_late,
                        'submitted_at' => $submission->submitted_at?->toISOString(),
                        'graded_at' => $submission->graded_at?->toISOString(),
                    ];
                }),
            ],
        ]);
    } 
   /**
     * Update an assignment
     */
    public function update(UpdateAssignmentRequest $request, Assignment $assignment): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $assignment->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only update your own assignments.',
            ], 403);
        }

        $validated = $request->validated();

        // Check if teacher has access to the new class (if changing)
        if ($user->isTeacher() && isset($validated['class_id'])) {
            $class = Classes::find($validated['class_id']);
            if ($class->teacher_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. You can only assign to your own classes.',
                ], 403);
            }
        }

        $assignment->update($validated);
        $assignment->load(['class', 'subject', 'teacher']);

        return response()->json([
            'success' => true,
            'message' => 'Assignment updated successfully.',
            'data' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'type' => $assignment->type,
                'max_points' => $assignment->max_points,
                'due_date' => $assignment->due_date->toISOString(),
                'is_published' => $assignment->is_published,
                'class' => [
                    'id' => $assignment->class->id,
                    'name' => $assignment->class->name,
                    'full_name' => $assignment->class->full_name,
                ],
                'subject' => [
                    'id' => $assignment->subject->id,
                    'name' => $assignment->subject->name,
                    'code' => $assignment->subject->code,
                ],
            ],
        ]);
    }

    /**
     * Delete an assignment
     */
    public function destroy(Assignment $assignment): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $assignment->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only delete your own assignments.',
            ], 403);
        }

        // Check if assignment has submissions
        if ($assignment->submissions()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete assignment with submissions. Please remove submissions first.',
            ], 422);
        }

        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assignment deleted successfully.',
        ]);
    }

    /**
     * Publish an assignment
     */
    public function publish(Assignment $assignment): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $assignment->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only publish your own assignments.',
            ], 403);
        }

        $assignment->update(['is_published' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Assignment published successfully.',
            'data' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'is_published' => $assignment->is_published,
            ],
        ]);
    }

    /**
     * Unpublish an assignment
     */
    public function unpublish(Assignment $assignment): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $assignment->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only unpublish your own assignments.',
            ], 403);
        }

        $assignment->update(['is_published' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Assignment unpublished successfully.',
            'data' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'is_published' => $assignment->is_published,
            ],
        ]);
    }

    /**
     * Get submissions for an assignment
     */
    public function getSubmissions(Assignment $assignment): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $assignment->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only view submissions for your own assignments.',
            ], 403);
        }

        $submissions = $assignment->submissions()
            ->with(['student'])
            ->orderBy('submitted_at', 'desc')
            ->get()
            ->map(function ($submission) {
                return [
                    'id' => $submission->id,
                    'student' => [
                        'id' => $submission->student->id,
                        'name' => $submission->student->name,
                        'student_id' => $submission->student->student_id,
                        'email' => $submission->student->email,
                    ],
                    'status' => $submission->status,
                    'content' => $submission->content,
                    'attachments' => $submission->attachments,
                    'grade' => $submission->grade,
                    'feedback' => $submission->feedback,
                    'is_late' => $submission->is_late,
                    'submitted_at' => $submission->submitted_at?->toISOString(),
                    'graded_at' => $submission->graded_at?->toISOString(),
                    'created_at' => $submission->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'assignment_id' => $assignment->id,
                'assignment_title' => $assignment->title,
                'submissions' => $submissions,
                'total_submissions' => $submissions->count(),
                'submission_stats' => $assignment->getSubmissionStats(),
            ],
        ]);
    }

    /**
     * Grade a submission
     */
    public function gradeSubmission(Request $request, Assignment $assignment, AssignmentSubmission $submission): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $assignment->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only grade submissions for your own assignments.',
            ], 403);
        }

        // Verify submission belongs to assignment
        if ($submission->assignment_id !== $assignment->id) {
            return response()->json([
                'success' => false,
                'message' => 'Submission does not belong to this assignment.',
            ], 422);
        }

        $validated = $request->validate([
            'grade' => 'required|numeric|min:0|max:' . $assignment->max_points,
            'feedback' => 'nullable|string',
        ]);

        $submission->update([
            'grade' => $validated['grade'],
            'feedback' => $validated['feedback'] ?? null,
            'graded_at' => Carbon::now(),
            'graded_by' => $user->id,
        ]);

        $submission->load('student');

        return response()->json([
            'success' => true,
            'message' => 'Submission graded successfully.',
            'data' => [
                'id' => $submission->id,
                'student' => [
                    'id' => $submission->student->id,
                    'name' => $submission->student->name,
                    'student_id' => $submission->student->student_id,
                ],
                'grade' => $submission->grade,
                'feedback' => $submission->feedback,
                'graded_at' => $submission->graded_at->toISOString(),
            ],
        ]);
    }

    /**
     * Get grading queue (assignments with ungraded submissions)
     */
    public function gradingQueue(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isTeacher() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Teacher or admin role required.',
            ], 403);
        }

        $query = Assignment::with(['class', 'subject'])
            ->whereHas('submissions', function ($q) {
                $q->whereNull('grade')->where('status', '!=', 'draft');
            })
            ->withCount(['submissions' => function ($q) {
                $q->whereNull('grade')->where('status', '!=', 'draft');
            }]);

        // If teacher, only show their assignments
        if ($user->isTeacher()) {
            $query->byTeacher($user->id);
        }

        $assignments = $query->orderBy('due_date')
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'type' => $assignment->type,
                    'due_date' => $assignment->due_date->toISOString(),
                    'is_overdue' => $assignment->isOverdue(),
                    'class' => [
                        'id' => $assignment->class->id,
                        'name' => $assignment->class->name,
                        'full_name' => $assignment->class->full_name,
                    ],
                    'subject' => [
                        'id' => $assignment->subject->id,
                        'name' => $assignment->subject->name,
                        'code' => $assignment->subject->code,
                    ],
                    'pending_submissions' => $assignment->submissions_count,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    /**
     * Get assignment analytics
     */
    public function analytics(Assignment $assignment): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $assignment->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only view analytics for your own assignments.',
            ], 403);
        }

        $submissions = $assignment->submissions()->whereNotNull('grade')->get();
        $grades = $submissions->pluck('grade');

        $analytics = [
            'assignment_id' => $assignment->id,
            'assignment_title' => $assignment->title,
            'total_students' => $assignment->class->students()->count(),
            'submission_stats' => $assignment->getSubmissionStats(),
            'grade_analytics' => [
                'average' => $grades->avg(),
                'median' => $grades->median(),
                'min' => $grades->min(),
                'max' => $grades->max(),
                'std_deviation' => $grades->count() > 1 ? sqrt($grades->map(function ($grade) use ($grades) {
                    return pow($grade - $grades->avg(), 2);
                })->sum() / ($grades->count() - 1)) : 0,
            ],
            'grade_distribution' => [
                'A (90-100)' => $grades->filter(fn($g) => $g >= 90)->count(),
                'B (80-89)' => $grades->filter(fn($g) => $g >= 80 && $g < 90)->count(),
                'C (70-79)' => $grades->filter(fn($g) => $g >= 70 && $g < 80)->count(),
                'D (60-69)' => $grades->filter(fn($g) => $g >= 60 && $g < 70)->count(),
                'F (0-59)' => $grades->filter(fn($g) => $g < 60)->count(),
            ],
            'performance_insights' => [
                'pass_rate' => $grades->count() > 0 ? round(($grades->filter(fn($g) => $g >= 60)->count() / $grades->count()) * 100, 2) : 0,
                'excellence_rate' => $grades->count() > 0 ? round(($grades->filter(fn($g) => $g >= 90)->count() / $grades->count()) * 100, 2) : 0,
                'late_submission_rate' => $assignment->submissions()->count() > 0 ? round(($assignment->submissions()->where('is_late', true)->count() / $assignment->submissions()->count()) * 100, 2) : 0,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Get assignment types
     */
    public function getTypes(): JsonResponse
    {
        $types = [
            ['value' => 'homework', 'label' => 'Homework'],
            ['value' => 'quiz', 'label' => 'Quiz'],
            ['value' => 'exam', 'label' => 'Exam'],
            ['value' => 'project', 'label' => 'Project'],
            ['value' => 'lab', 'label' => 'Lab Work'],
        ];

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }
}