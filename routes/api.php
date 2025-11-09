<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\AcademicsController;
use App\Http\Controllers\Api\AdmissionsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ClassManagementController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\MonitoringController;
use App\Http\Controllers\Api\DataProtectionController;
use App\Http\Controllers\Api\ScheduleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentication routes with rate limiting
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('rate.limit:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/user', [AuthController::class, 'user']);
});

// Public routes (no authentication required) with rate limiting and caching
Route::prefix('public')->middleware(['rate.limit:public', 'cache.response:600'])->group(function () {
    // Home page data endpoints
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/home/news', [HomeController::class, 'news']);
    Route::get('/home/announcements', [HomeController::class, 'announcements']);
    Route::get('/home/featured-events', [HomeController::class, 'featuredEvents']);
    Route::get('/home/achievements', [HomeController::class, 'achievements']);
    Route::get('/home/quick-access-links', [HomeController::class, 'quickAccessLinks']);
    
    Route::get('/announcements', [AnnouncementController::class, 'public']);
    Route::get('/announcements/{id}', [AnnouncementController::class, 'show']);
    
    Route::get('/events', [EventController::class, 'public']);
    Route::get('/events/calendar', [EventController::class, 'calendar']);
    Route::get('/events/upcoming', [EventController::class, 'upcoming']);
    Route::get('/events/search', [EventController::class, 'search']);
    Route::get('/events/{id}', [EventController::class, 'show']);
    
    Route::get('/resources', [ResourceController::class, 'public']);
    Route::get('/resources/{id}', [ResourceController::class, 'show']);
    
    // Academics information endpoints
    Route::get('/academics/curriculum', [AcademicsController::class, 'curriculum']);
    Route::get('/academics/examinations', [AcademicsController::class, 'examinations']);
    Route::get('/academics/calendar', [AcademicsController::class, 'calendar']);
    Route::get('/academics/syllabus', [AcademicsController::class, 'syllabus']);
    Route::get('/academics/grade-levels', [AcademicsController::class, 'gradeLevels']);
    Route::get('/academics/academic-years', [AcademicsController::class, 'academicYears']);
    Route::get('/academics/subjects', [AcademicsController::class, 'subjects']);
    
    // Admissions information endpoints
    Route::get('/admissions/process', [AdmissionsController::class, 'process']);
    Route::get('/admissions/deadlines', [AdmissionsController::class, 'deadlines']);
    Route::get('/admissions/fees', [AdmissionsController::class, 'fees']);
    Route::get('/admissions/forms', [AdmissionsController::class, 'forms']);
    Route::get('/admissions/faq', [AdmissionsController::class, 'faq']);
    Route::get('/admissions/contact', [AdmissionsController::class, 'contact']);
    Route::get('/admissions/available-grades', [AdmissionsController::class, 'availableGrades']);
});

// Resource download route (accessible to both public and authenticated users)
Route::get('/resources/{id}/download', [ResourceController::class, 'download'])->name('resources.download');

// Analytics tracking (public route for tracking page views)
Route::post('/analytics/track', [AnalyticsController::class, 'track']);

// Admin/Teacher routes (authentication required) with rate limiting
Route::middleware(['auth:sanctum', 'rate.limit:api'])->group(function () {
    // Dashboard routes
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/dashboard/daily-schedule', [DashboardController::class, 'dailySchedule']);
    Route::get('/dashboard/pending-tasks', [DashboardController::class, 'pendingTasks']);
    Route::get('/dashboard/reminders', [DashboardController::class, 'reminders']);
    Route::get('/dashboard/teacher-data', [DashboardController::class, 'teacherData']);
    Route::get('/dashboard/admin-data', [DashboardController::class, 'adminData']);
    
    // Class management routes
    Route::apiResource('classes', ClassManagementController::class);
    Route::post('/classes/{class}/assign-subjects', [ClassManagementController::class, 'assignSubjects']);
    Route::post('/classes/{class}/remove-subjects', [ClassManagementController::class, 'removeSubjects']);
    Route::post('/classes/{class}/enroll-students', [ClassManagementController::class, 'enrollStudents']);
    Route::post('/classes/{class}/remove-students', [ClassManagementController::class, 'removeStudents']);
    Route::get('/classes/{class}/resources', [ClassManagementController::class, 'getResources']);
    Route::get('/available-subjects', [ClassManagementController::class, 'getAvailableSubjects']);
    Route::get('/available-students', [ClassManagementController::class, 'getAvailableStudents']);
    
    // Schedule management routes
    Route::apiResource('schedules', ScheduleController::class);
    Route::get('/classes/{class}/schedules', [ScheduleController::class, 'index']);
    
    // Assignment management routes
    Route::apiResource('assignments', AssignmentController::class);
    Route::post('/assignments/{assignment}/publish', [AssignmentController::class, 'publish']);
    Route::post('/assignments/{assignment}/unpublish', [AssignmentController::class, 'unpublish']);
    Route::get('/assignments/{assignment}/submissions', [AssignmentController::class, 'getSubmissions']);
    Route::post('/assignments/{assignment}/submissions/{submission}/grade', [AssignmentController::class, 'gradeSubmission']);
    Route::get('/assignments/{assignment}/analytics', [AssignmentController::class, 'analytics']);
    Route::get('/grading-queue', [AssignmentController::class, 'gradingQueue']);
    Route::get('/assignment-types', [AssignmentController::class, 'getTypes']);
    
    Route::apiResource('announcements', AnnouncementController::class);
    Route::post('/announcements/{id}/publish', [AnnouncementController::class, 'publish']);
    Route::post('/announcements/{id}/unpublish', [AnnouncementController::class, 'unpublish']);
    Route::get('/announcement-types', [AnnouncementController::class, 'types']);
    
    Route::apiResource('events', EventController::class);
    Route::get('/event-types', [EventController::class, 'types']);
    
    Route::apiResource('resources', ResourceController::class);
    Route::get('/resource-types', [ResourceController::class, 'types']);
    Route::get('/upload-limits', [ResourceController::class, 'uploadLimits']);
    
    // CMS routes with content-specific rate limiting
    Route::prefix('cms')->middleware('rate.limit:content')->group(function () {
        // Content management
        Route::apiResource('content', ContentController::class);
        Route::get('/content/{id}/versions', [ContentController::class, 'versions']);
        Route::post('/content/{id}/publish-version', [ContentController::class, 'publishVersion']);
        Route::get('/content-types', [ContentController::class, 'types']);
        Route::get('/content-templates', [ContentController::class, 'templates']);
        Route::post('/content/bulk-action', [ContentController::class, 'bulkAction']);
        
        // Media management with upload rate limiting
        Route::delete('/media/bulk-delete', [MediaController::class, 'bulkDelete'])->middleware('rate.limit:upload');
        Route::post('/media/directories', [MediaController::class, 'createDirectory']);
        Route::get('/media-directories', [MediaController::class, 'directories']);
        Route::apiResource('media', MediaController::class)->except(['update'])->middleware('rate.limit:upload');
    });
    
    // Analytics routes (admin only)
    Route::prefix('analytics')->group(function () {
        Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('/popular-pages', [AnalyticsController::class, 'popularPages']);
        Route::get('/popular-content', [AnalyticsController::class, 'popularContent']);
        Route::get('/engagement-metrics', [AnalyticsController::class, 'engagementMetrics']);
        Route::get('/real-time', [AnalyticsController::class, 'realTime']);
    });
    
    // Monitoring routes (admin only)
    Route::prefix('monitoring')->group(function () {
        Route::get('/system-health', [MonitoringController::class, 'systemHealth']);
        Route::get('/error-stats', [MonitoringController::class, 'errorStats']);
        Route::get('/security-stats', [MonitoringController::class, 'securityStats']);
        Route::get('/performance-metrics', [MonitoringController::class, 'performanceMetrics']);
        Route::get('/recent-logs', [MonitoringController::class, 'recentLogs']);
        Route::post('/log-event', [MonitoringController::class, 'logEvent']);
        Route::delete('/cleanup-logs', [MonitoringController::class, 'cleanupLogs']);
    });
    
    // Data Protection routes (GDPR compliance)
    Route::prefix('data-protection')->group(function () {
        Route::get('/consent-status', [DataProtectionController::class, 'getConsentStatus']);
        Route::post('/grant-consent', [DataProtectionController::class, 'grantConsent']);
        Route::post('/revoke-consent', [DataProtectionController::class, 'revokeConsent']);
        Route::get('/export-data', [DataProtectionController::class, 'exportUserData']);
        Route::post('/request-deletion', [DataProtectionController::class, 'requestAccountDeletion']);
        Route::get('/retention-policies', [DataProtectionController::class, 'getRetentionPolicies']);
        Route::get('/compliance-report', [DataProtectionController::class, 'getComplianceReport']);
        Route::post('/anonymize-user/{userId}', [DataProtectionController::class, 'anonymizeUserData']);
    });
});

// Public data protection routes
Route::get('/privacy-policy', [DataProtectionController::class, 'getPrivacyPolicy']);

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => config('app.version', '1.0.0'),
        'environment' => app()->environment(),
    ]);
});