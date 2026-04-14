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
        Schema::table('contracts', function (Blueprint $table) {
            $table->renameColumn('contract_start', 'start_date');
            $table->renameColumn('contract_end', 'end_date');
            $table->renameColumn('monthly_salary', 'contract_value');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->enum('payment_status', ['Pending', 'Partially Paid', 'Fully Paid'])->default('Pending')->after('contract_value');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['status', 'joining_date']);
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->renameColumn('start_date', 'contract_start');
            $table->renameColumn('end_date', 'contract_end');
            $table->renameColumn('contract_value', 'monthly_salary');
            $table->dropColumn('payment_status');
            $table->enum('status', ['active', 'completed', 'terminated'])->default('active');
            $table->date('joining_date')->nullable();
        });
    }
};
