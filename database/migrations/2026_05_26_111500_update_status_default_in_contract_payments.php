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
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->string('status')->default('not_collected')->change();
        });
        
        // Update all existing to not_collected
        DB::table('contract_payments')->update(['status' => 'not_collected']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->string('status')->default('collected')->change();
        });
    }
};
