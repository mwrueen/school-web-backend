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
        DB::statement("ALTER TABLE announcements MODIFY COLUMN type ENUM('news', 'notice', 'result', 'holiday', 'event', 'achievement')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove achievement type from enum
        DB::statement("ALTER TABLE announcements MODIFY COLUMN type ENUM('news', 'notice', 'result', 'holiday', 'event')");
    }
};
