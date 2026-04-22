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
        Schema::table('staff', function (Blueprint $table) {
            $this->addIndexIfNotExists('staff', 'name', 'staff_name_index');
            $this->addIndexIfNotExists('staff', 'qid_number', 'staff_qid_number_index');
            $this->addIndexIfNotExists('staff', 'passport_number', 'staff_passport_number_index');
            $this->addIndexIfNotExists('staff', 'status', 'staff_status_index');
            $this->addIndexIfNotExists('staff', 'company_id', 'staff_company_id_index');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $this->addIndexIfNotExists('contracts', 'staff_id', 'contracts_staff_id_index');
            $this->addIndexIfNotExists('contracts', 'payment_status', 'contracts_payment_status_index');
            $this->addIndexIfNotExists('contracts', 'start_date', 'contracts_start_date_index');
            $this->addIndexIfNotExists('contracts', 'end_date', 'contracts_end_date_index');
            $this->addIndexIfNotExists('contracts', 'created_at', 'contracts_created_at_index');
        });

        Schema::table('contract_payments', function (Blueprint $table) {
            $this->addIndexIfNotExists('contract_payments', 'contract_id', 'contract_payments_contract_id_index');
            $this->addIndexIfNotExists('contract_payments', 'payment_date', 'contract_payments_payment_date_index');
            $this->addIndexIfNotExists('contract_payments', 'created_at', 'contract_payments_created_at_index');
        });

        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                $this->addIndexIfNotExists('companies', 'name', 'companies_name_index');
                $this->addIndexIfNotExists('companies', 'is_active', 'companies_is_active_index');
            });
        }

        Schema::table('expenses', function (Blueprint $table) {
            $this->addIndexIfNotExists('expenses', 'date', 'expenses_date_index');
            $this->addIndexIfNotExists('expenses', 'contract_id', 'expenses_contract_id_index');
            $this->addIndexIfNotExists('expenses', 'staff_id', 'expenses_staff_id_index');
            $this->addIndexIfNotExists('expenses', 'category', 'expenses_category_index');
        });
    }

    private function addIndexIfNotExists($table, $column, $indexName)
    {
        $conn = Schema::getConnection();
        $db = $conn->getDatabaseName();
        
        $exists = DB::select("
            SELECT COUNT(*) as count 
            FROM information_schema.statistics 
            WHERE table_schema = ? 
            AND table_name = ? 
            AND index_name = ?
        ", [$db, $table, $indexName])[0]->count;

        if (!$exists && Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $tableObj) use ($column, $indexName) {
                $tableObj->index($column, $indexName);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not implemented for safety in this robust migration
    }
};
