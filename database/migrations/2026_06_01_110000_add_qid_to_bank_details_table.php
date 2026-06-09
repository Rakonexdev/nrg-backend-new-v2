<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bank_details', 'qid')) {
            return;
        }
        Schema::table('bank_details', function (Blueprint $table) {
            $table->string('qid')->nullable()->after('person_name');
        });
    }

    public function down(): void
    {
        Schema::table('bank_details', function (Blueprint $table) {
            $table->dropColumn('qid');
        });
    }
};
