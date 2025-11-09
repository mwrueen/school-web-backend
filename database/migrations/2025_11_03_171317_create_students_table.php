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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('student_id')->unique();
            $table->enum('grade_level', ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12']);
            $table->string('parent_name');
            $table->string('parent_email');
            $table->string('parent_phone');
            $table->text('address')->nullable();
            $table->date('date_of_birth');
            $table->date('enrollment_date');
            $table->enum('status', ['active', 'inactive', 'graduated', 'transferred'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
