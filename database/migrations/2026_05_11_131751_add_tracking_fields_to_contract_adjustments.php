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
        if (!Schema::hasColumn('contract_adjustments', 'paid_amount')) {
            Schema::table('contract_adjustments', function (Blueprint $table) {
                $table->decimal('paid_amount', 15, 2)->default(0)->after('amount');
            });
        }
        if (!Schema::hasColumn('contract_adjustments', 'pending_amount')) {
            Schema::table('contract_adjustments', function (Blueprint $table) {
                $table->decimal('pending_amount', 15, 2)->default(0)->after('paid_amount');
            });
        }
        if (!Schema::hasColumn('contract_adjustments', 'next_payment_date')) {
            Schema::table('contract_adjustments', function (Blueprint $table) {
                $table->date('next_payment_date')->nullable()->after('adjustment_date');
            });
        }
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
