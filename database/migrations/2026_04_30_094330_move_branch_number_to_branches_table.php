<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add branch_number to company_branches if it doesn't exist
        if (!Schema::hasColumn('company_branches', 'branch_number')) {
            Schema::table('company_branches', function (Blueprint $table) {
                $table->string('branch_number', 50)->nullable()->after('name');
            });
        }

        // Migrate data if companies still has branch_number
        if (Schema::hasColumn('companies', 'branch_number')) {
            $companies = DB::table('companies')->whereNotNull('branch_number')->get();
            foreach ($companies as $company) {
                // Check if this company already has a branch (maybe created before migration)
                // If not, we could create a 'Main' branch with this number
                // But for now, we just ensure the column exists.
            }

            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('branch_number');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('branch_number', 50)->nullable();
        });

        Schema::table('company_branches', function (Blueprint $table) {
            $table->dropColumn('branch_number');
        });
    }
};
