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
        // If the column exists, it means this migration or equivalent SQL was already applied.
        if (Schema::hasColumn('bank_details', 'bank_details_for')) {
            return;
        }

        Schema::table('bank_details', function (Blueprint $table) {
            $table->string('bank_details_for')->default('Company')->after('id');
            // We will safely skip dropping the foreign key to avoid crash on existing databases
            // $table->dropForeign(['company_id']);
        });

        Schema::table('bank_details', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->string('person_name')->nullable()->change();
        });

        // Schema::table('bank_details', function (Blueprint $table) {
        //     $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_details', function (Blueprint $table) {
            $table->dropColumn('bank_details_for');
            
            $table->dropForeign(['company_id']);
        });

        Schema::table('bank_details', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable(false)->change();
            $table->string('person_name')->nullable(false)->change();
        });

        Schema::table('bank_details', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }
};
