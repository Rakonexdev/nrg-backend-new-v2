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
        Schema::table('company_visas', function (Blueprint $table) {
            $table->string('vp_number')->after('available_slots');
            $table->date('vp_expiry_date')->after('vp_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_visas', function (Blueprint $table) {
            $table->dropColumn(['vp_number', 'vp_expiry_date']);
        });
    }
};
