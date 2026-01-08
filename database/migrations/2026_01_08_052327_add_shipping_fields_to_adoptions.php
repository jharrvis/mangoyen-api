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
        Schema::table('adoptions', function (Blueprint $table) {
            $table->timestamp('shipping_deadline')->nullable()->after('status');
            $table->string('tracking_number')->nullable()->after('shipping_deadline');
            $table->string('shipping_proof')->nullable()->after('tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('shipping_proof');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('adoptions', function (Blueprint $table) {
            $table->dropColumn(['shipping_deadline', 'tracking_number', 'shipping_proof', 'shipped_at']);
        });
    }
};
