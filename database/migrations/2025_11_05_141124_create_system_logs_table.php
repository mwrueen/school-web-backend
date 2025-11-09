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
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level'); // error, warning, info, debug
            $table->string('type'); // error, security, performance, system
            $table->string('message');
            $table->text('context')->nullable(); // JSON context data
            $table->string('file')->nullable();
            $table->integer('line')->nullable();
            $table->text('stack_trace')->nullable();
            $table->string('user_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('request_id')->nullable();
            $table->string('session_id')->nullable();
            $table->json('metadata')->nullable(); // Additional structured data
            $table->timestamp('logged_at');
            $table->timestamps();
            
            $table->index(['level', 'logged_at']);
            $table->index(['type', 'logged_at']);
            $table->index('user_id');
            $table->index('request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
