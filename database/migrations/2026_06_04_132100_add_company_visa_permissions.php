<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            'view_company_visas',
            'company_visa_create',
            'company_visa_edit',
            'company_visa_delete',
            'company_visa_status',
        ];
        
        foreach ($permissions as $permission) {
            foreach (['web', 'api'] as $guard) {
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]);
            }
        }

        $superAdminRole = Role::where('name', 'super_admin')->where('guard_name', 'web')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo($permissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'view_company_visas',
            'company_visa_create',
            'company_visa_edit',
            'company_visa_delete',
            'company_visa_status',
        ];
        
        Permission::whereIn('name', $permissions)->delete();
    }
};
