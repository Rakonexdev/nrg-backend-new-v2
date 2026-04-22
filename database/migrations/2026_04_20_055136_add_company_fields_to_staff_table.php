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
        if (!Schema::hasColumn('staff', 'company_name')) {
            Schema::table('staff', function (Blueprint $table) {
                $table->string('company_name')->nullable()->after('status');
                $table->string('company_contact_person')->nullable()->after('company_name');
                $table->string('company_phone')->nullable()->after('company_contact_person');
            });
        }

        // Migrate existing companies if necessary
        if (Schema::hasTable('companies')) {
            $companies = DB::table('companies')->get()->keyBy('id');
            
            if (Schema::hasColumn('staff', 'company_id')) {
                foreach ($companies as $id => $company) {
                    DB::table('staff')
                        ->where('company_id', $id)
                        ->update([
                            'company_name' => $company->name ?? null,
                            'company_contact_person' => $company->contact_person_name ?? null,
                            'company_phone' => $company->contact_person_phone ?? null,
                        ]);
                }
            }

            // Tables that have company_id foreign key constraint
            $tablesWithCompany = ['staff', 'contracts', 'invoices', 'collections', 'expenses'];

            foreach ($tablesWithCompany as $tableName) {
                if (Schema::hasColumn($tableName, 'company_id')) {
                    Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                        $foreignKeys = DB::select("
                            SELECT CONSTRAINT_NAME
                            FROM information_schema.KEY_COLUMN_USAGE
                            WHERE TABLE_SCHEMA = DATABASE() 
                            AND TABLE_NAME = ?
                            AND COLUMN_NAME = 'company_id' 
                            AND REFERENCED_TABLE_NAME IS NOT NULL
                        ", [$tableName]);

                        if (count($foreignKeys) > 0) {
                            $table->dropForeign($foreignKeys[0]->CONSTRAINT_NAME);
                        }
                        $table->dropColumn('company_id');
                    });
                }
            }

            Schema::dropIfExists('companies');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->dropColumn(['company_name', 'company_contact_person', 'company_phone']);
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
        });
        
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained('companies');
        });

        Schema::table('collections', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained('companies');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained('companies');
        });
    }
};
