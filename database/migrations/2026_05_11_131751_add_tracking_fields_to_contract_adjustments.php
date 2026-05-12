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
        Schema::table('contract_adjustments', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->default(0)->after('amount');
            $table->decimal('pending_amount', 15, 2)->default(0)->after('paid_amount');
            $table->date('next_payment_date')->nullable()->after('adjustment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_adjustments', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'pending_amount', 'next_payment_date']);
        });
    }
};
