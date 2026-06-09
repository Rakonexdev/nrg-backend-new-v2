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
        if (!Schema::hasColumn('sponsorship_changes', 'approval_file')) {
            Schema::table('sponsorship_changes', function (Blueprint $table) {
                $table->string('approval_file')->nullable()->after('document');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sponsorship_changes', function (Blueprint $table) {
            $table->dropColumn('approval_file');
        });
    }
};
