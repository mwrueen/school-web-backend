<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->text('instructions')->nullable();
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['homework', 'quiz', 'exam', 'project', 'lab'])->default('homework');
            $table->integer('max_points')->default(100);
            $table->dateTime('due_date');
            $table->dateTime('available_from')->nullable();
            $table->dateTime('available_until')->nullable();
            $table->boolean('allow_late_submission')->default(false);
            $table->integer('late_penalty_percent')->default(0);
            $table->json('attachments')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            
            $table->index(['class_id', 'subject_id']);
            $table->index(['teacher_id']);
            $table->index(['due_date']);
            $table->index(['is_published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
