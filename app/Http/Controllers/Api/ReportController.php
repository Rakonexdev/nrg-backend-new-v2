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
     * Get collections report with date range filtering
     */
    public function collectionsReport(Request $request)
    {
        $query = Collection::with(['invoice', 'company', 'collector']);

        if ($request->from_date) {
            $query->whereDate('collection_date', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('collection_date', '<=', $request->to_date);
        }

        $collections = $query->orderBy('collection_date', 'desc')->paginate($request->per_page ?? 15);

        // Summary for the filtered range
        $totalCollected = (clone $query)->sum('collected_amount');

        return response()->json([
            'collections' => $collections,
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

        // Income (Collections) Summary
        $incomeQuery = Collection::query();
        if ($fromDate) $incomeQuery->whereDate('collection_date', '>=', $fromDate);
        if ($toDate) $incomeQuery->whereDate('collection_date', '<=', $toDate);
        
        $totalIncome = $incomeQuery->sum('collected_amount');

        // Expenditure (Expenses) Summary
        $expenseQuery = Expense::query();
        if ($fromDate) $expenseQuery->whereDate('expense_date', '>=', $fromDate);
        if ($toDate) $expenseQuery->whereDate('expense_date', '<=', $toDate);
        
        $totalExpenditure = $expenseQuery->sum('amount');

        // Group by Date for detailed comparison
        $incomeByDate = Collection::select(DB::raw('DATE(collection_date) as date'), DB::raw('SUM(collected_amount) as amount'))
            ->when($fromDate, fn($q) => $q->whereDate('collection_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('collection_date', '<=', $toDate))
            ->groupBy('date')
            ->get();

        $expenseByDate = Expense::select(DB::raw('DATE(expense_date) as date'), DB::raw('SUM(amount) as amount'))
            ->when($fromDate, fn($q) => $q->whereDate('expense_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('expense_date', '<=', $toDate))
            ->groupBy('date')
            ->get();

        // Merge dates and prepare daily data
        $allDates = $incomeByDate->pluck('date')
            ->merge($expenseByDate->pluck('date'))
            ->unique()
            ->sortDesc(); // Show latest first
        
        $dailyData = [];
        foreach ($allDates as $date) {
            $inc = $incomeByDate->firstWhere('date', $date)?->amount ?? 0;
            $exp = $expenseByDate->firstWhere('date', $date)?->amount ?? 0;
            $dailyData[] = [
                'date' => $date,
                'income' => (float)$inc,
                'expenditure' => (float)$exp,
                'balance' => (float)($inc - $exp)
            ];
        }

        return response()->json([
            'summary' => [
                'total_income' => (float)$totalIncome,
                'total_expenditure' => (float)$totalExpenditure,
                'net_balance' => (float)($totalIncome - $totalExpenditure),
            ],
            'daily_data' => $dailyData
        ]);
    }
}
