<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DataProtectionService;
use App\Models\UserConsent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DataProtectionController extends Controller
{
    protected DataProtectionService $dataProtectionService;

    public function __construct(DataProtectionService $dataProtectionService)
    {
        $this->dataProtectionService = $dataProtectionService;
    }

    /**
     * Get user's consent status
     */
    public function getConsentStatus(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $consents = UserConsent::where('user_id', $user->id)
            ->active()
            ->get()
            ->groupBy('consent_type');

        $consentStatus = [];
        foreach (UserConsent::getConsentTypes() as $type => $label) {
            $consent = $consents->get($type)?->first();
            $consentStatus[$type] = [
                'label' => $label,
                'granted' => $consent !== null,
                'granted_at' => $consent?->granted_at?->toISOString(),
                'purposes' => $consent?->purposes ?? [],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'consents' => $consentStatus,
                'available_purposes' => UserConsent::getAvailablePurposes(),
            ]
        ]);
    }

    /**
     * Grant consent for data processing
     */
    public function grantConsent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'consent_type' => 'required|string|in:' . implode(',', array_keys(UserConsent::getConsentTypes())),
            'purposes' => 'required|array|min:1',
            'purposes.*' => 'string|in:' . implode(',', array_keys(UserConsent::getAvailablePurposes())),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Revoke any existing consent of the same type
        $this->dataProtectionService->revokeConsent($user->id);

        // Create new consent record
        $this->dataProtectionService->createConsentRecord(
            $user->id,
            $request->consent_type,
            $request->purposes
        );

        return response()->json([
            'success' => true,
            'message' => 'Consent granted successfully',
            'data' => [
                'consent_type' => $request->consent_type,
                'purposes' => $request->purposes,
                'granted_at' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Revoke consent
     */
    public function revokeConsent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        $this->dataProtectionService->revokeConsent($user->id, $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Consent revoked successfully',
            'data' => [
                'revoked_at' => now()->toISOString(),
                'reason' => $request->reason,
            ]
        ]);
    }

    /**
     * Export user data (GDPR Article 20 - Right to data portability)
     */
    public function exportUserData(Request $request): JsonResponse
    {
        $user = Auth::user();

        try {
            $exportData = $this->dataProtectionService->exportUserData($user->id);

            return response()->json([
                'success' => true,
                'message' => 'User data exported successfully',
                'data' => $exportData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export user data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request account deletion (GDPR Article 17 - Right to erasure)
     */
    public function requestAccountDeletion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'confirm_deletion' => 'required|boolean|accepted',
            'soft_delete' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $softDelete = $request->get('soft_delete', true);

        try {
            $deletionReport = $this->dataProtectionService->deleteUserData($user->id, $softDelete);

            return response()->json([
                'success' => true,
                'message' => $softDelete ? 'Account anonymized successfully' : 'Account deleted successfully',
                'data' => $deletionReport
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process deletion request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get data retention policies
     */
    public function getRetentionPolicies(): JsonResponse
    {
        $report = $this->dataProtectionService->generateRetentionReport();

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    /**
     * Admin: Get data protection compliance report
     */
    public function getComplianceReport(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin role required.'
            ], 403);
        }

        $report = $this->dataProtectionService->generateRetentionReport();

        // Add additional admin statistics
        $report['statistics'] = [
            'total_users' => \App\Models\User::count(),
            'active_consents' => UserConsent::active()->count(),
            'revoked_consents' => UserConsent::whereNotNull('revoked_at')->count(),
            'users_with_consent' => UserConsent::active()->distinct('user_id')->count(),
            'consent_by_type' => UserConsent::active()
                ->selectRaw('consent_type, COUNT(*) as count')
                ->groupBy('consent_type')
                ->pluck('count', 'consent_type'),
        ];

        return response()->json([
            'success' => true,
            'data' => $report
        ]);
    }

    /**
     * Admin: Anonymize user data
     */
    public function anonymizeUserData(Request $request, int $userId): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Admin role required.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deletionReport = $this->dataProtectionService->deleteUserData($userId, true);

            return response()->json([
                'success' => true,
                'message' => 'User data anonymized successfully',
                'data' => $deletionReport
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to anonymize user data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get privacy policy information
     */
    public function getPrivacyPolicy(): JsonResponse
    {
        $privacyPolicy = [
            'last_updated' => '2025-11-05',
            'version' => '1.0',
            'data_controller' => [
                'name' => 'School Management System',
                'contact' => 'privacy@school.edu',
                'address' => 'School Address, City, Country',
            ],
            'data_categories' => $this->dataProtectionService->generateRetentionReport()['data_categories'],
            'user_rights' => [
                'access' => 'Right to access your personal data',
                'rectification' => 'Right to correct inaccurate data',
                'erasure' => 'Right to request deletion of your data',
                'portability' => 'Right to export your data',
                'restriction' => 'Right to restrict processing',
                'objection' => 'Right to object to processing',
                'withdraw_consent' => 'Right to withdraw consent at any time',
            ],
            'contact_info' => [
                'data_protection_officer' => 'dpo@school.edu',
                'privacy_team' => 'privacy@school.edu',
                'phone' => '+1-555-0123',
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $privacyPolicy
        ]);
    }
}