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
        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->string('type')->default('page'); // page, post, announcement, etc.
            $table->string('status')->default('draft'); // draft, published, archived
            $table->json('meta_data')->nullable(); // SEO, custom fields, etc.
            $table->string('template')->nullable(); // template to use for rendering
            $table->integer('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'status']);
            $table->index(['published_at', 'status']);
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};
