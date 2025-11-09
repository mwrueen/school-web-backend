<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentRequest extends FormRequest
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
                'required',
                'string',
                'min:3',
                'max:255',
                'regex:/^[a-zA-Z0-9\s\-_.,!?()]+$/'
            ],
            'content' => [
                'required',
                'string',
                'min:10',
                'max:50000'
            ],
            'type' => [
                'required',
                'string',
                Rule::in(['page', 'post', 'announcement', 'news'])
            ],
            'slug' => [
                'nullable',
                'string',
                'min:3',
                'max:255',
                'regex:/^[a-z0-9\-]+$/',
                'unique:contents,slug'
            ],
            'meta_data' => [
                'nullable',
                'array'
            ],
            'meta_data.description' => [
                'nullable',
                'string',
                'max:500'
            ],
            'meta_data.keywords' => [
                'nullable',
                'string',
                'max:255'
            ],
            'template' => [
                'nullable',
                'string',
                Rule::in(['default', 'full-width', 'sidebar', 'landing'])
            ],
            'is_featured' => 'boolean',
            'status' => [
                'nullable',
                Rule::in(['draft', 'published', 'archived'])
            ],
            'change_summary' => [
                'nullable',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Content title is required.',
            'title.min' => 'Title must be at least 3 characters long.',
            'title.max' => 'Title cannot exceed 255 characters.',
            'title.regex' => 'Title contains invalid characters.',
            'content.required' => 'Content body is required.',
            'content.min' => 'Content must be at least 10 characters long.',
            'content.max' => 'Content cannot exceed 50,000 characters.',
            'type.required' => 'Content type is required.',
            'type.in' => 'Invalid content type selected.',
            'slug.min' => 'Slug must be at least 3 characters long.',
            'slug.max' => 'Slug cannot exceed 255 characters.',
            'slug.regex' => 'Slug can only contain lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'This slug is already in use.',
            'meta_data.description.max' => 'Meta description cannot exceed 500 characters.',
            'meta_data.keywords.max' => 'Meta keywords cannot exceed 255 characters.',
            'template.in' => 'Invalid template selected.',
            'status.in' => 'Invalid status selected.',
            'change_summary.max' => 'Change summary cannot exceed 500 characters.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize input data
        $this->merge([
            'title' => $this->sanitizeString($this->title),
            'content' => $this->sanitizeHtml($this->content),
            'slug' => $this->sanitizeSlug($this->slug),
            'change_summary' => $this->sanitizeString($this->change_summary),
        ]);

        // Sanitize meta_data if present
        if ($this->has('meta_data') && is_array($this->meta_data)) {
            $metaData = $this->meta_data;
            if (isset($metaData['description'])) {
                $metaData['description'] = $this->sanitizeString($metaData['description']);
            }
            if (isset($metaData['keywords'])) {
                $metaData['keywords'] = $this->sanitizeString($metaData['keywords']);
            }
            $this->merge(['meta_data' => $metaData]);
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
        
        // Allow safe HTML tags for content
        $allowedTags = '<p><br><strong><em><u><ol><ul><li><h1><h2><h3><h4><h5><h6><blockquote><a><img><table><tr><td><th><thead><tbody>';
        
        return trim(strip_tags($input, $allowedTags));
    }

    /**
     * Sanitize slug input
     */
    private function sanitizeSlug(?string $input): ?string
    {
        if (!$input) return $input;
        
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9\-]/', '', $input)));
    }
}