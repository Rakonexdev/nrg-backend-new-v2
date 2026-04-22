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
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->boolean('is_settled')->default(false)->after('created_by');
            $table->timestamp('settled_at')->nullable()->after('is_settled');
            $table->foreignId('settlement_id')->nullable()->after('settled_at')->constrained('settlements')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_payments', function (Blueprint $table) {
            $table->dropForeign(['settlement_id']);
            $table->dropColumn(['is_settled', 'settled_at', 'settlement_id']);
        });
    }
};
