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
            $table->decimal('qid_renewal_fee', 10, 2)->default(0);
            $table->date('qid_next_renewal_date')->nullable();
            $table->decimal('passport_renewal_fee', 10, 2)->default(0);
            $table->decimal('profession_change_fee', 10, 2)->default(0);
            $table->decimal('sponsorship_change_fee', 10, 2)->default(0);
            $table->decimal('health_card_fee', 10, 2)->default(0);
            $table->decimal('others_fee', 10, 2)->default(0);

            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
            $table->decimal('contract_value', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'qid_renewal_fee',
                'qid_next_renewal_date',
                'passport_renewal_fee',
                'profession_change_fee',
                'sponsorship_change_fee',
                'health_card_fee',
                'others_fee'
            ]);
        });
    }
};
