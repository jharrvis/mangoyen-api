<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // First, update existing vaccination_status values to match new enum
        DB::table('cats')->whereIn('vaccination_status', ['Lengkap', 'lengkap', 'Complete', 'complete'])
            ->update(['vaccination_status' => 'complete']);
        DB::table('cats')->whereIn('vaccination_status', ['Sebagian', 'sebagian', 'Partial', 'partial'])
            ->update(['vaccination_status' => 'partial']);
        DB::table('cats')->where(function ($q) {
            $q->whereNotIn('vaccination_status', ['complete', 'partial'])
                ->orWhereNull('vaccination_status');
        })->update(['vaccination_status' => 'none']);

        Schema::table('cats', function (Blueprint $table) {
            // Only add columns if they don't exist
            if (!Schema::hasColumn('cats', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('age_months');
            }
            if (!Schema::hasColumn('cats', 'weight')) {
                $table->decimal('weight', 5, 2)->nullable()->after('color');
            }
            if (!Schema::hasColumn('cats', 'energy_level')) {
                $table->enum('energy_level', ['low', 'medium', 'high', 'hyperactive'])->default('medium')->after('description');
            }
            if (!Schema::hasColumn('cats', 'temperament')) {
                $table->enum('temperament', ['shy', 'friendly', 'clingy', 'independent'])->default('friendly')->after('energy_level');
            }
            if (!Schema::hasColumn('cats', 'good_with_kids')) {
                $table->boolean('good_with_kids')->default(false)->after('temperament');
            }
            if (!Schema::hasColumn('cats', 'good_with_cats')) {
                $table->boolean('good_with_cats')->default(false)->after('good_with_kids');
            }
            if (!Schema::hasColumn('cats', 'good_with_dogs')) {
                $table->boolean('good_with_dogs')->default(false)->after('good_with_cats');
            }
            if (!Schema::hasColumn('cats', 'indoor_only')) {
                $table->boolean('indoor_only')->default(true)->after('good_with_dogs');
            }
            if (!Schema::hasColumn('cats', 'tags')) {
                $table->text('tags')->nullable()->after('indoor_only');
            }
            if (!Schema::hasColumn('cats', 'is_dewormed')) {
                $table->boolean('is_dewormed')->default(false)->after('is_sterilized');
            }
            if (!Schema::hasColumn('cats', 'is_flea_free')) {
                $table->boolean('is_flea_free')->default(true)->after('is_dewormed');
            }
            if (!Schema::hasColumn('cats', 'special_condition')) {
                $table->string('special_condition')->nullable()->after('is_flea_free');
            }
            if (!Schema::hasColumn('cats', 'medical_notes')) {
                $table->text('medical_notes')->nullable()->after('special_condition');
            }
            if (!Schema::hasColumn('cats', 'vaccine_proof')) {
                $table->string('vaccine_proof')->nullable()->after('medical_notes');
            }
            if (!Schema::hasColumn('cats', 'certificate')) {
                $table->string('certificate')->nullable()->after('vaccine_proof');
            }
            if (!Schema::hasColumn('cats', 'awards')) {
                $table->text('awards')->nullable()->after('certificate');
            }
            if (!Schema::hasColumn('cats', 'youtube_url')) {
                $table->string('youtube_url')->nullable()->after('awards');
            }
            if (!Schema::hasColumn('cats', 'adoption_requirements')) {
                $table->text('adoption_requirements')->nullable()->after('is_urgent');
            }
        });

        // Change vaccination_status to enum
        DB::statement("ALTER TABLE cats MODIFY vaccination_status ENUM('none', 'partial', 'complete') DEFAULT 'none'");
    }

    public function down(): void
    {
        Schema::table('cats', function (Blueprint $table) {
            $columns = [
                'date_of_birth',
                'weight',
                'energy_level',
                'temperament',
                'good_with_kids',
                'good_with_cats',
                'good_with_dogs',
                'indoor_only',
                'tags',
                'is_dewormed',
                'is_flea_free',
                'special_condition',
                'medical_notes',
                'vaccine_proof',
                'certificate',
                'awards',
                'youtube_url',
                'adoption_requirements'
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('cats', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        DB::statement("ALTER TABLE cats MODIFY vaccination_status VARCHAR(255) NULL");
    }
};
