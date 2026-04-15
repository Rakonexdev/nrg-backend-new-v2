<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('paid_amount', 10, 2)->default(0)->after('contract_value');
            $table->decimal('pending_amount', 10, 2)->default(0)->after('paid_amount');
        });

        DB::table('contracts')->update([
            'paid_amount' => 0,
            'pending_amount' => DB::raw('contract_value'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'pending_amount']);
        });
    }
};
