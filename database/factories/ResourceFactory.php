<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Resource>
 */
class ResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $resourceTypes = ['document', 'image', 'video', 'audio', 'presentation', 'spreadsheet', 'other'];
        $resourceType = $this->faker->randomElement($resourceTypes);
        
        // Generate appropriate file data based on resource type
        $fileData = $this->generateFileData($resourceType);

        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->optional(0.7)->paragraphs(2, true),
            'file_name' => $fileData['file_name'],
            'file_path' => 'resources/' . $this->faker->uuid() . '.' . $fileData['extension'],
            'file_type' => $fileData['extension'],
            'mime_type' => $fileData['mime_type'],
            'file_size' => $this->faker->numberBetween(1024, 10 * 1024 * 1024), // 1KB to 10MB
            'resource_type' => $resourceType,
            'is_public' => $this->faker->boolean(70), // 70% chance of being public
            'subject_id' => \App\Models\Subject::factory(),
            'class_id' => \App\Models\Classes::factory(),
            'teacher_id' => \App\Models\User::factory(),
        ];
    }

    /**
     * Generate file data based on resource type
     */
    private function generateFileData(string $resourceType): array
    {
        $fileTypes = [
            'document' => [
                ['extension' => 'pdf', 'mime_type' => 'application/pdf'],
                ['extension' => 'docx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                ['extension' => 'txt', 'mime_type' => 'text/plain'],
            ],
            'image' => [
                ['extension' => 'jpg', 'mime_type' => 'image/jpeg'],
                ['extension' => 'png', 'mime_type' => 'image/png'],
                ['extension' => 'gif', 'mime_type' => 'image/gif'],
            ],
            'video' => [
                ['extension' => 'mp4', 'mime_type' => 'video/mp4'],
                ['extension' => 'mov', 'mime_type' => 'video/quicktime'],
            ],
            'audio' => [
                ['extension' => 'mp3', 'mime_type' => 'audio/mpeg'],
                ['extension' => 'wav', 'mime_type' => 'audio/wav'],
            ],
            'presentation' => [
                ['extension' => 'pptx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            ],
            'spreadsheet' => [
                ['extension' => 'xlsx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                ['extension' => 'csv', 'mime_type' => 'text/csv'],
            ],
            'other' => [
                ['extension' => 'zip', 'mime_type' => 'application/zip'],
            ],
        ];

        $typeData = $fileTypes[$resourceType] ?? $fileTypes['other'];
        $selectedType = $this->faker->randomElement($typeData);
        
        return [
            'file_name' => $this->faker->words(2, true) . '.' . $selectedType['extension'],
            'extension' => $selectedType['extension'],
            'mime_type' => $selectedType['mime_type'],
        ];
    }

    /**
     * Indicate that the resource is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    /**
     * Indicate that the resource is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    /**
     * Indicate that the resource is of a specific type.
     */
    public function ofType(string $type): static
    {
        $fileData = $this->generateFileData($type);
        
        return $this->state(fn (array $attributes) => [
            'resource_type' => $type,
            'file_name' => $fileData['file_name'],
            'file_type' => $fileData['extension'],
            'mime_type' => $fileData['mime_type'],
        ]);
    }

    /**
     * Indicate that the resource belongs to a specific subject.
     */
    public function forSubject($subject): static
    {
        return $this->state(fn (array $attributes) => [
            'subject_id' => is_object($subject) ? $subject->id : $subject,
        ]);
    }

    /**
     * Indicate that the resource belongs to a specific class.
     */
    public function forClass($class): static
    {
        return $this->state(fn (array $attributes) => [
            'class_id' => is_object($class) ? $class->id : $class,
        ]);
    }

    /**
     * Indicate that the resource was created by a specific teacher.
     */
    public function byTeacher($teacher): static
    {
        return $this->state(fn (array $attributes) => [
            'teacher_id' => is_object($teacher) ? $teacher->id : $teacher,
        ]);
    }
}
