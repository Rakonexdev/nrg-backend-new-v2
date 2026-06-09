<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bank_details', 'card_number')) {
            return;
        }
        Schema::table('bank_details', function (Blueprint $table) {
            $table->string('card_number')->nullable()->after('card_type');
        });
    }

    public function down(): void
    {
        Schema::table('bank_details', function (Blueprint $table) {
            $table->dropColumn('card_number');
        });
    }
};
