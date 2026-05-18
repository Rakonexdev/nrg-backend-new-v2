<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('contracts', 'notes')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('contract_date');
            });
        }
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
