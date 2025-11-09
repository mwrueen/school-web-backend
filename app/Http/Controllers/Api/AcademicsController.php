<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Classes;
use App\Models\Event;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class AcademicsController extends Controller
{
    /**
     * Get curriculum information with grade level filtering
     */
    public function curriculum(Request $request): JsonResponse
    {
        $query = Subject::active()->with(['classes' => function ($query) {
            $query->active()->with('teacher:id,name');
        }]);

        // Filter by grade level
        if ($request->has('grade_level')) {
            $query->forGrade($request->grade_level);
        }

        // Filter by academic year
        if ($request->has('academic_year')) {
            $query->whereHas('classes', function ($q) use ($request) {
                $q->byAcademicYear($request->academic_year);
            });
        }

        $subjects = $query->orderBy('name')->get();

        // Group subjects by grade level for better organization
        $curriculum = [];
        foreach ($subjects as $subject) {
            foreach ($subject->grade_levels as $gradeLevel) {
                if (!isset($curriculum[$gradeLevel])) {
                    $curriculum[$gradeLevel] = [];
                }
                
                $curriculum[$gradeLevel][] = [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'description' => $subject->description,
                    'credits' => $subject->credits,
                    'classes' => $subject->classes->where('grade_level', $gradeLevel)->values(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'curriculum' => $curriculum,
                'available_grades' => array_keys($curriculum),
            ],
        ]);
    }

    /**
     * Get examination schedules and policies
     */
    public function examinations(Request $request): JsonResponse
    {
        // Get examination events
        $query = Event::public()
            ->where('type', 'academic')
            ->where(function ($q) {
                $q->where('title', 'like', '%exam%')
                  ->orWhere('title', 'like', '%test%')
                  ->orWhere('description', 'like', '%exam%')
                  ->orWhere('description', 'like', '%test%');
            });

        // Filter by academic year or date range
        if ($request->has('academic_year')) {
            // Assuming academic year format like "2024-2025"
            $years = explode('-', $request->academic_year);
            if (count($years) === 2) {
                $startYear = (int) $years[0];
                $endYear = (int) $years[1];
                $query->whereBetween('event_date', [
                    Carbon::create($startYear, 7, 1), // Academic year starts in July
                    Carbon::create($endYear, 6, 30)   // Academic year ends in June
                ]);
            }
        }

        if ($request->has('upcoming') && $request->boolean('upcoming')) {
            $query->upcoming();
        }

        $examinations = $query->orderBy('event_date', 'asc')->get();

        // Get examination policies (this could come from a settings table or config)
        $policies = $this->getExaminationPolicies();

        return response()->json([
            'success' => true,
            'data' => [
                'schedules' => $examinations,
                'policies' => $policies,
            ],
        ]);
    }

    /**
     * Get academic calendar
     */
    public function calendar(Request $request): JsonResponse
    {
        $query = Event::public()->where('type', 'academic');

        // Filter by year and month if provided
        if ($request->has('year') && $request->has('month')) {
            $query->inMonth($request->year, $request->month);
        } elseif ($request->has('year')) {
            $query->whereYear('event_date', $request->year);
        } elseif ($request->has('academic_year')) {
            // Filter by academic year
            $years = explode('-', $request->academic_year);
            if (count($years) === 2) {
                $startYear = (int) $years[0];
                $endYear = (int) $years[1];
                $query->whereBetween('event_date', [
                    Carbon::create($startYear, 7, 1),
                    Carbon::create($endYear, 6, 30)
                ]);
            }
        }

        $events = $query->orderBy('event_date', 'asc')->get();

        // Group events by month for calendar display
        $calendar = [];
        foreach ($events as $event) {
            $monthKey = $event->event_date->format('Y-m');
            if (!isset($calendar[$monthKey])) {
                $calendar[$monthKey] = [
                    'year' => $event->event_date->year,
                    'month' => $event->event_date->month,
                    'month_name' => $event->event_date->format('F'),
                    'events' => [],
                ];
            }
            $calendar[$monthKey]['events'][] = $event;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'calendar' => array_values($calendar),
                'total_events' => $events->count(),
            ],
        ]);
    }

    /**
     * Get syllabus download functionality
     */
    public function syllabus(Request $request): JsonResponse
    {
        $query = Resource::public()
            ->where('resource_type', 'syllabus')
            ->with(['subject', 'class']);

        // Filter by grade level
        if ($request->has('grade_level')) {
            $query->whereHas('class', function ($q) use ($request) {
                $q->byGrade($request->grade_level);
            });
        }

        // Filter by subject
        if ($request->has('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        // Filter by academic year
        if ($request->has('academic_year')) {
            $query->whereHas('class', function ($q) use ($request) {
                $q->byAcademicYear($request->academic_year);
            });
        }

        $syllabusFiles = $query->orderBy('title')->get();

        // Group by grade level and subject
        $syllabus = [];
        foreach ($syllabusFiles as $file) {
            $gradeLevel = $file->class ? $file->class->grade_level : 'general';
            $subjectName = $file->subject ? $file->subject->name : 'General';
            
            if (!isset($syllabus[$gradeLevel])) {
                $syllabus[$gradeLevel] = [];
            }
            
            if (!isset($syllabus[$gradeLevel][$subjectName])) {
                $syllabus[$gradeLevel][$subjectName] = [];
            }
            
            $syllabus[$gradeLevel][$subjectName][] = [
                'id' => $file->id,
                'title' => $file->title,
                'description' => $file->description,
                'file_type' => $file->file_type,
                'file_size' => $file->file_size ?? null,
                'download_url' => route('resources.download', $file->id),
                'updated_at' => $file->updated_at,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'syllabus' => $syllabus,
                'available_grades' => array_keys($syllabus),
            ],
        ]);
    }

    /**
     * Get available grade levels
     */
    public function gradeLevels(): JsonResponse
    {
        // Get unique grade levels from active classes
        $gradeLevels = Classes::active()
            ->distinct()
            ->pluck('grade_level')
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $gradeLevels,
        ]);
    }

    /**
     * Get available academic years
     */
    public function academicYears(): JsonResponse
    {
        // Get unique academic years from classes
        $academicYears = Classes::distinct()
            ->pluck('academic_year')
            ->filter()
            ->sort()
            ->values();

        return response()->json([
            'success' => true,
            'data' => $academicYears,
        ]);
    }

    /**
     * Get subjects for a specific grade level
     */
    public function subjects(Request $request): JsonResponse
    {
        $query = Subject::active();

        if ($request->has('grade_level')) {
            $query->forGrade($request->grade_level);
        }

        $subjects = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $subjects,
        ]);
    }

    /**
     * Get examination policies
     * In a real application, this might come from a database or config file
     */
    private function getExaminationPolicies(): array
    {
        return [
            'general_policies' => [
                'Students must arrive 15 minutes before the examination starts',
                'Late arrivals will not be permitted after 30 minutes',
                'Students must bring valid ID cards',
                'Mobile phones and electronic devices are strictly prohibited',
                'Students must use only blue or black ink pens',
            ],
            'grading_system' => [
                'A+' => '90-100%',
                'A' => '80-89%',
                'B+' => '70-79%',
                'B' => '60-69%',
                'C+' => '50-59%',
                'C' => '40-49%',
                'F' => 'Below 40%',
            ],
            'examination_types' => [
                'Mid-term Examinations' => 'Conducted twice per academic year',
                'Final Examinations' => 'Conducted at the end of each semester',
                'Unit Tests' => 'Regular assessments throughout the term',
                'Practical Examinations' => 'For science and computer subjects',
            ],
            'important_dates' => [
                'Examination form submission deadline',
                'Admit card distribution dates',
                'Result publication dates',
                'Re-examination dates (if applicable)',
            ],
        ];
    }
}