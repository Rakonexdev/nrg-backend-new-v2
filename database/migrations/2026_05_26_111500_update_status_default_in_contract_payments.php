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
        if (Schema::hasColumn('contract_payments', 'status')) {
            try {
                DB::statement("ALTER TABLE contract_payments MODIFY status VARCHAR(255) DEFAULT 'not_collected'");
            } catch (\Throwable $e) {
                // Ignore fallback
            }
            DB::table('contract_payments')->whereNull('status')->orWhere('status', '')->update(['status' => 'not_collected']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('contract_payments', 'status')) {
            try {
                DB::statement("ALTER TABLE contract_payments MODIFY status VARCHAR(255) DEFAULT 'collected'");
            } catch (\Throwable $e) {
                // Ignore fallback
            }
        }
    }
};
