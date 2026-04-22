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
            $table->decimal('qid_renewal_fee', 10, 2)->nullable()->default(0)->change();
            $table->decimal('passport_renewal_fee', 10, 2)->nullable()->default(0)->change();
            $table->decimal('profession_change_fee', 10, 2)->nullable()->default(0)->change();
            $table->decimal('sponsorship_change_fee', 10, 2)->nullable()->default(0)->change();
            $table->decimal('health_card_fee', 10, 2)->nullable()->default(0)->change();
            $table->decimal('others_fee', 10, 2)->nullable()->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('qid_renewal_fee', 10, 2)->nullable(false)->default(0)->change();
            $table->decimal('passport_renewal_fee', 10, 2)->nullable(false)->default(0)->change();
            $table->decimal('profession_change_fee', 10, 2)->nullable(false)->default(0)->change();
            $table->decimal('sponsorship_change_fee', 10, 2)->nullable(false)->default(0)->change();
            $table->decimal('health_card_fee', 10, 2)->nullable(false)->default(0)->change();
            $table->decimal('others_fee', 10, 2)->nullable(false)->default(0)->change();
        });
    }
};
