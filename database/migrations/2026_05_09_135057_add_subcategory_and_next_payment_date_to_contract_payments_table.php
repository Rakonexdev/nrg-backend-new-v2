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
        if (!Schema::hasColumn('contract_payments', 'subcategory')) {
            Schema::table('contract_payments', function (Blueprint $table) {
                $table->string('subcategory')->nullable()->after('payment_method');
            });
        }
        if (!Schema::hasColumn('contract_payments', 'next_payment_date')) {
            Schema::table('contract_payments', function (Blueprint $table) {
                $table->date('next_payment_date')->nullable()->after('subcategory');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->dropColumn(['subcategory', 'next_payment_date']);
        });
    }
};
