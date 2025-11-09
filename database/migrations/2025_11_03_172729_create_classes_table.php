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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('grade_level', ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']);
            $table->string('section')->default('A');
            $table->string('academic_year');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->integer('max_students')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['grade_level', 'section', 'academic_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
