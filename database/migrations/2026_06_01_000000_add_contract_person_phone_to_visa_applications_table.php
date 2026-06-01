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
        Schema::table('visa_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('visa_applications', 'contract_person_phone')) {
                $table->string('contract_person_phone')->nullable()->after('contract_person');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visa_applications', function (Blueprint $table) {
            if (Schema::hasColumn('visa_applications', 'contract_person_phone')) {
                $table->dropColumn('contract_person_phone');
            }
        });
    }
};
