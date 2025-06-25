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
        // Create comments table
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade'); // For nested comments
            $table->text('content');
            $table->boolean('is_edited')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes(); // Soft delete for moderation
            $table->timestamps();

            // Indexes for performance
            $table->index(['post_id', 'created_at']);
            $table->index(['post_id', 'parent_id']);
            $table->index('user_id');
        });

        // Create comment_likes pivot table
        Schema::create('comment_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Ensure a user can only like a comment once
            $table->unique(['comment_id', 'user_id']);
            $table->index('comment_id'); // For counting likes
        });

        // Create comment_dislikes pivot table
        Schema::create('comment_dislikes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Ensure a user can only dislike a comment once
            $table->unique(['comment_id', 'user_id']);
            $table->index('comment_id'); // For counting dislikes
        });

        // Create comment_reports pivot table
        Schema::create('comment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('reason')->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'resolved', 'dismissed'])->default('pending');
            $table->timestamps();

            // Ensure a user can only report a comment once
            $table->unique(['comment_id', 'user_id']);
            $table->index(['comment_id', 'status']); // For moderation queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_reports');
        Schema::dropIfExists('comment_dislikes');
        Schema::dropIfExists('comment_likes');
        Schema::dropIfExists('comments');
    }
};
