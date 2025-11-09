<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For MySQL, we need to use raw SQL to modify enum
        DB::statement("ALTER TABLE resources MODIFY COLUMN resource_type ENUM('document', 'image', 'video', 'audio', 'presentation', 'spreadsheet', 'syllabus', 'other')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove syllabus type from enum
        DB::statement("ALTER TABLE resources MODIFY COLUMN resource_type ENUM('document', 'image', 'video', 'audio', 'presentation', 'spreadsheet', 'other')");
    }
};
