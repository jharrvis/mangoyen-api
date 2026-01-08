<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('membership_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // anak-bawang, sultan-meong, crazy-cat-lord
            $table->string('name'); // Display name
            $table->text('description')->nullable();
            $table->decimal('price', 12, 0); // Price in IDR
            $table->unsignedInteger('duration_months')->default(12); // Subscription duration

            // Limits
            $table->unsignedInteger('max_cats')->default(5); // Max active cats
            $table->unsignedInteger('max_photos_per_cat')->default(3);
            $table->unsignedInteger('max_videos_per_cat')->default(0);

            // Features
            $table->unsignedInteger('featured_slots_per_month')->default(0);
            $table->unsignedInteger('catalog_boost_percent')->default(0); // Priority in listing
            $table->string('badge_type')->default('basic'); // basic, gold, diamond
            $table->unsignedInteger('max_admin_accounts')->default(1); // Multi-admin
            $table->boolean('has_promo_banner')->default(false);
            $table->boolean('priority_support')->default(false);

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Add membership_tier_id to shelters
        Schema::table('shelters', function (Blueprint $table) {
            $table->foreignId('membership_tier_id')->nullable()->after('is_verified')->constrained('membership_tiers')->nullOnDelete();
            $table->timestamp('membership_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('shelters', function (Blueprint $table) {
            $table->dropForeign(['membership_tier_id']);
            $table->dropColumn(['membership_tier_id', 'membership_expires_at']);
        });

        Schema::dropIfExists('membership_tiers');
    }
};
