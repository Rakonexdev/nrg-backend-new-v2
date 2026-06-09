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
        if (Schema::hasColumn('visa_payments', 'method')) {
            return;
        }
        Schema::table('visa_payments', function (Blueprint $table) {
            $table->string('method')->nullable()->after('payment_date');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visa_payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['method', 'user_id']);
        });
    }
};
