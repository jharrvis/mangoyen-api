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
            $table->string('body_type')->nullable()->after('weight');
            $table->string('coat_length')->nullable()->after('body_type');
            $table->string('coat_pattern')->nullable()->after('coat_length');
            $table->string('face_shape')->nullable()->after('coat_pattern');
            $table->string('eye_color')->nullable()->after('face_shape');
            $table->string('tail_type')->nullable()->after('eye_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cats', function (Blueprint $table) {
            $table->dropColumn([
                'body_type',
                'coat_length',
                'coat_pattern',
                'face_shape',
                'eye_color',
                'tail_type'
            ]);
        });
    }
};
