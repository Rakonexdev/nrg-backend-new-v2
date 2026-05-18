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
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'dashboard_total_staff',
            'dashboard_qid_expiry',
            'dashboard_passport_expiry',
            'dashboard_renewing_this_month',
            'dashboard_doc_status',
            'dashboard_total_collected',
            'dashboard_pending_collection',
            'dashboard_contract_profit',
            'dashboard_recent_collections',
            'dashboard_upcoming_expirations',
        ];

        foreach ($permissions as $permission) {
            foreach (['web', 'api'] as $guard) {
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]);
            }
        }

        // Add to Super Admin
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo(Permission::whereIn('name', $permissions)->where('guard_name', 'web')->get());
        }

        // Add to Admin
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo(Permission::whereIn('name', $permissions)->where('guard_name', 'web')->get());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'dashboard_total_staff',
            'dashboard_qid_expiry',
            'dashboard_passport_expiry',
            'dashboard_renewing_this_month',
            'dashboard_doc_status',
            'dashboard_total_collected',
            'dashboard_pending_collection',
            'dashboard_contract_profit',
            'dashboard_recent_collections',
            'dashboard_upcoming_expirations',
        ];

        Permission::whereIn('name', $permissions)->delete();
    }
};
