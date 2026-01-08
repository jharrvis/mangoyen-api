<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Modify the status enum to include new values
        DB::statement("ALTER TABLE adoptions MODIFY COLUMN status ENUM('pending', 'waiting_payment', 'payment', 'shipping', 'completed', 'cancelled', 'rejected') DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert to original enum values
        DB::statement("ALTER TABLE adoptions MODIFY COLUMN status ENUM('pending', 'payment', 'shipping', 'completed', 'cancelled') DEFAULT 'pending'");
    }
};
