<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ContractPaymentController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\CollectorController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\GeneralDocumentController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VisaApplicationController;

// Public Auth routes

Route::post('/auth/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth Base
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    // Core Entities
    Route::get('/dashboard', [\App\Http\Controllers\Api\DashboardController::class, 'index']);
    Route::apiResource('companies', CompanyController::class);
    Route::get('/companies/{id}/pending-collections', [CompanyController::class, 'getPendingCollections']);
    Route::apiResource('staff', StaffController::class);
    Route::apiResource('collectors', CollectorController::class);
    
    // Vehicles
    Route::apiResource('vehicles', VehicleController::class);
    
    // Visa Applications
    Route::apiResource('visa-applications', VisaApplicationController::class);

    Route::get('/contracts/summary', [ContractController::class, 'summary']);
    Route::apiResource('contracts', ContractController::class);
    Route::post('/contracts/{id}/adjustments', [ContractController::class, 'addAdjustment']);
    Route::put('/contracts/{id}/adjustments/{adjustment_id}', [ContractController::class, 'updateAdjustment']);
    Route::put('/contracts/{id}/next-due-date', [ContractController::class, 'updateNextDueDate']);
    Route::get('/contracts/{contract}/payments', [ContractPaymentController::class, 'index']);
    Route::post('/contracts/{contract}/payments', [ContractPaymentController::class, 'store']);
    Route::put('/contracts/{contract}/payments/{payment}', [ContractPaymentController::class, 'update']);
    Route::delete('/contracts/{contract}/payments/{payment}', [ContractPaymentController::class, 'destroy']);
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'update']);

    // Staff Docs
    Route::get('/staff/{id}/documents', [DocumentController::class, 'index']);
    Route::post('/staff/{id}/documents', [DocumentController::class, 'store']);

    // General Documents
    Route::middleware('can:view_documentation')->group(function () {
        Route::get('/general-documents', [GeneralDocumentController::class, 'index']);
        Route::post('/general-documents', [GeneralDocumentController::class, 'store'])->middleware('can:documentation_create');
        Route::post('/general-documents/{id}', [GeneralDocumentController::class, 'update'])->middleware('can:documentation_edit');
        Route::delete('/general-documents/{id}', [GeneralDocumentController::class, 'destroy'])->middleware('can:documentation_delete');
        Route::get('/general-documents/{id}/download', [GeneralDocumentController::class, 'download'])->middleware('can:documentation_download');
    });

    // Financials
    Route::get('/collections/pending', [CollectionController::class, 'pendingCollections']);
    Route::get('/collections/unsettled', [CollectionController::class, 'unsettled']);
    Route::get('/collections', [CollectionController::class, 'index']);
    Route::post('/collections', [CollectionController::class, 'store']); // Create Collection
    
    // Auto-migrate if needed before updating status
    Route::put('/collections/{id}/status', function (\Illuminate\Http\Request $request, $id) {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('contract_payments', 'status')) {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        }
        return app(\App\Http\Controllers\Api\CollectionController::class)->updateStatus($request, $id);
    }); // Update Status

    Route::get('/settlements/summary', [SettlementController::class, 'todaySummary']);
    Route::get('/settlements', [SettlementController::class, 'index']);
    Route::post('/settlements', [SettlementController::class, 'store']);
    Route::post('/settlements/{id}/confirm', [SettlementController::class, 'confirm']);

    Route::get('/expenses/export', [ExpenseController::class, 'export']);
    Route::post('/expenses/{id}/finalize', [ExpenseController::class, 'finalizeRenewal']);
    Route::apiResource('expenses', ExpenseController::class);
    Route::apiResource('expense-categories', ExpenseCategoryController::class);

    // Dashboard
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/income-expense', [DashboardController::class, 'incomeExpense']);

    // Reports
    Route::get('/reports/collections', [ReportController::class, 'collectionsReport']);
    Route::get('/reports/collections/export', [ReportController::class, 'exportCollections']);
    Route::get('/reports/income-expenditure', [ReportController::class, 'incomeExpenditureReport']);
    Route::get('/reports/income-expenditure/export', [ReportController::class, 'exportIncomeExpenditure']);
    Route::get('/reports/documentation-status', [ReportController::class, 'documentationStatusReport']);

    // Company Branches
    Route::get('companies/{company}/branches', [App\Http\Controllers\Api\CompanyBranchController::class, 'index']);
    Route::post('companies/{company}/branches', [App\Http\Controllers\Api\CompanyBranchController::class, 'store']);
    Route::put('branches/{branch}', [App\Http\Controllers\Api\CompanyBranchController::class, 'update']);
    Route::delete('branches/{branch}', [App\Http\Controllers\Api\CompanyBranchController::class, 'destroy']);

    // Role & Permission Management
    Route::prefix('roles')->middleware('permission:view_role_access')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/permissions', [RoleController::class, 'permissions']);
        Route::post('/', [RoleController::class, 'store']);
        Route::put('/{id}', [RoleController::class, 'update']);
        Route::delete('/{id}', [RoleController::class, 'destroy']);
    });

    // Admin User Management
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/admin-users', [RoleController::class, 'adminUsers']);
        Route::post('/admin-users', [RoleController::class, 'createAdminUser']);
        Route::put('/admin-users/{id}', [RoleController::class, 'updateAdminUser']);
        Route::delete('/admin-users/{id}', [RoleController::class, 'deleteAdminUser']);
    });
});
