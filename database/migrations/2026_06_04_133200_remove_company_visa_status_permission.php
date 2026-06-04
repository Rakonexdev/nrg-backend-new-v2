<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Permission::where('name', 'company_visa_status')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['web', 'api'] as $guard) {
            Permission::firstOrCreate(['name' => 'company_visa_status', 'guard_name' => $guard]);
        }
    }
};
