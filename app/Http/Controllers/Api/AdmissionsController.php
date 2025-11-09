<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class AdmissionsController extends Controller
{
    /**
     * Get admission process and requirements information
     */
    public function process(Request $request): JsonResponse
    {
        // Get admission process information
        $processSteps = $this->getAdmissionProcessSteps();
        $requirements = $this->getAdmissionRequirements();
        $eligibilityCriteria = $this->getEligibilityCriteria();

        return response()->json([
            'success' => true,
            'data' => [
                'process_steps' => $processSteps,
                'requirements' => $requirements,
                'eligibility_criteria' => $eligibilityCriteria,
            ],
        ]);
    }

    /**
     * Get admission deadlines and schedules
     */
    public function deadlines(Request $request): JsonResponse
    {
        // Get admission-related events
        $query = Event::public()
            ->where(function ($q) {
                $q->where('title', 'like', '%admission%')
                  ->orWhere('title', 'like', '%enrollment%')
                  ->orWhere('title', 'like', '%registration%')
                  ->orWhere('description', 'like', '%admission%')
                  ->orWhere('description', 'like', '%enrollment%')
                  ->orWhere('description', 'like', '%registration%');
            });

        // Filter by academic year if provided
        if ($request->has('academic_year')) {
            $years = explode('-', $request->academic_year);
            if (count($years) === 2) {
                $startYear = (int) $years[0];
                $endYear = (int) $years[1];
                $query->whereBetween('event_date', [
                    Carbon::create($startYear, 1, 1),
                    Carbon::create($endYear, 12, 31)
                ]);
            }
        }

        // Filter upcoming deadlines only
        if ($request->has('upcoming') && $request->boolean('upcoming')) {
            $query->upcoming();
        }

        $deadlines = $query->orderBy('event_date', 'asc')->get();

        // Get admission schedule information
        $schedule = $this->getAdmissionSchedule();

        return response()->json([
            'success' => true,
            'data' => [
                'deadlines' => $deadlines,
                'schedule' => $schedule,
            ],
        ]);
    }

    /**
     * Get fee structure and payment information
     */
    public function fees(Request $request): JsonResponse
    {
        // Filter by grade level if provided
        $gradeLevel = $request->get('grade_level');
        
        $feeStructure = $this->getFeeStructure($gradeLevel);
        $paymentMethods = $this->getPaymentMethods();
        $scholarships = $this->getScholarshipInformation();

        return response()->json([
            'success' => true,
            'data' => [
                'fee_structure' => $feeStructure,
                'payment_methods' => $paymentMethods,
                'scholarships' => $scholarships,
            ],
        ]);
    }

    /**
     * Get downloadable admission forms
     */
    public function forms(Request $request): JsonResponse
    {
        $query = Resource::public()
            ->where('resource_type', 'document')
            ->where(function ($q) {
                $q->where('title', 'like', '%admission%')
                  ->orWhere('title', 'like', '%enrollment%')
                  ->orWhere('title', 'like', '%application%')
                  ->orWhere('title', 'like', '%form%')
                  ->orWhere('description', 'like', '%admission%')
                  ->orWhere('description', 'like', '%enrollment%')
                  ->orWhere('description', 'like', '%application%');
            });

        // Filter by grade level if provided
        if ($request->has('grade_level')) {
            $query->whereHas('class', function ($q) use ($request) {
                $q->byGrade($request->grade_level);
            });
        }

        $forms = $query->orderBy('title')->get();

        // Group forms by category
        $categorizedForms = [
            'application_forms' => [],
            'information_brochures' => [],
            'fee_related' => [],
            'other' => [],
        ];

        foreach ($forms as $form) {
            $category = $this->categorizeForm($form);
            $categorizedForms[$category][] = [
                'id' => $form->id,
                'title' => $form->title,
                'description' => $form->description,
                'file_type' => $form->file_type,
                'file_size' => $form->file_size_human ?? null,
                'download_url' => route('resources.download', $form->id),
                'updated_at' => $form->updated_at,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $categorizedForms,
        ]);
    }

    /**
     * Get frequently asked questions about admissions
     */
    public function faq(Request $request): JsonResponse
    {
        $faqs = $this->getAdmissionFAQs();

        // Filter by category if provided
        if ($request->has('category')) {
            $category = $request->get('category');
            $faqs = array_filter($faqs, function ($faq) use ($category) {
                return $faq['category'] === $category;
            });
        }

        return response()->json([
            'success' => true,
            'data' => array_values($faqs),
        ]);
    }

    /**
     * Get admission contact information
     */
    public function contact(Request $request): JsonResponse
    {
        $contactInfo = $this->getAdmissionContactInfo();

        return response()->json([
            'success' => true,
            'data' => $contactInfo,
        ]);
    }

    /**
     * Get available grade levels for admission
     */
    public function availableGrades(Request $request): JsonResponse
    {
        // This could come from a configuration or database
        $availableGrades = $this->getAvailableGradesForAdmission();

        return response()->json([
            'success' => true,
            'data' => $availableGrades,
        ]);
    }

    /**
     * Get admission process steps
     */
    private function getAdmissionProcessSteps(): array
    {
        return [
            [
                'step' => 1,
                'title' => 'Online Application',
                'description' => 'Fill out the online application form with all required information',
                'duration' => '15-20 minutes',
                'required_documents' => [
                    'Student\'s birth certificate',
                    'Previous school transcripts',
                    'Passport-size photographs',
                    'Parent/guardian ID proof'
                ]
            ],
            [
                'step' => 2,
                'title' => 'Document Submission',
                'description' => 'Submit all required documents either online or at the admission office',
                'duration' => '1-2 days',
                'required_documents' => [
                    'Completed application form',
                    'Academic records',
                    'Medical certificate',
                    'Character certificate from previous school'
                ]
            ],
            [
                'step' => 3,
                'title' => 'Entrance Assessment',
                'description' => 'Attend the entrance assessment (if applicable for the grade level)',
                'duration' => '2-3 hours',
                'note' => 'Assessment dates will be communicated via email/SMS'
            ],
            [
                'step' => 4,
                'title' => 'Interview',
                'description' => 'Parent-student interview with the admission committee',
                'duration' => '30-45 minutes',
                'note' => 'Both parents and student must attend'
            ],
            [
                'step' => 5,
                'title' => 'Admission Decision',
                'description' => 'Receive admission decision and fee payment instructions',
                'duration' => '3-5 working days',
                'note' => 'Results will be communicated via email and SMS'
            ],
            [
                'step' => 6,
                'title' => 'Fee Payment & Enrollment',
                'description' => 'Pay admission fees and complete enrollment process',
                'duration' => '1-2 days',
                'note' => 'Admission is confirmed only after fee payment'
            ]
        ];
    }

    /**
     * Get admission requirements
     */
    private function getAdmissionRequirements(): array
    {
        return [
            'general_requirements' => [
                'Age criteria as per grade level',
                'Previous academic records',
                'Medical fitness certificate',
                'Character certificate from previous institution',
                'Transfer certificate (if applicable)'
            ],
            'grade_specific' => [
                'nursery_kg' => [
                    'Minimum age: 3 years for Nursery, 4 years for KG',
                    'Basic interaction skills',
                    'Toilet training completed'
                ],
                'primary' => [
                    'Age appropriate for grade level',
                    'Basic reading and writing skills',
                    'Previous school records'
                ],
                'secondary' => [
                    'Minimum 60% in previous grade',
                    'Good conduct certificate',
                    'Entrance test (for grades 6-8)'
                ],
                'higher_secondary' => [
                    'Minimum 70% in grade 10',
                    'Stream selection based on grade 10 performance',
                    'Entrance test for science stream'
                ]
            ]
        ];
    }

    /**
     * Get eligibility criteria
     */
    private function getEligibilityCriteria(): array
    {
        return [
            'age_criteria' => [
                'Nursery' => '3 years completed by March 31st',
                'KG' => '4 years completed by March 31st',
                'Grade 1' => '5 years completed by March 31st',
                'Grade 2-12' => 'Age appropriate for the grade level'
            ],
            'academic_criteria' => [
                'Grades 1-5' => 'No specific percentage requirement',
                'Grades 6-8' => 'Minimum 60% in previous grade',
                'Grades 9-10' => 'Minimum 65% in previous grade',
                'Grades 11-12' => 'Minimum 70% in grade 10 board exams'
            ],
            'special_considerations' => [
                'Sibling preference (if current student)',
                'Alumni children preference',
                'Staff children preference',
                'Defense personnel children quota'
            ]
        ];
    }

    /**
     * Get admission schedule
     */
    private function getAdmissionSchedule(): array
    {
        return [
            'application_period' => [
                'start_date' => 'December 1st',
                'end_date' => 'February 28th',
                'note' => 'Late applications may be considered based on seat availability'
            ],
            'assessment_period' => [
                'start_date' => 'March 1st',
                'end_date' => 'March 15th',
                'note' => 'Specific dates will be communicated to applicants'
            ],
            'result_declaration' => [
                'date' => 'March 25th',
                'method' => 'Email and SMS notification'
            ],
            'fee_payment_deadline' => [
                'date' => 'April 10th',
                'note' => 'Admission will be cancelled if fees are not paid by deadline'
            ],
            'session_start' => [
                'date' => 'April 15th',
                'note' => 'New academic session begins'
            ]
        ];
    }

    /**
     * Get fee structure
     */
    private function getFeeStructure(?string $gradeLevel = null): array
    {
        $feeStructure = [
            'nursery_kg' => [
                'admission_fee' => 25000,
                'tuition_fee_monthly' => 8000,
                'development_fee_annual' => 15000,
                'activity_fee_annual' => 5000,
                'transport_fee_monthly' => 3000,
                'total_first_year' => 151000
            ],
            'primary' => [
                'admission_fee' => 30000,
                'tuition_fee_monthly' => 10000,
                'development_fee_annual' => 18000,
                'activity_fee_annual' => 6000,
                'transport_fee_monthly' => 3500,
                'total_first_year' => 196000
            ],
            'secondary' => [
                'admission_fee' => 35000,
                'tuition_fee_monthly' => 12000,
                'development_fee_annual' => 20000,
                'activity_fee_annual' => 8000,
                'transport_fee_monthly' => 4000,
                'total_first_year' => 231000
            ],
            'higher_secondary' => [
                'admission_fee' => 40000,
                'tuition_fee_monthly' => 15000,
                'development_fee_annual' => 25000,
                'activity_fee_annual' => 10000,
                'transport_fee_monthly' => 4500,
                'total_first_year' => 289000
            ]
        ];

        if ($gradeLevel) {
            $category = $this->getGradeCategory($gradeLevel);
            return $feeStructure[$category] ?? $feeStructure;
        }

        return $feeStructure;
    }

    /**
     * Get payment methods
     */
    private function getPaymentMethods(): array
    {
        return [
            'online_payment' => [
                'methods' => ['Credit Card', 'Debit Card', 'Net Banking', 'UPI', 'Digital Wallets'],
                'processing_fee' => '2% of transaction amount',
                'note' => 'Instant confirmation available'
            ],
            'bank_transfer' => [
                'account_name' => 'School Management System',
                'account_number' => '1234567890',
                'ifsc_code' => 'BANK0001234',
                'bank_name' => 'State Bank of India',
                'branch' => 'Main Branch',
                'note' => 'Please mention student name and application number in remarks'
            ],
            'demand_draft' => [
                'payable_to' => 'School Management System',
                'payable_at' => 'City Name',
                'note' => 'DD should be submitted at the admission office'
            ],
            'cash_payment' => [
                'location' => 'School Admission Office',
                'timings' => 'Monday to Friday: 9:00 AM to 4:00 PM',
                'note' => 'Cash payments accepted only at the school office'
            ]
        ];
    }

    /**
     * Get scholarship information
     */
    private function getScholarshipInformation(): array
    {
        return [
            'merit_scholarship' => [
                'eligibility' => 'Top 5% students in entrance assessment',
                'benefit' => '50% tuition fee waiver for first year',
                'renewal' => 'Based on academic performance (minimum 85%)'
            ],
            'need_based_scholarship' => [
                'eligibility' => 'Family income less than ₹3,00,000 per annum',
                'benefit' => '25-75% fee concession based on income',
                'documents_required' => ['Income certificate', 'Bank statements', 'Salary slips']
            ],
            'sibling_discount' => [
                'eligibility' => 'Second child onwards',
                'benefit' => '10% discount on tuition fees',
                'note' => 'Applicable when both siblings are studying in the school'
            ],
            'sports_scholarship' => [
                'eligibility' => 'State/National level sports achievements',
                'benefit' => '25-50% fee concession',
                'documents_required' => ['Sports certificates', 'Achievement records']
            ]
        ];
    }

    /**
     * Get admission FAQs
     */
    private function getAdmissionFAQs(): array
    {
        return [
            [
                'category' => 'general',
                'question' => 'What is the admission process?',
                'answer' => 'The admission process involves online application, document submission, entrance assessment (if applicable), interview, and fee payment upon selection.'
            ],
            [
                'category' => 'general',
                'question' => 'When do admissions open?',
                'answer' => 'Admissions typically open in December and close by February end. Please check our website for exact dates.'
            ],
            [
                'category' => 'fees',
                'question' => 'What are the payment options available?',
                'answer' => 'We accept online payments, bank transfers, demand drafts, and cash payments at the school office.'
            ],
            [
                'category' => 'fees',
                'question' => 'Are there any scholarships available?',
                'answer' => 'Yes, we offer merit-based, need-based, sibling discount, and sports scholarships. Please check the fee structure section for details.'
            ],
            [
                'category' => 'documents',
                'question' => 'What documents are required for admission?',
                'answer' => 'Birth certificate, previous school records, medical certificate, character certificate, and parent ID proof are required.'
            ],
            [
                'category' => 'documents',
                'question' => 'Can I submit documents online?',
                'answer' => 'Yes, you can upload scanned copies during online application. Original documents need to be verified at the time of admission.'
            ],
            [
                'category' => 'assessment',
                'question' => 'Is there an entrance test?',
                'answer' => 'Entrance assessment is conducted for grades 6 and above. For younger grades, interaction-based assessment is done.'
            ],
            [
                'category' => 'assessment',
                'question' => 'What is the syllabus for entrance test?',
                'answer' => 'The test is based on the previous grade curriculum. Sample papers are available on our website.'
            ]
        ];
    }

    /**
     * Get admission contact information
     */
    private function getAdmissionContactInfo(): array
    {
        return [
            'office_address' => [
                'street' => '123 Education Street',
                'city' => 'City Name',
                'state' => 'State Name',
                'pincode' => '123456',
                'country' => 'India'
            ],
            'contact_numbers' => [
                'primary' => '+91-9876543210',
                'secondary' => '+91-9876543211',
                'landline' => '011-12345678'
            ],
            'email_addresses' => [
                'admissions' => 'admissions@school.edu',
                'general' => 'info@school.edu'
            ],
            'office_hours' => [
                'weekdays' => 'Monday to Friday: 9:00 AM to 5:00 PM',
                'saturday' => 'Saturday: 9:00 AM to 1:00 PM',
                'sunday' => 'Closed'
            ],
            'online_support' => [
                'chat_available' => 'Monday to Friday: 10:00 AM to 4:00 PM',
                'response_time' => 'Within 24 hours for email queries'
            ]
        ];
    }

    /**
     * Get available grades for admission
     */
    private function getAvailableGradesForAdmission(): array
    {
        return [
            'nursery' => ['name' => 'Nursery', 'age_range' => '3-4 years'],
            'kg' => ['name' => 'Kindergarten', 'age_range' => '4-5 years'],
            '1' => ['name' => 'Grade 1', 'age_range' => '5-6 years'],
            '2' => ['name' => 'Grade 2', 'age_range' => '6-7 years'],
            '3' => ['name' => 'Grade 3', 'age_range' => '7-8 years'],
            '4' => ['name' => 'Grade 4', 'age_range' => '8-9 years'],
            '5' => ['name' => 'Grade 5', 'age_range' => '9-10 years'],
            '6' => ['name' => 'Grade 6', 'age_range' => '10-11 years'],
            '7' => ['name' => 'Grade 7', 'age_range' => '11-12 years'],
            '8' => ['name' => 'Grade 8', 'age_range' => '12-13 years'],
            '9' => ['name' => 'Grade 9', 'age_range' => '13-14 years'],
            '10' => ['name' => 'Grade 10', 'age_range' => '14-15 years'],
            '11' => ['name' => 'Grade 11', 'age_range' => '15-16 years'],
            '12' => ['name' => 'Grade 12', 'age_range' => '16-17 years'],
        ];
    }

    /**
     * Categorize admission forms
     */
    private function categorizeForm($form): string
    {
        $title = strtolower($form->title);
        $description = strtolower($form->description ?? '');

        if (strpos($title, 'application') !== false || strpos($description, 'application') !== false) {
            return 'application_forms';
        }

        if (strpos($title, 'brochure') !== false || strpos($title, 'prospectus') !== false || 
            strpos($description, 'brochure') !== false || strpos($description, 'prospectus') !== false) {
            return 'information_brochures';
        }

        if (strpos($title, 'fee') !== false || strpos($title, 'payment') !== false || 
            strpos($description, 'fee') !== false || strpos($description, 'payment') !== false) {
            return 'fee_related';
        }

        return 'other';
    }

    /**
     * Get grade category for fee structure
     */
    private function getGradeCategory(string $gradeLevel): string
    {
        if (in_array($gradeLevel, ['nursery', 'kg'])) {
            return 'nursery_kg';
        }

        if (in_array($gradeLevel, ['1', '2', '3', '4', '5'])) {
            return 'primary';
        }

        if (in_array($gradeLevel, ['6', '7', '8', '9', '10'])) {
            return 'secondary';
        }

        if (in_array($gradeLevel, ['11', '12'])) {
            return 'higher_secondary';
        }

        return 'primary'; // default
    }
}