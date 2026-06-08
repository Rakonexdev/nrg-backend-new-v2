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
        Schema::table('sponsorship_changes', function (Blueprint $table) {
            $table->string('referral_contact_person')->nullable();
            $table->string('reference_contact_number')->nullable();
            $table->string('reference_alt_number')->nullable();
            $table->string('identity_alt_phone')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsorship_changes', function (Blueprint $table) {
            $table->dropColumn([
                'referral_contact_person',
                'reference_contact_number',
                'reference_alt_number',
                'identity_alt_phone'
            ]);
        });
    }
};
