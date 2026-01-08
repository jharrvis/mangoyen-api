<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fraud_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('reporter_name')->nullable();
            $table->string('reporter_phone')->nullable();
            $table->string('perpetrator_name');
            $table->text('description');
            $table->string('evidence_path')->nullable();
            $table->enum('status', ['pending', 'investigating', 'resolved'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_reports');
    }
};
