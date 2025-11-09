<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Classes;
use App\Models\Event;
use App\Models\Announcement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Get dashboard overview data for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $data = [
            'user' => [
                'name' => $user->name,
                'role' => $user->role,
                'email' => $user->email,
            ],
            'daily_schedule' => $this->getDailySchedule($user),
            'pending_tasks' => $this->getPendingTasks($user),
            'reminders' => $this->getReminders($user),
            'quick_stats' => $this->getQuickStats($user),
            'recent_activity' => $this->getRecentActivity($user),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get daily schedule overview for the user
     */
    public function dailySchedule(Request $request): JsonResponse
    {
        $user = Auth::user();
        $date = $request->get('date', Carbon::today()->toDateString());
        
        $schedule = $this->getDailySchedule($user, $date);
        
        return response()->json([
            'success' => true,
            'data' => $schedule,
        ]);
    } 
   /**
     * Get pending tasks and reminders
     */
    public function pendingTasks(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $tasks = $this->getPendingTasks($user);
        
        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Get reminders for the user
     */
    public function reminders(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $reminders = $this->getReminders($user);
        
        return response()->json([
            'success' => true,
            'data' => $reminders,
        ]);
    }

    /**
     * Get teacher-specific dashboard data
     */
    public function teacherData(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isTeacher()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Teacher role required.',
            ], 403);
        }

        $data = [
            'classes_overview' => $this->getClassesOverview($user),
            'assignments_summary' => $this->getAssignmentsSummary($user),
            'grading_queue' => $this->getGradingQueue($user),
            'upcoming_deadlines' => $this->getUpcomingDeadlines($user),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get admin-specific dashboard data
     */
    public function adminData(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin role required.',
            ], 403);
        }

        $data = [
            'system_overview' => $this->getSystemOverview(),
            'recent_announcements' => $this->getRecentAnnouncements(),
            'upcoming_events' => $this->getUpcomingEvents(),
            'teacher_activity' => $this->getTeacherActivity(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }    /**

     * Get daily schedule for a user
     */
    private function getDailySchedule($user, $date = null): array
    {
        $date = $date ? Carbon::parse($date) : Carbon::today();
        
        if ($user->isTeacher()) {
            // Get classes scheduled for today
            $classes = $user->classes()
                ->with(['subjects', 'students'])
                ->active()
                ->get()
                ->map(function ($class) use ($date) {
                    return [
                        'id' => $class->id,
                        'name' => $class->name,
                        'full_name' => $class->full_name,
                        'grade_level' => $class->grade_level,
                        'section' => $class->section,
                        'student_count' => $class->students->count(),
                        'subjects' => $class->subjects->pluck('name')->toArray(),
                        // Note: In a real implementation, you'd have a schedule table
                        // For now, we'll return basic class info
                    ];
                });

            return [
                'date' => $date->toDateString(),
                'day_of_week' => $date->format('l'),
                'classes' => $classes,
                'total_classes' => $classes->count(),
            ];
        }

        return [
            'date' => $date->toDateString(),
            'day_of_week' => $date->format('l'),
            'classes' => [],
            'total_classes' => 0,
        ];
    }

    /**
     * Get pending tasks for a user
     */
    private function getPendingTasks($user): array
    {
        $tasks = [];

        if ($user->isTeacher()) {
            // Assignments needing grading
            $ungradedCount = Assignment::byTeacher($user->id)
                ->whereHas('submissions', function ($query) {
                    $query->whereNull('grade')->where('status', '!=', 'draft');
                })
                ->count();

            if ($ungradedCount > 0) {
                $tasks[] = [
                    'type' => 'grading',
                    'title' => 'Assignments to Grade',
                    'count' => $ungradedCount,
                    'priority' => 'high',
                    'url' => '/admin/assignments/grading',
                ];
            }

            // Overdue assignments (created by teacher)
            $overdueCount = Assignment::byTeacher($user->id)
                ->overdue()
                ->published()
                ->count();

            if ($overdueCount > 0) {
                $tasks[] = [
                    'type' => 'overdue_assignments',
                    'title' => 'Overdue Assignments',
                    'count' => $overdueCount,
                    'priority' => 'medium',
                    'url' => '/admin/assignments?filter=overdue',
                ];
            }
        }

        return $tasks;
    } 
   /**
     * Get reminders for a user
     */
    private function getReminders($user): array
    {
        $reminders = [];

        if ($user->isTeacher()) {
            // Upcoming assignment due dates
            $upcomingAssignments = Assignment::byTeacher($user->id)
                ->published()
                ->dueBetween(Carbon::now(), Carbon::now()->addDays(7))
                ->with(['class', 'subject'])
                ->orderBy('due_date')
                ->limit(5)
                ->get();

            foreach ($upcomingAssignments as $assignment) {
                $reminders[] = [
                    'type' => 'assignment_due',
                    'title' => $assignment->title,
                    'description' => "Due in {$assignment->class->name} - {$assignment->subject->name}",
                    'due_date' => $assignment->due_date->toISOString(),
                    'days_until_due' => $assignment->days_until_due,
                    'priority' => $assignment->days_until_due <= 2 ? 'high' : 'medium',
                ];
            }
        }

        // Upcoming events for all users
        $upcomingEvents = Event::where('event_date', '>=', Carbon::now())
            ->where('event_date', '<=', Carbon::now()->addDays(7))
            ->orderBy('event_date')
            ->limit(3)
            ->get();

        foreach ($upcomingEvents as $event) {
            $reminders[] = [
                'type' => 'event',
                'title' => $event->title,
                'description' => $event->description,
                'event_date' => $event->event_date->toISOString(),
                'days_until_event' => Carbon::now()->diffInDays($event->event_date),
                'priority' => 'low',
            ];
        }

        return $reminders;
    }

    /**
     * Get quick statistics for a user
     */
    private function getQuickStats($user): array
    {
        if ($user->isTeacher()) {
            $totalClasses = $user->classes()->active()->count();
            $totalStudents = $user->classes()
                ->active()
                ->withCount('students')
                ->get()
                ->sum('students_count');
            $totalAssignments = Assignment::byTeacher($user->id)->count();
            $pendingGrading = Assignment::byTeacher($user->id)
                ->whereHas('submissions', function ($query) {
                    $query->whereNull('grade')->where('status', '!=', 'draft');
                })
                ->count();

            return [
                'total_classes' => $totalClasses,
                'total_students' => $totalStudents,
                'total_assignments' => $totalAssignments,
                'pending_grading' => $pendingGrading,
            ];
        }

        if ($user->isAdmin()) {
            $totalTeachers = \App\Models\User::where('role', 'teacher')->count();
            $totalClasses = Classes::active()->count();
            $totalStudents = \App\Models\Student::count();
            $totalAnnouncements = Announcement::count();

            return [
                'total_teachers' => $totalTeachers,
                'total_classes' => $totalClasses,
                'total_students' => $totalStudents,
                'total_announcements' => $totalAnnouncements,
            ];
        }

        return [];
    } 
   /**
     * Get recent activity for a user
     */
    private function getRecentActivity($user): array
    {
        $activities = [];

        if ($user->isTeacher()) {
            // Recent assignments created
            $recentAssignments = Assignment::byTeacher($user->id)
                ->with(['class', 'subject'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            foreach ($recentAssignments as $assignment) {
                $activities[] = [
                    'type' => 'assignment_created',
                    'title' => "Created assignment: {$assignment->title}",
                    'description' => "For {$assignment->class->name} - {$assignment->subject->name}",
                    'timestamp' => $assignment->created_at->toISOString(),
                    'url' => "/admin/assignments/{$assignment->id}",
                ];
            }
        }

        // Recent announcements for all users
        $recentAnnouncements = Announcement::where('is_public', true)
            ->orderBy('published_at', 'desc')
            ->limit(3)
            ->get();

        foreach ($recentAnnouncements as $announcement) {
            $activities[] = [
                'type' => 'announcement',
                'title' => $announcement->title,
                'description' => substr($announcement->content, 0, 100) . '...',
                'timestamp' => $announcement->published_at->toISOString(),
                'url' => "/announcements/{$announcement->id}",
            ];
        }

        // Sort by timestamp descending
        usort($activities, function ($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return array_slice($activities, 0, 10);
    }

    /**
     * Get classes overview for teacher
     */
    private function getClassesOverview($user): array
    {
        return $user->classes()
            ->active()
            ->withCount(['students', 'assignments'])
            ->with('subjects')
            ->get()
            ->map(function ($class) {
                return [
                    'id' => $class->id,
                    'name' => $class->name,
                    'full_name' => $class->full_name,
                    'grade_level' => $class->grade_level,
                    'section' => $class->section,
                    'student_count' => $class->students_count,
                    'assignment_count' => $class->assignments_count,
                    'subjects' => $class->subjects->pluck('name')->toArray(),
                    'available_spots' => $class->available_spots,
                ];
            })
            ->toArray();
    }    /**

     * Get assignments summary for teacher
     */
    private function getAssignmentsSummary($user): array
    {
        $total = Assignment::byTeacher($user->id)->count();
        $published = Assignment::byTeacher($user->id)->published()->count();
        $overdue = Assignment::byTeacher($user->id)->overdue()->published()->count();
        $upcoming = Assignment::byTeacher($user->id)
            ->published()
            ->dueBetween(Carbon::now(), Carbon::now()->addDays(7))
            ->count();

        return [
            'total' => $total,
            'published' => $published,
            'overdue' => $overdue,
            'upcoming_due' => $upcoming,
        ];
    }

    /**
     * Get grading queue for teacher
     */
    private function getGradingQueue($user): array
    {
        return Assignment::byTeacher($user->id)
            ->whereHas('submissions', function ($query) {
                $query->whereNull('grade')->where('status', '!=', 'draft');
            })
            ->with(['class', 'subject'])
            ->withCount(['submissions' => function ($query) {
                $query->whereNull('grade')->where('status', '!=', 'draft');
            }])
            ->orderBy('due_date')
            ->limit(10)
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'class_name' => $assignment->class->name,
                    'subject_name' => $assignment->subject->name,
                    'due_date' => $assignment->due_date->toISOString(),
                    'pending_submissions' => $assignment->submissions_count,
                    'is_overdue' => $assignment->isOverdue(),
                ];
            })
            ->toArray();
    }

    /**
     * Get upcoming deadlines for teacher
     */
    private function getUpcomingDeadlines($user): array
    {
        return Assignment::byTeacher($user->id)
            ->published()
            ->dueBetween(Carbon::now(), Carbon::now()->addDays(14))
            ->with(['class', 'subject'])
            ->orderBy('due_date')
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'class_name' => $assignment->class->name,
                    'subject_name' => $assignment->subject->name,
                    'due_date' => $assignment->due_date->toISOString(),
                    'days_until_due' => $assignment->days_until_due,
                ];
            })
            ->toArray();
    }    /**
   
  * Get system overview for admin
     */
    private function getSystemOverview(): array
    {
        return [
            'total_users' => \App\Models\User::count(),
            'total_teachers' => \App\Models\User::where('role', 'teacher')->count(),
            'total_admins' => \App\Models\User::where('role', 'admin')->count(),
            'total_classes' => Classes::count(),
            'active_classes' => Classes::active()->count(),
            'total_students' => \App\Models\Student::count(),
            'total_assignments' => Assignment::count(),
            'published_assignments' => Assignment::published()->count(),
            'total_announcements' => Announcement::count(),
            'public_announcements' => Announcement::where('is_public', true)->count(),
            'total_events' => Event::count(),
            'upcoming_events' => Event::where('event_date', '>=', Carbon::now())->count(),
        ];
    }

    /**
     * Get recent announcements for admin
     */
    private function getRecentAnnouncements(): array
    {
        return Announcement::orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($announcement) {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'type' => $announcement->type,
                    'is_public' => $announcement->is_public,
                    'published_at' => $announcement->published_at?->toISOString(),
                    'created_at' => $announcement->created_at->toISOString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get upcoming events for admin
     */
    private function getUpcomingEvents(): array
    {
        return Event::where('event_date', '>=', Carbon::now())
            ->orderBy('event_date')
            ->limit(5)
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'event_date' => $event->event_date->toISOString(),
                    'type' => $event->type,
                    'location' => $event->location,
                ];
            })
            ->toArray();
    }

    /**
     * Get teacher activity summary for admin
     */
    private function getTeacherActivity(): array
    {
        $teachers = \App\Models\User::where('role', 'teacher')
            ->withCount(['classes'])
            ->get();

        return $teachers->map(function ($teacher) {
            $assignmentCount = Assignment::byTeacher($teacher->id)->count();
            $recentAssignments = Assignment::byTeacher($teacher->id)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->count();

            return [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'email' => $teacher->email,
                'classes_count' => $teacher->classes_count,
                'total_assignments' => $assignmentCount,
                'recent_assignments' => $recentAssignments,
                'last_login' => $teacher->updated_at->toISOString(), // Approximation
            ];
        })->toArray();
    }
}