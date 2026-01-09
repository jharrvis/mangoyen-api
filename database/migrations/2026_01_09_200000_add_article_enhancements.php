<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add new fields to articles table
        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('author_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->string('meta_title')->nullable()->after('thumbnail');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->unsignedInteger('reading_time')->nullable()->after('meta_description');
            $table->unsignedInteger('view_count')->default(0)->after('reading_time');
            $table->boolean('is_ai_generated')->default(false)->after('view_count');
        });

        // Create tags table
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Create pivot table for article-tag relationship
        Schema::create('article_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['article_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_tag');
        Schema::dropIfExists('tags');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->dropColumn([
                'author_id',
                'meta_title',
                'meta_description',
                'reading_time',
                'view_count',
                'is_ai_generated'
            ]);
        });
    }
};
