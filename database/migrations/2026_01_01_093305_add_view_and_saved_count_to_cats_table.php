<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cats', function (Blueprint $table) {
            $table->unsignedInteger('view_count')->default(0)->after('status');
            $table->unsignedInteger('saved_count')->default(0)->after('view_count');
        });

        // Create cat_saves pivot table for tracking who saved which cats
        Schema::create('cat_saves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('cat_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'cat_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cats', function (Blueprint $table) {
            $table->dropColumn(['view_count', 'saved_count']);
        });

        Schema::dropIfExists('cat_saves');
    }
};
