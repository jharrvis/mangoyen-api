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
        // Add price visibility and negotiation columns to cats
        Schema::table('cats', function (Blueprint $table) {
            $table->boolean('price_visible')->default(true)->after('adoption_fee');
            $table->boolean('is_negotiable')->default(false)->after('price_visible');
        });

        // Add final price column to adoptions
        Schema::table('adoptions', function (Blueprint $table) {
            $table->decimal('final_price', 12, 2)->nullable()->after('status');
            $table->timestamp('price_negotiated_at')->nullable()->after('final_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cats', function (Blueprint $table) {
            $table->dropColumn(['price_visible', 'is_negotiable']);
        });

        Schema::table('adoptions', function (Blueprint $table) {
            $table->dropColumn(['final_price', 'price_negotiated_at']);
        });
    }
};
