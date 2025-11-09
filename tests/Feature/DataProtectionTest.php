<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserConsent;
use App\Services\DataProtectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected DataProtectionService $dataProtectionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dataProtectionService = app(DataProtectionService::class);
    }

    /** @test */
    public function it_can_encrypt_and_decrypt_sensitive_data()
    {
        $sensitiveData = 'user@example.com';
        
        $encrypted = $this->dataProtectionService->encryptSensitiveData($sensitiveData);
        $decrypted = $this->dataProtectionService->decryptSensitiveData($encrypted);
        
        $this->assertNotEquals($sensitiveData, $encrypted);
        $this->assertEquals($sensitiveData, $decrypted);
    }

    /** @test */
    public function it_can_hash_and_verify_sensitive_data()
    {
        $sensitiveData = 'sensitive-information';
        
        $hashed = $this->dataProtectionService->hashSensitiveData($sensitiveData);
        
        $this->assertNotEquals($sensitiveData, $hashed);
        $this->assertTrue($this->dataProtectionService->verifyHashedData($sensitiveData, $hashed));
        $this->assertFalse($this->dataProtectionService->verifyHashedData('wrong-data', $hashed));
    }

    /** @test */
    public function it_can_anonymize_personal_data()
    {
        $personalData = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1234567890',
            'address' => '123 Main St, City, Country',
            'age' => 25,
        ];

        $anonymized = $this->dataProtectionService->anonymizePersonalData($personalData);

        $this->assertNotEquals($personalData['name'], $anonymized['name']);
        $this->assertNotEquals($personalData['email'], $anonymized['email']);
        $this->assertNotEquals($personalData['phone'], $anonymized['phone']);
        $this->assertEquals('[REDACTED ADDRESS]', $anonymized['address']);
        $this->assertEquals(25, $anonymized['age']); // Non-sensitive data unchanged
    }

    /** @test */
    public function it_can_create_consent_records()
    {
        $user = User::factory()->create();

        $this->dataProtectionService->createConsentRecord(
            $user->id,
            'data_processing',
            ['academic_records', 'communication']
        );

        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id,
            'consent_type' => 'data_processing',
        ]);

        $consent = UserConsent::where('user_id', $user->id)->first();
        $this->assertEquals(['academic_records', 'communication'], $consent->purposes);
    }

    /** @test */
    public function it_can_check_user_consent()
    {
        $user = User::factory()->create();

        // No consent initially
        $this->assertFalse($this->dataProtectionService->hasConsent($user->id, 'academic_records'));

        // Grant consent
        $this->dataProtectionService->createConsentRecord(
            $user->id,
            'data_processing',
            ['academic_records', 'communication']
        );

        $this->assertTrue($this->dataProtectionService->hasConsent($user->id, 'academic_records'));
        $this->assertTrue($this->dataProtectionService->hasConsent($user->id, 'communication'));
        $this->assertFalse($this->dataProtectionService->hasConsent($user->id, 'marketing'));
    }

    /** @test */
    public function it_can_revoke_consent()
    {
        $user = User::factory()->create();

        $this->dataProtectionService->createConsentRecord(
            $user->id,
            'data_processing',
            ['academic_records']
        );

        $this->assertTrue($this->dataProtectionService->hasConsent($user->id, 'academic_records'));

        $this->dataProtectionService->revokeConsent($user->id, 'User requested revocation');

        $this->assertFalse($this->dataProtectionService->hasConsent($user->id, 'academic_records'));

        $consent = UserConsent::where('user_id', $user->id)->first();
        $this->assertNotNull($consent->revoked_at);
        $this->assertEquals('User requested revocation', $consent->revocation_reason);
    }

    /** @test */
    public function it_can_export_user_data()
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'teacher',
        ]);

        $exportData = $this->dataProtectionService->exportUserData($user->id);

        $this->assertArrayHasKey('export_date', $exportData);
        $this->assertArrayHasKey('personal_data', $exportData);
        $this->assertArrayHasKey('academic_data', $exportData);
        $this->assertArrayHasKey('communication_data', $exportData);
        $this->assertArrayHasKey('system_data', $exportData);

        $this->assertEquals($user->id, $exportData['user_id']);
        $this->assertEquals('Test User', $exportData['personal_data']['name']);
        $this->assertEquals('test@example.com', $exportData['personal_data']['email']);
    }

    /** @test */
    public function it_can_soft_delete_user_data()
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $deletionReport = $this->dataProtectionService->deleteUserData($user->id, true);

        $this->assertTrue($deletionReport['soft_delete']);
        $this->assertArrayHasKey('deleted_data', $deletionReport);

        // User should still exist but be anonymized
        $user->refresh();
        $this->assertStringContainsString('Deleted User', $user->name);
        $this->assertStringContainsString('deleted_', $user->email);
    }

    /** @test */
    public function user_can_get_consent_status()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/data-protection/consent-status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user_id',
                    'consents',
                    'available_purposes'
                ]
            ]);
    }

    /** @test */
    public function user_can_grant_consent()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/data-protection/grant-consent', [
                'consent_type' => 'data_processing',
                'purposes' => ['academic_records', 'communication'],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Consent granted successfully'
            ]);

        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id,
            'consent_type' => 'data_processing',
        ]);
    }

    /** @test */
    public function user_can_revoke_consent()
    {
        $user = User::factory()->create();

        // First grant consent
        $this->dataProtectionService->createConsentRecord(
            $user->id,
            'data_processing',
            ['academic_records']
        );

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/data-protection/revoke-consent', [
                'reason' => 'No longer needed',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Consent revoked successfully'
            ]);

        $consent = UserConsent::where('user_id', $user->id)->first();
        $this->assertNotNull($consent->revoked_at);
    }

    /** @test */
    public function user_can_export_their_data()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/data-protection/export-data');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'export_date',
                    'user_id',
                    'personal_data',
                    'academic_data',
                    'communication_data',
                    'system_data'
                ]
            ]);
    }

    /** @test */
    public function user_can_request_account_deletion()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/data-protection/request-deletion', [
                'reason' => 'No longer need the account',
                'confirm_deletion' => true,
                'soft_delete' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account anonymized successfully'
            ]);
    }

    /** @test */
    public function it_validates_consent_grant_request()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/data-protection/grant-consent', [
                'consent_type' => 'invalid_type',
                'purposes' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['consent_type', 'purposes']);
    }

    /** @test */
    public function it_validates_account_deletion_request()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/data-protection/request-deletion', [
                'confirm_deletion' => false,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason', 'confirm_deletion']);
    }

    /** @test */
    public function admin_can_get_compliance_report()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/data-protection/compliance-report');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'generated_at',
                    'retention_policies',
                    'data_categories',
                    'cleanup_recommendations',
                    'statistics'
                ]
            ]);
    }

    /** @test */
    public function non_admin_cannot_access_compliance_report()
    {
        $user = User::factory()->create(['role' => 'teacher']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/data-protection/compliance-report');

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_anonymize_user_data()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $targetUser = User::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/data-protection/anonymize-user/{$targetUser->id}", [
                'reason' => 'GDPR compliance request',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User data anonymized successfully'
            ]);
    }

    /** @test */
    public function privacy_policy_is_publicly_accessible()
    {
        $response = $this->getJson('/api/privacy-policy');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'last_updated',
                    'version',
                    'data_controller',
                    'data_categories',
                    'user_rights',
                    'contact_info'
                ]
            ]);
    }
}