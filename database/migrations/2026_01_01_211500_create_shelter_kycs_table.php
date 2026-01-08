<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shelter_kycs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // KYC Documents
            $table->string('ktp_image'); // Foto KTP
            $table->string('selfie_with_ktp'); // Foto selfie sambil pegang KTP
            $table->string('address_proof')->nullable(); // Bukti alamat (opsional)

            // Additional Info
            $table->string('full_name'); // Nama lengkap sesuai KTP
            $table->string('nik', 16); // NIK 16 digit
            $table->string('phone'); // Nomor HP aktif
            $table->text('address'); // Alamat lengkap
            $table->string('city');
            $table->string('province');

            // Shelter Info (untuk dibuatkan setelah approved)
            $table->string('shelter_name'); // Nama shelter yang diinginkan
            $table->text('shelter_description')->nullable(); // Deskripsi shelter

            // Review Status
            $table->enum('status', ['pending', 'reviewing', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // User can only have one KYC submission
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shelter_kycs');
    }
};
