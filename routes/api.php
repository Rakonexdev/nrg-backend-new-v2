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
    Route::apiResource('staff', StaffController::class);
    Route::get('/contracts/summary', [ContractController::class, 'summary']);
    Route::apiResource('contracts', ContractController::class);
    Route::post('/contracts/{id}/adjustments', [ContractController::class, 'addAdjustment']);
    Route::get('/contracts/{contract}/payments', [ContractPaymentController::class, 'index']);
    Route::post('/contracts/{contract}/payments', [ContractPaymentController::class, 'store']);
    Route::delete('/contracts/{contract}/payments/{payment}', [ContractPaymentController::class, 'destroy']);
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show', 'update']);

    // Staff Docs
    Route::get('/staff/{id}/documents', [DocumentController::class, 'index']);
    Route::post('/staff/{id}/documents', [DocumentController::class, 'store']);

    // Financials
    Route::get('/collections/pending', [CollectionController::class, 'pendingCollections']);
    Route::get('/collections/unsettled', [CollectionController::class, 'unsettled']);
    Route::get('/collections', [CollectionController::class, 'index']);
    Route::post('/collections', [CollectionController::class, 'store']); // Create Collection

    Route::get('/settlements/summary', [SettlementController::class, 'todaySummary']);
    Route::get('/settlements', [SettlementController::class, 'index']);
    Route::post('/settlements', [SettlementController::class, 'store']);
    Route::post('/settlements/{id}/confirm', [SettlementController::class, 'confirm']);

    Route::get('/expenses/export', [ExpenseController::class, 'export']);
    Route::apiResource('expenses', ExpenseController::class);
    Route::apiResource('expense-categories', ExpenseCategoryController::class);

    // Dashboard
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/income-expense', [DashboardController::class, 'incomeExpense']);
});
