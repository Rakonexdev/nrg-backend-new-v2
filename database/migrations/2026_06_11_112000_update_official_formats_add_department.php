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
        Schema::table('official_formats', function (Blueprint $table) {
            $table->dropColumn('expiry_date');
            $table->string('document_department')->after('document_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('official_formats', function (Blueprint $table) {
            $table->dropColumn('document_department');
            $table->date('expiry_date')->nullable()->after('document_name');
        });
    }
};
