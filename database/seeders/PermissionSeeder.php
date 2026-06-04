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
            'view_visa_applications',
            'view_company_visas',

            // Dashboard card permissions
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

            // Action permissions — Staff
            'staff_create',
            'staff_edit',
            'staff_status',
            'staff_delete',

            // Action permissions — Companies
            'company_create',
            'company_edit',
            'company_delete',
            'company_status',

            // Action permissions — Contracts
            'contract_create',
            'contract_edit',
            'contract_delete',
            'contract_card_total_collected',
            'contract_card_pending_collection',
            'contract_card_contract_profit',
            'contract_card_general_overheads',

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

            // Action permissions — Vehicles
            'view_vehicles',
            'vehicle_create',
            'vehicle_edit',
            'vehicle_delete',
            'vehicle_status',

            // Action permissions — Visa Applications
            'visa_application_create',
            'visa_application_edit',
            'visa_application_delete',
            'visa_application_status',
            'visa_application_payment',

            // Action permissions — Company Visas
            'company_visa_create',
            'company_visa_edit',
            'company_visa_delete',
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
            'view_staff',
            'view_companies',
            'view_contracts',
            'contract_card_total_collected',
            'contract_card_pending_collection',
            'contract_card_contract_profit',
            'contract_card_general_overheads',
            'view_expenses',
            'view_settlements',
            'view_reports',
            'view_collectors',
            'staff_delete',
            'company_status',
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
