<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Get collections report with date range filtering (Contract Payments)
     */
    public function collectionsReport(Request $request)
    {
        $query = \App\Models\ContractPayment::with(['contract.staff.company', 'creator']);

        if ($request->from_date) {
            $query->whereDate('payment_date', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('payment_date', '<=', $request->to_date);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->whereHas('contract.staff', function($sq) use ($request) {
                    $sq->where('name', 'like', "%{$request->search}%");
                })->orWhereHas('contract.staff.company', function($cq) use ($request) {
                    $cq->where('name', 'like', "%{$request->search}%");
                });
            });
        }

        $collections = $query->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 15);

        // Summary for the filtered range
        $totalCollected = (clone $query)->sum('amount');

        return response()->json([
            'data' => $collections->items(),
            'current_page' => $collections->currentPage(),
            'last_page' => $collections->lastPage(),
            'total' => $collections->total(),
            'per_page' => $collections->perPage(),
            'from' => $collections->firstItem(),
            'to' => $collections->lastItem(),
            'summary' => [
                'total_collected' => (float)$totalCollected,
            ]
        ]);
    }

    /**
     * Get Income vs Expenditure report with date range filtering
     */
    public function incomeExpenditureReport(Request $request)
    {
        $fromDate = $request->from_date;
        $toDate = $request->to_date;
        $perPage = $request->per_page ?? 15;

        // Income (Contract Payments)
        $incomeQuery = DB::table('contract_payments')
            ->join('contracts', 'contract_payments.contract_id', '=', 'contracts.id')
            ->join('staff', 'contracts.staff_id', '=', 'staff.id')
            ->leftJoin('companies', 'staff.company_id', '=', 'companies.id')
            ->select(
                'contract_payments.id',
                'contract_payments.amount',
                'contract_payments.payment_date as date',
                'contract_payments.payment_method',
                'contract_payments.notes',
                DB::raw("'income' as type"),
                'staff.name as staff_name',
                'companies.name as company_name',
                'contract_payments.created_at'
            );

        if ($fromDate) $incomeQuery->whereDate('contract_payments.payment_date', '>=', $fromDate);
        if ($toDate) $incomeQuery->whereDate('contract_payments.payment_date', '<=', $toDate);

        // Expenditure (Expenses)
        $expenseQuery = DB::table('expenses')
            ->leftJoin('expense_categories', 'expenses.category_id', '=', 'expense_categories.id')
            ->leftJoin('staff', 'expenses.staff_id', '=', 'staff.id')
            ->leftJoin('companies', 'staff.company_id', '=', 'companies.id')
            ->select(
                'expenses.id',
                'expenses.amount',
                'expenses.expense_date as date',
                'expenses.payment_method',
                DB::raw("CONCAT(COALESCE(expense_categories.name, 'Expense'), ': ', COALESCE(expenses.description, '')) as notes"),
                DB::raw("'expenditure' as type"),
                'staff.name as staff_name',
                'companies.name as company_name',
                'expenses.created_at'
            );

        if ($fromDate) $expenseQuery->whereDate('expenses.expense_date', '>=', $fromDate);
        if ($toDate) $expenseQuery->whereDate('expenses.expense_date', '<=', $toDate);

        $combined = $incomeQuery->unionAll($expenseQuery)
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        $paginated = $combined->paginate($perPage);

        // Totals for the filtered range
        $totalIncome = DB::table('contract_payments')
            ->when($fromDate, fn($q) => $q->whereDate('payment_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('payment_date', '<=', $toDate))
            ->sum('amount');

        $totalExpenditure = DB::table('expenses')
            ->when($fromDate, fn($q) => $q->whereDate('expense_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('expense_date', '<=', $toDate))
            ->sum('amount');

        return response()->json([
            'data' => $paginated,
            'summary' => [
                'total_income' => (float)$totalIncome,
                'total_expenditure' => (float)$totalExpenditure,
                'net_balance' => (float)($totalIncome - $totalExpenditure),
            ]
        ]);
    }

}
