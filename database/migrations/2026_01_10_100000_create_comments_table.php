<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->onDelete('cascade');

            // Polymorphic author - can be user, shelter, or guest
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('shelter_id')->nullable()->constrained('shelters')->onDelete('cascade');

            // Guest info (when not logged in)
            $table->string('guest_name', 100)->nullable();
            $table->string('guest_email', 150)->nullable();

            // Reply support
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');

            // Content
            $table->text('content');

            // Moderation
            $table->enum('status', ['pending', 'approved', 'spam', 'rejected'])->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');

            // Spam detection data
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['article_id', 'status']);
            $table->index('parent_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
