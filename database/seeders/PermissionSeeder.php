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

            // Action permissions — Reports
            'report_doc_status_edit',

            // Action permissions — General Documentation
            'view_documentation',
            'documentation_create',
            'documentation_edit',
            'documentation_delete',
            'documentation_download',
        ];

        foreach ($permissions as $permission) {
            foreach (['web', 'api'] as $guard) {
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => $guard]);
            }
        }

        // Super Admin gets all permissions
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        // Sync only web guard permissions to web role to avoid guard mismatches
        $superAdminRole->syncPermissions(Permission::where('guard_name', 'web')->get());

        // Admin role — create with default view-only permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        
        // Sync permissions for admin (overwriting or merging based on preference, here we sync the standard set)
        $adminRole->syncPermissions([
            'view_dashboard',
            'view_staff',
            'view_companies',
            'view_contracts',
            'view_expenses',
            'view_settlements',
            'view_reports',
            'view_collectors',
            'report_doc_status_edit',
            'view_documentation',
            'documentation_create',
            'documentation_edit',
            'documentation_delete',
            'documentation_download',
        ]);

        // Collector and viewer roles
        Role::firstOrCreate(['name' => 'collector', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);

        // Ensure at least one Super Admin user exists
        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin@nrg.com'],
            [
                'name' => 'Super Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'is_active' => true,
            ]
        );
        
        if (!$admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }
    }
}
