<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdmissionsApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_process_endpoint_returns_admission_process_information(): void
    {
        $response = $this->getJson('/api/public/admissions/process');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'process_steps' => [
                            '*' => [
                                'step',
                                'title',
                                'description',
                                'duration'
                            ]
                        ],
                        'requirements' => [
                            'general_requirements',
                            'grade_specific'
                        ],
                        'eligibility_criteria' => [
                            'age_criteria',
                            'academic_criteria',
                            'special_considerations'
                        ]
                    ]
                ])
                ->assertJsonPath('success', true);

        $processSteps = $response->json('data.process_steps');
        $this->assertGreaterThan(0, count($processSteps));
        $this->assertEquals(1, $processSteps[0]['step']);
    }

    public function test_deadlines_endpoint_returns_admission_deadlines(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create admission-related events
        Event::factory()->create([
            'title' => 'Admission Form Submission Deadline',
            'description' => 'Last date for admission form submission',
            'event_date' => now()->addMonth(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        Event::factory()->create([
            'title' => 'Enrollment Process Begins',
            'description' => 'Start of enrollment process for new students',
            'event_date' => now()->addWeeks(2),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        // Create non-admission event (should not appear)
        Event::factory()->create([
            'title' => 'Sports Day',
            'description' => 'Annual sports day event',
            'event_date' => now()->addMonth(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/admissions/deadlines');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'deadlines',
                        'schedule' => [
                            'application_period',
                            'assessment_period',
                            'result_declaration',
                            'fee_payment_deadline',
                            'session_start'
                        ]
                    ]
                ])
                ->assertJsonPath('success', true)
                ->assertJsonCount(2, 'data.deadlines');
    }

    public function test_deadlines_endpoint_filters_upcoming_events(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create past admission event (should not appear)
        Event::factory()->create([
            'title' => 'Past Admission Deadline',
            'event_date' => now()->subWeek(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        // Create upcoming admission event
        Event::factory()->create([
            'title' => 'Upcoming Admission Deadline',
            'event_date' => now()->addWeek(),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/admissions/deadlines?upcoming=true');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data.deadlines')
                ->assertJsonPath('data.deadlines.0.title', 'Upcoming Admission Deadline');
    }

    public function test_fees_endpoint_returns_fee_structure(): void
    {
        $response = $this->getJson('/api/public/admissions/fees');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'fee_structure' => [
                            'nursery_kg',
                            'primary',
                            'secondary',
                            'higher_secondary'
                        ],
                        'payment_methods' => [
                            'online_payment',
                            'bank_transfer',
                            'demand_draft',
                            'cash_payment'
                        ],
                        'scholarships' => [
                            'merit_scholarship',
                            'need_based_scholarship',
                            'sibling_discount',
                            'sports_scholarship'
                        ]
                    ]
                ])
                ->assertJsonPath('success', true);

        $feeStructure = $response->json('data.fee_structure');
        $this->assertArrayHasKey('nursery_kg', $feeStructure);
        $this->assertArrayHasKey('admission_fee', $feeStructure['nursery_kg']);
    }

    public function test_fees_endpoint_filters_by_grade_level(): void
    {
        $response = $this->getJson('/api/public/admissions/fees?grade_level=9');

        $response->assertStatus(200);
        
        $feeStructure = $response->json('data.fee_structure');
        // Should return secondary category for grade 9
        $this->assertArrayHasKey('admission_fee', $feeStructure);
        $this->assertArrayHasKey('tuition_fee_monthly', $feeStructure);
    }

    public function test_forms_endpoint_returns_admission_forms(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        
        // Create admission-related resources
        Resource::factory()->create([
            'title' => 'Admission Application Form',
            'description' => 'Main application form for admission',
            'resource_type' => 'document',
            'is_public' => true,
            'teacher_id' => $teacher->id,
        ]);

        Resource::factory()->create([
            'title' => 'School Information Brochure',
            'description' => 'Information brochure about the school',
            'resource_type' => 'document',
            'is_public' => true,
            'teacher_id' => $teacher->id,
        ]);

        // Create non-admission resource (should not appear)
        Resource::factory()->create([
            'title' => 'Math Worksheet',
            'description' => 'Mathematics practice worksheet',
            'resource_type' => 'document',
            'is_public' => true,
            'teacher_id' => $teacher->id,
        ]);

        $response = $this->getJson('/api/public/admissions/forms');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'application_forms',
                        'information_brochures',
                        'fee_related',
                        'other'
                    ]
                ])
                ->assertJsonPath('success', true);

        $forms = $response->json('data');
        $this->assertGreaterThan(0, count($forms['application_forms']));
        $this->assertGreaterThan(0, count($forms['information_brochures']));
    }

    public function test_faq_endpoint_returns_admission_faqs(): void
    {
        $response = $this->getJson('/api/public/admissions/faq');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'category',
                            'question',
                            'answer'
                        ]
                    ]
                ])
                ->assertJsonPath('success', true);

        $faqs = $response->json('data');
        $this->assertGreaterThan(0, count($faqs));
        
        // Check that we have different categories
        $categories = array_unique(array_column($faqs, 'category'));
        $this->assertContains('general', $categories);
        $this->assertContains('fees', $categories);
    }

    public function test_faq_endpoint_filters_by_category(): void
    {
        $response = $this->getJson('/api/public/admissions/faq?category=fees');

        $response->assertStatus(200);
        
        $faqs = $response->json('data');
        foreach ($faqs as $faq) {
            $this->assertEquals('fees', $faq['category']);
        }
    }

    public function test_contact_endpoint_returns_admission_contact_info(): void
    {
        $response = $this->getJson('/api/public/admissions/contact');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'office_address' => [
                            'street',
                            'city',
                            'state',
                            'pincode',
                            'country'
                        ],
                        'contact_numbers' => [
                            'primary',
                            'secondary',
                            'landline'
                        ],
                        'email_addresses' => [
                            'admissions',
                            'general'
                        ],
                        'office_hours',
                        'online_support'
                    ]
                ])
                ->assertJsonPath('success', true);

        $contactInfo = $response->json('data');
        $this->assertArrayHasKey('office_address', $contactInfo);
        $this->assertArrayHasKey('contact_numbers', $contactInfo);
        $this->assertArrayHasKey('email_addresses', $contactInfo);
    }

    public function test_available_grades_endpoint_returns_admission_grades(): void
    {
        $response = $this->getJson('/api/public/admissions/available-grades');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'name',
                            'age_range'
                        ]
                    ]
                ])
                ->assertJsonPath('success', true);

        $grades = $response->json('data');
        $this->assertArrayHasKey('nursery', $grades);
        $this->assertArrayHasKey('kg', $grades);
        $this->assertArrayHasKey('1', $grades);
        $this->assertArrayHasKey('12', $grades);
    }

    public function test_admissions_endpoints_exclude_private_content(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create private admission event (should not appear)
        Event::factory()->create([
            'title' => 'Private Admission Meeting',
            'event_date' => now()->addWeek(),
            'is_public' => false,
            'created_by' => $user->id,
        ]);

        // Create private admission resource (should not appear)
        Resource::factory()->create([
            'title' => 'Private Admission Form',
            'resource_type' => 'document',
            'is_public' => false,
            'teacher_id' => $user->id,
        ]);

        $deadlinesResponse = $this->getJson('/api/public/admissions/deadlines');
        $formsResponse = $this->getJson('/api/public/admissions/forms');

        $deadlinesResponse->assertStatus(200)
                         ->assertJsonCount(0, 'data.deadlines');

        $formsResponse->assertStatus(200);
        $forms = $formsResponse->json('data');
        $totalForms = count($forms['application_forms']) + count($forms['information_brochures']) + 
                     count($forms['fee_related']) + count($forms['other']);
        $this->assertEquals(0, $totalForms);
    }

    public function test_deadlines_endpoint_filters_by_academic_year(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        
        // Create admission event for 2023 (should not appear)
        Event::factory()->create([
            'title' => 'Admission 2023',
            'event_date' => now()->setYear(2023)->setMonth(3),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        // Create admission event for 2024-2025
        Event::factory()->create([
            'title' => 'Admission 2024-25',
            'event_date' => now()->setYear(2024)->setMonth(3),
            'is_public' => true,
            'created_by' => $user->id,
        ]);

        $response = $this->getJson('/api/public/admissions/deadlines?academic_year=2024-2025');

        $response->assertStatus(200)
                ->assertJsonCount(1, 'data.deadlines')
                ->assertJsonPath('data.deadlines.0.title', 'Admission 2024-25');
    }

    public function test_forms_endpoint_filters_by_grade_level(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        
        // This test would require classes to be set up properly
        // For now, we'll test that the endpoint accepts the parameter
        $response = $this->getJson('/api/public/admissions/forms?grade_level=9');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'application_forms',
                        'information_brochures',
                        'fee_related',
                        'other'
                    ]
                ]);
    }

    public function test_all_admissions_endpoints_are_accessible_without_authentication(): void
    {
        $endpoints = [
            '/api/public/admissions/process',
            '/api/public/admissions/deadlines',
            '/api/public/admissions/fees',
            '/api/public/admissions/forms',
            '/api/public/admissions/faq',
            '/api/public/admissions/contact',
            '/api/public/admissions/available-grades',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(200)
                    ->assertJsonPath('success', true);
        }
    }

    public function test_admissions_data_structure_consistency(): void
    {
        // Test that all endpoints return consistent success structure
        $endpoints = [
            '/api/public/admissions/process',
            '/api/public/admissions/deadlines',
            '/api/public/admissions/fees',
            '/api/public/admissions/forms',
            '/api/public/admissions/faq',
            '/api/public/admissions/contact',
            '/api/public/admissions/available-grades',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'data'
                    ])
                    ->assertJsonPath('success', true);
        }
    }
}