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
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->string('midtrans_order_id')->nullable()->after('payment_reference');
            $table->string('midtrans_transaction_id')->nullable()->after('midtrans_order_id');
            $table->string('snap_token')->nullable()->after('midtrans_transaction_id');
            $table->timestamp('expires_at')->nullable()->after('snap_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('escrow_transactions', function (Blueprint $table) {
            $table->dropColumn(['midtrans_order_id', 'midtrans_transaction_id', 'snap_token', 'expires_at']);
        });
    }
};
