<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shelter_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('breed')->nullable();
            $table->enum('age_category', ['kitten', 'adult'])->default('adult');
            $table->unsignedInteger('age_months')->nullable();
            $table->enum('gender', ['jantan', 'betina']);
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->text('health_status')->nullable();
            $table->string('vaccination_status')->nullable();
            $table->boolean('is_sterilized')->default(false);
            $table->decimal('adoption_fee', 12, 2)->default(0);
            $table->enum('status', ['available', 'booked', 'adopted'])->default('available');
            $table->boolean('is_urgent')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cats');
    }
};
