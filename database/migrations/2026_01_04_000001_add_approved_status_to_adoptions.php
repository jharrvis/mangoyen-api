<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add 'approved' status before 'waiting_payment'
        DB::statement("ALTER TABLE adoptions MODIFY COLUMN status ENUM('pending', 'approved', 'waiting_payment', 'payment', 'shipping', 'completed', 'cancelled', 'rejected') DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert to previous enum values
        DB::statement("ALTER TABLE adoptions MODIFY COLUMN status ENUM('pending', 'waiting_payment', 'payment', 'shipping', 'completed', 'cancelled', 'rejected') DEFAULT 'pending'");
    }
};
