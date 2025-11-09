<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\Student;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClassManagementController extends Controller
{
    /**
     * Get all classes for the authenticated teacher
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

        $query = Classes::with(['subjects', 'students', 'teacher'])
            ->withCount(['students', 'assignments']);

        // If teacher, only show their classes
        if ($user->isTeacher()) {
            $query->where('teacher_id', $user->id);
        }

        // Apply filters
        if ($request->has('grade_level')) {
            $query->byGrade($request->grade_level);
        }

        if ($request->has('academic_year')) {
            $query->byAcademicYear($request->academic_year);
        }

        if ($request->has('active_only') && $request->boolean('active_only')) {
            $query->active();
        }

        $classes = $query->orderBy('grade_level')
            ->orderBy('section')
            ->get()
            ->map(function ($class) {
                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'code' => $class->code,
                    'full_name' => $class->full_name,
                    'grade_level' => $class->grade_level,
                    'section' => $class->section,
                    'academic_year' => $class->academic_year,
                    'description' => $class->description,
                    'max_students' => $class->max_students,
                    'is_active' => $class->is_active,
                    'teacher' => [
                        'id' => $class->teacher->id,
                        'name' => $class->teacher->name,
                        'email' => $class->teacher->email,
                    ],
                    'subjects' => $class->subjects->map(function ($subject) {
                        return [
                            'id' => $subject->id,
                            'name' => $subject->name,
                            'code' => $subject->code,
                        ];
                    }),
                    'students_count' => $class->students_count,
                    'assignments_count' => $class->assignments_count,
                    'available_spots' => $class->available_spots,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $classes,
        ]);
    }

    /**
     * Create a new class
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isTeacher() && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Teacher or admin role required.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:classes,code',
            'grade_level' => 'required|string|max:50',
            'section' => 'required|string|max:50',
            'academic_year' => 'required|string|max:20',
            'description' => 'nullable|string',
            'max_students' => 'required|integer|min:1|max:100',
            'teacher_id' => [
                'required',
                'exists:users,id',
                Rule::exists('users', 'id')->where('role', 'teacher'),
            ],
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        // If teacher, they can only assign themselves
        if ($user->isTeacher() && $validated['teacher_id'] != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Teachers can only create classes for themselves.',
            ], 403);
        }

        $class = Classes::create($validated);

        // Attach subjects if provided
        if (!empty($validated['subject_ids'])) {
            $class->subjects()->attach($validated['subject_ids']);
        }

        $class->load(['subjects', 'teacher']);

        return response()->json([
            'success' => true,
            'message' => 'Class created successfully.',
            'data' => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'full_name' => $class->full_name,
                'grade_level' => $class->grade_level,
                'section' => $class->section,
                'academic_year' => $class->academic_year,
                'description' => $class->description,
                'max_students' => $class->max_students,
                'is_active' => $class->is_active,
                'teacher' => [
                    'id' => $class->teacher->id,
                    'name' => $class->teacher->name,
                    'email' => $class->teacher->email,
                ],
                'subjects' => $class->subjects->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code,
                    ];
                }),
            ],
        ], 201);
    }

    /**
     * Get a specific class
     */
    public function show(Classes $class): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $class->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only view your own classes.',
            ], 403);
        }

        $class->load(['subjects', 'students', 'teacher', 'assignments'])
            ->loadCount(['students', 'assignments']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'full_name' => $class->full_name,
                'grade_level' => $class->grade_level,
                'section' => $class->section,
                'academic_year' => $class->academic_year,
                'description' => $class->description,
                'max_students' => $class->max_students,
                'is_active' => $class->is_active,
                'teacher' => [
                    'id' => $class->teacher->id,
                    'name' => $class->teacher->name,
                    'email' => $class->teacher->email,
                ],
                'subjects' => $class->subjects->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code,
                        'description' => $subject->description,
                    ];
                }),
                'students' => $class->students->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'name' => $student->name,
                        'student_id' => $student->student_id,
                        'email' => $student->email,
                        'grade_level' => $student->grade_level,
                        'status' => $student->status,
                    ];
                }),
                'assignments' => $class->assignments->map(function ($assignment) {
                    return [
                        'id' => $assignment->id,
                        'title' => $assignment->title,
                        'type' => $assignment->type,
                        'due_date' => $assignment->due_date->toISOString(),
                        'is_published' => $assignment->is_published,
                    ];
                }),
                'students_count' => $class->students_count,
                'assignments_count' => $class->assignments_count,
                'available_spots' => $class->available_spots,
            ],
        ]);
    }  
  /**
     * Update a class
     */
    public function update(Request $request, Classes $class): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $class->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only update your own classes.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('classes', 'code')->ignore($class->id),
            ],
            'grade_level' => 'sometimes|required|string|max:50',
            'section' => 'sometimes|required|string|max:50',
            'academic_year' => 'sometimes|required|string|max:20',
            'description' => 'nullable|string',
            'max_students' => 'sometimes|required|integer|min:1|max:100',
            'is_active' => 'sometimes|boolean',
            'teacher_id' => [
                'sometimes',
                'required',
                'exists:users,id',
                Rule::exists('users', 'id')->where('role', 'teacher'),
            ],
            'subject_ids' => 'nullable|array',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        // If teacher, they can only assign themselves
        if ($user->isTeacher() && isset($validated['teacher_id']) && $validated['teacher_id'] != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Teachers can only assign classes to themselves.',
            ], 403);
        }

        $class->update($validated);

        // Update subjects if provided
        if (array_key_exists('subject_ids', $validated)) {
            $class->subjects()->sync($validated['subject_ids'] ?? []);
        }

        $class->load(['subjects', 'teacher']);

        return response()->json([
            'success' => true,
            'message' => 'Class updated successfully.',
            'data' => [
                'id' => $class->id,
                'name' => $class->name,
                'code' => $class->code,
                'full_name' => $class->full_name,
                'grade_level' => $class->grade_level,
                'section' => $class->section,
                'academic_year' => $class->academic_year,
                'description' => $class->description,
                'max_students' => $class->max_students,
                'is_active' => $class->is_active,
                'teacher' => [
                    'id' => $class->teacher->id,
                    'name' => $class->teacher->name,
                    'email' => $class->teacher->email,
                ],
                'subjects' => $class->subjects->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Delete a class
     */
    public function destroy(Classes $class): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $class->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only delete your own classes.',
            ], 403);
        }

        // Check if class has students or assignments
        if ($class->students()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete class with enrolled students. Please remove students first.',
            ], 422);
        }

        if ($class->assignments()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete class with assignments. Please remove assignments first.',
            ], 422);
        }

        $class->delete();

        return response()->json([
            'success' => true,
            'message' => 'Class deleted successfully.',
        ]);
    }

    /**
     * Assign subjects to a class
     */
    public function assignSubjects(Request $request, Classes $class): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $class->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only manage your own classes.',
            ], 403);
        }

        $validated = $request->validate([
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $class->subjects()->sync($validated['subject_ids']);
        $class->load('subjects');

        return response()->json([
            'success' => true,
            'message' => 'Subjects assigned successfully.',
            'data' => [
                'class_id' => $class->id,
                'subjects' => $class->subjects->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code,
                        'description' => $subject->description,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Remove subjects from a class
     */
    public function removeSubjects(Request $request, Classes $class): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $class->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only manage your own classes.',
            ], 403);
        }

        $validated = $request->validate([
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $class->subjects()->detach($validated['subject_ids']);
        $class->load('subjects');

        return response()->json([
            'success' => true,
            'message' => 'Subjects removed successfully.',
            'data' => [
                'class_id' => $class->id,
                'subjects' => $class->subjects->map(function ($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code,
                        'description' => $subject->description,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Enroll students in a class
     */
    public function enrollStudents(Request $request, Classes $class): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $class->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only manage your own classes.',
            ], 403);
        }

        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        // Check class capacity
        $currentStudentCount = $class->students()->count();
        $newStudentCount = count($validated['student_ids']);
        
        if ($currentStudentCount + $newStudentCount > $class->max_students) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot enroll students. Class capacity exceeded.',
                'data' => [
                    'current_count' => $currentStudentCount,
                    'max_students' => $class->max_students,
                    'available_spots' => $class->max_students - $currentStudentCount,
                    'requested_enrollments' => $newStudentCount,
                ],
            ], 422);
        }

        // Check for already enrolled students
        $alreadyEnrolled = \DB::table('class_student')
            ->where('classes_id', $class->id)
            ->whereIn('student_id', $validated['student_ids'])
            ->pluck('student_id')
            ->toArray();

        if (!empty($alreadyEnrolled)) {
            return response()->json([
                'success' => false,
                'message' => 'Some students are already enrolled in this class.',
                'data' => [
                    'already_enrolled_ids' => $alreadyEnrolled,
                ],
            ], 422);
        }

        $class->students()->attach($validated['student_ids']);
        $class->load('students');

        return response()->json([
            'success' => true,
            'message' => 'Students enrolled successfully.',
            'data' => [
                'class_id' => $class->id,
                'enrolled_count' => count($validated['student_ids']),
                'total_students' => $class->students()->count(),
                'available_spots' => $class->available_spots,
            ],
        ]);
    }

    /**
     * Remove students from a class
     */
    public function removeStudents(Request $request, Classes $class): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $class->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only manage your own classes.',
            ], 403);
        }

        $validated = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        $class->students()->detach($validated['student_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Students removed successfully.',
            'data' => [
                'class_id' => $class->id,
                'removed_count' => count($validated['student_ids']),
                'total_students' => $class->students()->count(),
                'available_spots' => $class->available_spots,
            ],
        ]);
    }

    /**
     * Get class resources
     */
    public function getResources(Classes $class): JsonResponse
    {
        $user = Auth::user();
        
        // Check access permissions
        if ($user->isTeacher() && $class->teacher_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only view resources for your own classes.',
            ], 403);
        }

        $resources = $class->resources()
            ->with(['subject', 'teacher'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($resource) {
                return [
                    'id' => $resource->id,
                    'title' => $resource->title,
                    'file_path' => $resource->file_path,
                    'file_type' => $resource->file_type,
                    'file_size' => $resource->file_size ?? null,
                    'subject' => $resource->subject ? [
                        'id' => $resource->subject->id,
                        'name' => $resource->subject->name,
                        'code' => $resource->subject->code,
                    ] : null,
                    'teacher' => [
                        'id' => $resource->teacher->id,
                        'name' => $resource->teacher->name,
                    ],
                    'created_at' => $resource->created_at->toISOString(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'class_id' => $class->id,
                'class_name' => $class->name,
                'resources' => $resources,
                'total_resources' => $resources->count(),
            ],
        ]);
    }

    /**
     * Get available subjects for assignment
     */
    public function getAvailableSubjects(): JsonResponse
    {
        $subjects = Subject::orderBy('name')->get()->map(function ($subject) {
            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'description' => $subject->description,
                'grade_level' => $subject->grade_level,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $subjects,
        ]);
    }

    /**
     * Get available students for enrollment
     */
    public function getAvailableStudents(Request $request): JsonResponse
    {
        $query = Student::active();

        // Filter by grade level if provided
        if ($request->has('grade_level')) {
            $query->byGrade($request->grade_level);
        }

        // Exclude students already enrolled in a specific class
        if ($request->has('exclude_class_id')) {
            $classId = $request->exclude_class_id;
            $query->whereDoesntHave('classes', function ($q) use ($classId) {
                $q->where('classes.id', $classId);
            });
        }

        $students = $query->orderBy('name')->get()->map(function ($student) {
            return [
                'id' => $student->id,
                'name' => $student->name,
                'student_id' => $student->student_id,
                'email' => $student->email,
                'grade_level' => $student->grade_level,
                'status' => $student->status,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }
}