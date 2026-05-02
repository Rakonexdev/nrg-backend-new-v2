<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Seed all system permissions and assign full access to super_admin.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Menu visibility permissions
            'view_dashboard',
            'view_staff',
            'view_companies',
            'view_contracts',
            'view_expenses',
            'view_settlements',
            'view_reports',
            'view_collectors',
            'view_role_access',

            // Action permissions — Staff
            'staff_create',
            'staff_edit',
            'staff_status',

            // Action permissions — Companies
            'company_create',
            'company_edit',
            'company_delete',

            // Action permissions — Contracts
            'contract_create',
            'contract_edit',
            'contract_delete',

            // Action permissions — Expenses
            'expense_create',
            'expense_edit',
            'expense_delete',

            // Action permissions — Settlements
            'settlement_create',
            'settlement_edit',
            'settlement_delete',

            // Action permissions — Collectors
            'collector_create',
            'collector_edit',
            'collector_delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Super Admin gets all permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());

        // Admin role — create with default view-only permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        // Don't override existing permissions if admin already has some
        if ($adminRole->permissions->isEmpty()) {
            $adminRole->syncPermissions([
                'view_dashboard',
                'view_staff',
                'view_companies',
                'view_contracts',
                'view_expenses',
                'view_settlements',
                'view_reports',
                'view_collectors',
            ]);
        }

        // Collector and viewer roles — no dashboard permissions needed
        Role::firstOrCreate(['name' => 'collector', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
    }
}
