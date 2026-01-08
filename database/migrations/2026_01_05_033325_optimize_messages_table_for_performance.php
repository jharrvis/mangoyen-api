<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Add indexes to optimize message queries and prepare for archiving
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Index for faster query by adoption_id (most common query)
            $table->index('adoption_id', 'idx_messages_adoption_id');

            // Index for chronological queries
            $table->index('created_at', 'idx_messages_created_at');

            // Composite index for common query pattern: adoption + time
            $table->index(['adoption_id', 'created_at'], 'idx_messages_adoption_time');

            // Index for bot messages filtering
            $table->index('sender_id', 'idx_messages_sender_id');
        });

        // Create archive table for old messages
        Schema::create('messages_archive', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id'); // Original message ID
            $table->unsignedBigInteger('adoption_id');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->text('content');
            $table->boolean('is_censored')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('original_created_at');
            $table->timestamp('archived_at')->useCurrent();

            // Indexes for archive queries
            $table->index('adoption_id', 'idx_archive_adoption_id');
            $table->index('original_created_at', 'idx_archive_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_adoption_id');
            $table->dropIndex('idx_messages_created_at');
            $table->dropIndex('idx_messages_adoption_time');
            $table->dropIndex('idx_messages_sender_id');
        });

        Schema::dropIfExists('messages_archive');
    }
};
