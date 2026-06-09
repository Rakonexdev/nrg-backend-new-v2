<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('contract_payments', 'contract_adjustment_id')) {
            Schema::table('contract_payments', function (Blueprint $table) {
                $table->foreignId('contract_adjustment_id')->nullable()->constrained('contract_adjustments')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->dropForeign(['contract_adjustment_id']);
            $table->dropColumn('contract_adjustment_id');
        });
    }
};
