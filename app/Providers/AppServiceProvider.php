<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\ContractCreated;
use App\Listeners\GenerateInitialInvoice;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Handle Authorization header fallback for web servers stripping Authorization header
        // Priority: HTTP_AUTHORIZATION > REDIRECT_HTTP_AUTHORIZATION > HTTP_X_AUTHORIZATION > apache_request_headers()
        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['HTTP_X_AUTHORIZATION'])) {
                $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['HTTP_X_AUTHORIZATION'];
            } elseif (function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                if (isset($headers['Authorization'])) {
                    $_SERVER['HTTP_AUTHORIZATION'] = $headers['Authorization'];
                } elseif (isset($headers['authorization'])) {
                    $_SERVER['HTTP_AUTHORIZATION'] = $headers['authorization'];
                }
            }
        }

        Event::listen(
            ContractCreated::class,
            GenerateInitialInvoice::class
        );
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);

        // Self-healing: Automatically add the notes column to contracts table if missing
        try {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('contracts', 'notes')) {
                \Illuminate\Support\Facades\Schema::table('contracts', function (\Illuminate\Database\Schema\Blueprint $table) {
                    $table->text('notes')->nullable()->after('contract_date');
                });
            }
        } catch (\Throwable $e) {
            // Ignore database connection/migration issues during seeding
        }

        // Self-healing: Ensure required CRUD permissions exist in permissions table
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('permissions')) {
                $requiredPermissions = [
                    'view_companies', 'company_create', 'company_edit', 'company_delete',
                    'view_staff', 'staff_create', 'staff_edit', 'staff_delete',
                    'view_contracts', 'contract_create', 'contract_edit', 'contract_delete',
                    'view_expenses', 'expense_create', 'expense_edit', 'expense_delete',
                    'view_collectors', 'collector_create', 'collector_edit', 'collector_delete',
                    'view_role_access', 'view_reports', 'view_documentation',
                    'documentation_create', 'documentation_edit', 'documentation_delete', 'documentation_download',
                    'view_employee_list_moi', 'employee_list_moi_create', 'employee_list_moi_edit', 'employee_list_moi_delete', 'employee_list_moi_download',
                    'view_salary_sheet', 'salary_sheet_create', 'salary_sheet_edit', 'salary_sheet_delete', 'salary_sheet_download',
                ];
                foreach ($requiredPermissions as $pName) {
                    \Spatie\Permission\Models\Permission::firstOrCreate(
                        ['name' => $pName, 'guard_name' => 'web']
                    );
                }
            }
        } catch (\Throwable $e) {
            // Ignore if database is not ready
        }

        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user()->can() and @can()
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->isSuperAdmin() ? true : null;
        });
    }
}
