<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAnnouncementRequest extends FormRequest
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
            'content' => [
                'sometimes',
                'required',
                'string',
                'min:10',
                'max:10000'
            ],
            'type' => [
                'sometimes',
                'required',
                Rule::in(Announcement::getTypes())
            ],
            'is_public' => 'sometimes|boolean',
            'published_at' => 'nullable|date',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Announcement title is required.',
            'title.min' => 'Title must be at least 3 characters long.',
            'title.max' => 'Title cannot exceed 255 characters.',
            'title.regex' => 'Title contains invalid characters.',
            'content.required' => 'Announcement content is required.',
            'content.min' => 'Content must be at least 10 characters long.',
            'content.max' => 'Content cannot exceed 10,000 characters.',
            'type.required' => 'Announcement type is required.',
            'type.in' => 'Invalid announcement type selected.',
            'published_at.date' => 'Published date must be a valid date.',
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
        
        if ($this->has('content')) {
            $this->merge(['content' => $this->sanitizeHtml($this->content)]);
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