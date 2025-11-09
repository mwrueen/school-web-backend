<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DataProtectionService
{
    /**
     * Encrypt sensitive data
     */
    public function encryptSensitiveData(string $data): string
    {
        try {
            return Crypt::encryptString($data);
        } catch (\Exception $e) {
            Log::error('Data encryption failed: ' . $e->getMessage());
            throw new \Exception('Failed to encrypt sensitive data');
        }
    }

    /**
     * Decrypt sensitive data
     */
    public function decryptSensitiveData(string $encryptedData): string
    {
        try {
            return Crypt::decryptString($encryptedData);
        } catch (\Exception $e) {
            Log::error('Data decryption failed: ' . $e->getMessage());
            throw new \Exception('Failed to decrypt sensitive data');
        }
    }

    /**
     * Hash sensitive data (one-way)
     */
    public function hashSensitiveData(string $data): string
    {
        return Hash::make($data);
    }

    /**
     * Verify hashed data
     */
    public function verifyHashedData(string $data, string $hashedData): bool
    {
        return Hash::check($data, $hashedData);
    }

    /**
     * Anonymize personal data
     */
    public function anonymizePersonalData(array $data): array
    {
        $anonymizedData = [];
        
        foreach ($data as $key => $value) {
            if ($this->isSensitiveField($key)) {
                $anonymizedData[$key] = $this->anonymizeValue($key, $value);
            } else {
                $anonymizedData[$key] = $value;
            }
        }
        
        return $anonymizedData;
    }

    /**
     * Check if field contains sensitive data
     */
    protected function isSensitiveField(string $fieldName): bool
    {
        $sensitiveFields = [
            'email',
            'phone',
            'address',
            'parent_contact',
            'emergency_contact',
            'medical_info',
            'social_security',
            'id_number',
            'passport',
            'birth_date',
            'personal_notes'
        ];

        return in_array(strtolower($fieldName), $sensitiveFields) ||
               str_contains(strtolower($fieldName), 'contact') ||
               str_contains(strtolower($fieldName), 'phone') ||
               str_contains(strtolower($fieldName), 'email') ||
               str_contains(strtolower($fieldName), 'address');
    }

    /**
     * Anonymize a specific value based on its type
     */
    protected function anonymizeValue(string $fieldName, $value): string
    {
        if (empty($value)) {
            return $value;
        }

        $fieldName = strtolower($fieldName);

        if (str_contains($fieldName, 'email')) {
            return $this->anonymizeEmail($value);
        }

        if (str_contains($fieldName, 'phone')) {
            return $this->anonymizePhone($value);
        }

        if (str_contains($fieldName, 'address')) {
            return '[REDACTED ADDRESS]';
        }

        if (str_contains($fieldName, 'name')) {
            return $this->anonymizeName($value);
        }

        // Default anonymization
        return '[REDACTED]';
    }

    /**
     * Anonymize email address
     */
    protected function anonymizeEmail(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '[REDACTED EMAIL]';
        }

        $parts = explode('@', $email);
        $username = $parts[0];
        $domain = $parts[1];

        $anonymizedUsername = substr($username, 0, 2) . str_repeat('*', max(1, strlen($username) - 2));
        
        return $anonymizedUsername . '@' . $domain;
    }

    /**
     * Anonymize phone number
     */
    protected function anonymizePhone(string $phone): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($cleanPhone) < 4) {
            return '[REDACTED PHONE]';
        }

        return str_repeat('*', strlen($cleanPhone) - 4) . substr($cleanPhone, -4);
    }

    /**
     * Anonymize name
     */
    protected function anonymizeName(string $name): string
    {
        $parts = explode(' ', trim($name));
        $anonymizedParts = [];

        foreach ($parts as $part) {
            if (strlen($part) <= 2) {
                $anonymizedParts[] = str_repeat('*', strlen($part));
            } else {
                $anonymizedParts[] = substr($part, 0, 1) . str_repeat('*', strlen($part) - 1);
            }
        }

        return implode(' ', $anonymizedParts);
    }

    /**
     * Generate data retention report
     */
    public function generateRetentionReport(): array
    {
        $report = [
            'generated_at' => Carbon::now()->toISOString(),
            'retention_policies' => $this->getRetentionPolicies(),
            'data_categories' => $this->getDataCategories(),
            'cleanup_recommendations' => $this->getCleanupRecommendations()
        ];

        return $report;
    }

    /**
     * Get retention policies
     */
    protected function getRetentionPolicies(): array
    {
        return [
            'student_records' => [
                'retention_period' => '7 years after graduation',
                'description' => 'Academic records, grades, and enrollment information'
            ],
            'user_accounts' => [
                'retention_period' => '2 years after last login',
                'description' => 'User authentication and profile data'
            ],
            'system_logs' => [
                'retention_period' => '1 year',
                'description' => 'Application logs and audit trails'
            ],
            'analytics_data' => [
                'retention_period' => '2 years',
                'description' => 'Anonymized usage statistics and metrics'
            ],
            'communication_records' => [
                'retention_period' => '3 years',
                'description' => 'Announcements, messages, and notifications'
            ]
        ];
    }

    /**
     * Get data categories for GDPR compliance
     */
    protected function getDataCategories(): array
    {
        return [
            'personal_identifiers' => [
                'fields' => ['name', 'email', 'phone', 'address', 'id_number'],
                'legal_basis' => 'Legitimate interest for educational services',
                'retention' => 'As per retention policies'
            ],
            'academic_data' => [
                'fields' => ['grades', 'assignments', 'attendance', 'academic_records'],
                'legal_basis' => 'Contract for educational services',
                'retention' => '7 years after graduation'
            ],
            'technical_data' => [
                'fields' => ['ip_address', 'browser_info', 'session_data'],
                'legal_basis' => 'Legitimate interest for system security',
                'retention' => '1 year'
            ],
            'communication_data' => [
                'fields' => ['messages', 'announcements', 'notifications'],
                'legal_basis' => 'Legitimate interest for educational communication',
                'retention' => '3 years'
            ]
        ];
    }

    /**
     * Get cleanup recommendations
     */
    protected function getCleanupRecommendations(): array
    {
        $recommendations = [];

        // Check for old user accounts
        $inactiveUsers = \App\Models\User::where('updated_at', '<', Carbon::now()->subYears(2))->count();
        if ($inactiveUsers > 0) {
            $recommendations[] = [
                'type' => 'inactive_users',
                'count' => $inactiveUsers,
                'description' => 'Users inactive for more than 2 years should be reviewed for deletion',
                'priority' => 'medium'
            ];
        }

        // Check for old system logs
        $oldLogs = \App\Models\SystemLog::where('created_at', '<', Carbon::now()->subYear())->count();
        if ($oldLogs > 0) {
            $recommendations[] = [
                'type' => 'old_logs',
                'count' => $oldLogs,
                'description' => 'System logs older than 1 year should be archived or deleted',
                'priority' => 'low'
            ];
        }

        // Check for old analytics data
        $oldAnalytics = \App\Models\Analytics::where('created_at', '<', Carbon::now()->subYears(2))->count();
        if ($oldAnalytics > 0) {
            $recommendations[] = [
                'type' => 'old_analytics',
                'count' => $oldAnalytics,
                'description' => 'Analytics data older than 2 years should be anonymized or deleted',
                'priority' => 'medium'
            ];
        }

        return $recommendations;
    }

    /**
     * Export user data for GDPR compliance
     */
    public function exportUserData(int $userId): array
    {
        $user = \App\Models\User::findOrFail($userId);
        
        $exportData = [
            'export_date' => Carbon::now()->toISOString(),
            'user_id' => $userId,
            'personal_data' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ],
            'academic_data' => [],
            'communication_data' => [],
            'system_data' => []
        ];

        // Add academic data if user is a student
        if ($user->role === 'student') {
            $student = \App\Models\Student::where('email', $user->email)->first();
            if ($student) {
                $exportData['academic_data'] = [
                    'student_id' => $student->student_id,
                    'grade_level' => $student->grade_level,
                    'enrollment_date' => $student->enrollment_date,
                    'parent_contact' => $student->parent_contact,
                ];

                // Add assignment submissions
                $submissions = \App\Models\AssignmentSubmission::where('student_id', $student->id)
                    ->with('assignment')
                    ->get()
                    ->map(function ($submission) {
                        return [
                            'assignment_title' => $submission->assignment->title,
                            'submitted_at' => $submission->submitted_at?->toISOString(),
                            'grade' => $submission->grade,
                            'feedback' => $submission->feedback,
                        ];
                    });
                
                $exportData['academic_data']['submissions'] = $submissions;
            }
        }

        // Add teacher data if user is a teacher
        if ($user->role === 'teacher') {
            $classes = \App\Models\Classes::where('teacher_id', $user->id)->get();
            $assignments = \App\Models\Assignment::where('teacher_id', $user->id)->get();
            
            $exportData['academic_data'] = [
                'classes_taught' => $classes->pluck('name'),
                'assignments_created' => $assignments->count(),
            ];
        }

        // Add communication data
        $announcements = \App\Models\Announcement::where('created_by', $user->id)->get();
        $exportData['communication_data'] = [
            'announcements_created' => $announcements->map(function ($announcement) {
                return [
                    'title' => $announcement->title,
                    'type' => $announcement->type,
                    'created_at' => $announcement->created_at->toISOString(),
                ];
            })
        ];

        // Add system data
        $systemLogs = \App\Models\SystemLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
            
        $exportData['system_data'] = [
            'recent_activity' => $systemLogs->map(function ($log) {
                return [
                    'action' => $log->action,
                    'ip_address' => $this->anonymizeIpAddress($log->ip_address),
                    'created_at' => $log->created_at->toISOString(),
                ];
            })
        ];

        return $exportData;
    }

    /**
     * Delete user data for GDPR compliance
     */
    public function deleteUserData(int $userId, bool $softDelete = true): array
    {
        $user = \App\Models\User::findOrFail($userId);
        $deletionReport = [
            'user_id' => $userId,
            'deletion_date' => Carbon::now()->toISOString(),
            'soft_delete' => $softDelete,
            'deleted_data' => []
        ];

        if ($softDelete) {
            // Anonymize user data instead of hard delete
            $user->update([
                'name' => 'Deleted User ' . $userId,
                'email' => 'deleted_' . $userId . '@anonymized.local',
            ]);
            
            $deletionReport['deleted_data'][] = 'User profile anonymized';
        } else {
            // Hard delete - remove all related data
            
            // Delete student data if applicable
            if ($user->role === 'student') {
                $student = \App\Models\Student::where('email', $user->email)->first();
                if ($student) {
                    \App\Models\AssignmentSubmission::where('student_id', $student->id)->delete();
                    $student->delete();
                    $deletionReport['deleted_data'][] = 'Student records and submissions';
                }
            }

            // Delete teacher data if applicable
            if ($user->role === 'teacher') {
                \App\Models\Assignment::where('teacher_id', $user->id)->delete();
                \App\Models\Classes::where('teacher_id', $user->id)->update(['teacher_id' => null]);
                $deletionReport['deleted_data'][] = 'Teacher assignments and class associations';
            }

            // Delete announcements
            \App\Models\Announcement::where('created_by', $user->id)->delete();
            $deletionReport['deleted_data'][] = 'User announcements';

            // Delete system logs
            \App\Models\SystemLog::where('user_id', $user->id)->delete();
            $deletionReport['deleted_data'][] = 'System logs';

            // Finally delete the user
            $user->delete();
            $deletionReport['deleted_data'][] = 'User account';
        }

        return $deletionReport;
    }

    /**
     * Anonymize IP address for privacy
     */
    protected function anonymizeIpAddress(string $ipAddress): string
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ipAddress);
            return $parts[0] . '.' . $parts[1] . '.xxx.xxx';
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ipAddress);
            return implode(':', array_slice($parts, 0, 4)) . '::xxxx';
        }

        return '[REDACTED IP]';
    }

    /**
     * Create consent record
     */
    public function createConsentRecord(int $userId, string $consentType, array $purposes): void
    {
        \App\Models\UserConsent::create([
            'user_id' => $userId,
            'consent_type' => $consentType,
            'purposes' => json_encode($purposes),
            'granted_at' => Carbon::now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Check if user has given consent for specific purpose
     */
    public function hasConsent(int $userId, string $purpose): bool
    {
        $consent = \App\Models\UserConsent::where('user_id', $userId)
            ->where('revoked_at', null)
            ->latest()
            ->first();

        if (!$consent) {
            return false;
        }

        $purposes = json_decode($consent->purposes, true);
        return in_array($purpose, $purposes);
    }

    /**
     * Revoke user consent
     */
    public function revokeConsent(int $userId, string $reason = null): void
    {
        \App\Models\UserConsent::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => Carbon::now(),
                'revocation_reason' => $reason,
            ]);
    }
}