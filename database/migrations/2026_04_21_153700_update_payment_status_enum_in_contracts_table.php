<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enums can't be easily updated via Schema builder in some Laravel versions/backends.
        // Using raw SQL for stability when changing enum values.
        DB::statement("ALTER TABLE contracts MODIFY COLUMN payment_status ENUM('Payment Not Initialized', 'Partially Paid', 'Fully Paid', 'Pending') DEFAULT 'Payment Not Initialized'");
        
        // Update existing 'Pending' records to 'Payment Not Initialized'
        DB::table('contracts')->where('payment_status', 'Pending')->update(['payment_status' => 'Payment Not Initialized']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE contracts MODIFY COLUMN payment_status ENUM('Pending', 'Partially Paid', 'Fully Paid') DEFAULT 'Pending'");
    }
};
