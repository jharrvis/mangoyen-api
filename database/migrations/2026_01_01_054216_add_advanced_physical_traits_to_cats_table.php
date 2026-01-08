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
            $table->string('leg_type')->nullable()->after('tail_type');
            $table->string('ear_type')->nullable()->after('leg_type');
            $table->string('nose_type')->nullable()->after('ear_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cats', function (Blueprint $table) {
            $table->dropColumn(['leg_type', 'ear_type', 'nose_type']);
        });
    }
};
