<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && ($this->user()->isTeacher() || $this->user()->isAdmin());
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-_.,!?()]+$/'
            ],
            'description' => [
                'sometimes',
                'required',
                'string',
                'min:10',
                'max:5000'
            ],
            'instructions' => [
                'nullable',
                'string',
                'max:10000'
            ],
            'class_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:classes,id'
            ],
            'subject_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:subjects,id'
            ],
            'type' => [
                'sometimes',
                'required',
                Rule::in(['homework', 'quiz', 'exam', 'project', 'lab'])
            ],
            'max_points' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:1000'
            ],
            'due_date' => [
                'sometimes',
                'required',
                'date'
            ],
            'available_from' => [
                'nullable',
                'date',
                'before:due_date'
            ],
            'available_until' => [
                'nullable',
                'date',
                'after:due_date'
            ],
            'allow_late_submission' => 'boolean',
            'late_penalty_percent' => [
                'nullable',
                'integer',
                'min:0',
                'max:100'
            ],
            'attachments' => [
                'nullable',
                'array',
                'max:10'
            ],
            'attachments.*' => [
                'string',
                'max:500'
            ],
            'is_published' => 'boolean',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Assignment title is required.',
            'title.min' => 'Title must be at least 3 characters long.',
            'title.max' => 'Title cannot exceed 255 characters.',
            'title.regex' => 'Title contains invalid characters.',
            'description.required' => 'Assignment description is required.',
            'description.min' => 'Description must be at least 10 characters long.',
            'description.max' => 'Description cannot exceed 5,000 characters.',
            'instructions.max' => 'Instructions cannot exceed 10,000 characters.',
            'class_id.required' => 'Class selection is required.',
            'class_id.exists' => 'Selected class does not exist.',
            'subject_id.required' => 'Subject selection is required.',
            'subject_id.exists' => 'Selected subject does not exist.',
            'type.required' => 'Assignment type is required.',
            'type.in' => 'Invalid assignment type selected.',
            'max_points.required' => 'Maximum points is required.',
            'max_points.min' => 'Maximum points must be at least 1.',
            'max_points.max' => 'Maximum points cannot exceed 1000.',
            'due_date.required' => 'Due date is required.',
            'available_from.before' => 'Available from date must be before due date.',
            'available_until.after' => 'Available until date must be after due date.',
            'late_penalty_percent.min' => 'Late penalty cannot be negative.',
            'late_penalty_percent.max' => 'Late penalty cannot exceed 100%.',
            'attachments.max' => 'Cannot attach more than 10 files.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize input data
        if ($this->has('title')) {
            $this->merge(['title' => $this->sanitizeString($this->title)]);
        }
        
        if ($this->has('description')) {
            $this->merge(['description' => $this->sanitizeHtml($this->description)]);
        }
        
        if ($this->has('instructions')) {
            $this->merge(['instructions' => $this->sanitizeHtml($this->instructions)]);
        }
    }

    /**
     * Sanitize string input
     */
    private function sanitizeString(?string $input): ?string
    {
        if (!$input) return $input;
        
        return trim(strip_tags($input));
    }

    /**
     * Sanitize HTML content while preserving safe tags
     */
    private function sanitizeHtml(?string $input): ?string
    {
        if (!$input) return $input;
        
        // Allow only safe HTML tags
        $allowedTags = '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6><blockquote>';
        
        return trim(strip_tags($input, $allowedTags));
    }
}