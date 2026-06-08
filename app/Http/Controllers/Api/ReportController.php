<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get collections report with date range filtering (Contract Payments)
     */
    public function collectionsReport(Request $request)
    {
        $query = \App\Models\ContractPayment::with(['contract.staff.company', 'creator.roles']);

        if ($request->from_date) {
            $query->whereDate('payment_date', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('payment_date', '<=', $request->to_date);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->whereHas('contract.staff', function($sq) use ($request) {
                    $sq->where('name', 'like', "%{$request->search}%")
                      ->orWhere('qid_number', 'like', "%{$request->search}%");
                })->orWhereHas('contract.staff.company', function($cq) use ($request) {
                    $cq->where('name', 'like', "%{$request->search}%");
                });
            });
        }

        $collections = $query->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 15);

        // Calculate adjustment_pending for each collection's contract & load creator roles
        $collections->getCollection()->transform(function ($payment) {
            if ($payment->contract) {
                $payment->contract->adjustment_pending = (float)$payment->contract->adjustments()->sum('pending_amount');
            }
            if ($payment->creator) {
                $payment->creator->role = $payment->creator->role;
            }
            // Ensure created_at is always exposed as ISO string for frontend time display
            $payment->recorded_at = $payment->created_at ? $payment->created_at->toIso8601String() : null;
            return $payment;
        });

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
        $search = $request->search;
        $perPage = $request->per_page ?? 10;

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
                'staff.qid_number as staff_qid',
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
                'staff.qid_number as staff_qid',
                'companies.name as company_name',
                'expenses.created_at'
            );

        if ($fromDate) $expenseQuery->whereDate('expenses.expense_date', '>=', $fromDate);
        if ($toDate) $expenseQuery->whereDate('expenses.expense_date', '<=', $toDate);

        // Wrap union in subquery, then apply search/filters on the outer query
        $unionSub = $incomeQuery->unionAll($expenseQuery);

        $outer = DB::query()->fromSub($unionSub, 'records')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        // Global search across all fields
        if ($search) {
            $outer->where(function($q) use ($search) {
                $q->where('staff_name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('payment_method', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhere('amount', 'like', "%{$search}%")
                  ->orWhere('staff_qid', 'like', "%{$search}%");
            });
        }

        // Individual column filters
        if ($request->type) {
            $outer->where('type', $request->type);
        }
        if ($request->method) {
            $outer->where('payment_method', $request->method);
        }

        $paginated = $outer->paginate($perPage);

        // Convert the database UTC created_at timestamp to ISO 8601 string for frontend local timezone conversion
        $paginated->getCollection()->transform(function ($item) {
            if (!empty($item->created_at)) {
                $item->recorded_at = \Carbon\Carbon::parse($item->created_at)->toIso8601String();
            } else {
                $item->recorded_at = null;
            }
            return $item;
        });


        // Totals for the filtered range
        $totalIncome = DB::table('contract_payments')
            ->when($fromDate, fn($q) => $q->whereDate('payment_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('payment_date', '<=', $toDate))
            ->sum('amount');

        $totalExpenditure = DB::table('expenses')
            ->when($fromDate, fn($q) => $q->whereDate('expense_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('expense_date', '<=', $toDate))
            ->sum('amount');

        $totalRecoverable = DB::table('expenses')
            ->where('is_recoverable', true)
            ->when($fromDate, fn($q) => $q->whereDate('expense_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('expense_date', '<=', $toDate))
            ->sum('amount');

        return response()->json([
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
            'summary' => [
                'total_income' => (float)$totalIncome,
                'total_expenditure' => (float)$totalExpenditure,
                'total_recoverable' => (float)$totalRecoverable,
                'net_balance' => (float)($totalIncome - $totalExpenditure),
            ]
        ]);
    }

    /**
     * Documentation Status Report with server-side pagination and search
     */
    public function documentationStatusReport(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $status = $request->status;

        $query = Expense::with(['staff.branch', 'subcategory', 'staff.company'])
            ->whereNotNull('validation_date')
            ->whereNotNull('staff_id')
            ->whereHas('subcategory', function($q) {
                $q->where('name', 'like', '%QID%')
                  ->orWhere('name', 'like', '%PASSPORT%')
                  ->orWhere('name', 'like', '%PP%');
            })
            ->where(function($q) {
                $q->whereNull('renewal_status')
                  ->orWhere('renewal_status', '!=', 'completed');
            });

        // Search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->whereHas('staff', function($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('qid_number', 'like', "%{$search}%")
                       ->orWhere('phone', 'like', "%{$search}%");
                })
                ->orWhereHas('staff.company', function($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('subcategory', function($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                })
                ->orWhere('renewal_status', 'like', "%{$search}%")
                ->orWhere('renewal_notes', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($status) {
            $query->where('renewal_status', $status);
        }

        $paginated = $query->latest()->paginate($perPage);

        $items = $paginated->getCollection()->filter(function ($expense) {
            if (!$expense->staff || !$expense->subcategory) return false;
            $subName = strtoupper($expense->subcategory->name);

            if (str_contains($subName, 'QID')) {
                return !$expense->staff->qid_expiry || $expense->staff->qid_expiry < $expense->validation_date;
            }
            if (str_contains($subName, 'PASSPORT') || str_contains($subName, 'PP')) {
                return !$expense->staff->passport_expiry || $expense->staff->passport_expiry < $expense->validation_date;
            }
            return false;
        })->map(function ($expense) {
            $subName = strtoupper($expense->subcategory?->name ?? '');
            $type = str_contains($subName, 'QID') ? 'QID' : 'Passport';

            return [
                'id' => $expense->id,
                'staff_id' => $expense->staff_id,
                'staff_name' => $expense->staff->name,
                'type' => $type,
                'expense_date' => $expense->expense_date ? $expense->expense_date->format('d M Y') : 'N/A',
                'new_expiry' => $expense->validation_date ? $expense->validation_date->format('d M Y') : 'N/A',
                'current_expiry' => $type === 'QID'
                    ? ($expense->staff?->qid_expiry ? $expense->staff->qid_expiry->format('d M Y') : 'Expired/Missing')
                    : ($expense->staff?->passport_expiry ? $expense->staff->passport_expiry->format('d M Y') : 'Expired/Missing'),
                'renewal_status' => $expense->renewal_status ?? 'processing',
                'renewal_notes' => $expense->renewal_notes,
                'payment_method' => $expense->payment_method,
                'staff' => [
                    'name' => $expense->staff->name,
                    'qid_number' => $expense->staff->qid_number,
                    'phone' => $expense->staff->phone,
                    'branch_name' => $expense->staff->branch?->name,
                    'branch_number' => $expense->staff->branch?->branch_number,
                    'company_name' => $expense->staff->company?->name,
                ],
            ];
        })->values();

        // Summary counts (from full unfiltered set for totals)
        $allQuery = Expense::whereNotNull('validation_date')
            ->whereNotNull('staff_id')
            ->where(function($q) {
                $q->whereNull('renewal_status')
                  ->orWhere('renewal_status', '!=', 'completed');
            });

        $totalPending = (clone $allQuery)->count();
        $processing = (clone $allQuery)->where('renewal_status', 'processing')->count();
        $delayed = (clone $allQuery)->where('renewal_status', 'delayed')->count();

        return response()->json([
            'data' => $items,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
            'from' => $paginated->firstItem(),
            'to' => $paginated->lastItem(),
            'summary' => [
                'total_pending' => $totalPending,
                'processing' => $processing,
                'delayed' => $delayed,
            ]
        ]);
    }

    /**
     * Export Collections Report to Excel (CSV)
     */
    public function exportCollections(Request $request)
    {
        $query = \App\Models\ContractPayment::with(['contract.staff.company', 'creator.roles']);

        if ($request->from_date) {
            $query->whereDate('payment_date', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('payment_date', '<=', $request->to_date);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->whereHas('contract.staff', function($sq) use ($request) {
                    $sq->where('name', 'like', "%{$request->search}%")
                      ->orWhere('qid_number', 'like', "%{$request->search}%");
                })->orWhereHas('contract.staff.company', function($cq) use ($request) {
                    $cq->where('name', 'like', "%{$request->search}%");
                });
            });
        }

        $collections = $query->orderBy('payment_date', 'desc')->orderBy('id', 'desc')->get();

        $filename = "Collections_Report_" . date('Y-m-d') . ".csv";
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['Date', 'Staff Name', 'Company', 'Payment Method', 'Amount', 'Recorded By', 'Status'];

        $callback = function () use ($collections, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($collections as $row) {
                $role = $row->creator ? ($row->creator->role === 'super_admin' ? 'Super Admin' : ($row->creator->role === 'admin' ? 'Admin' : $row->creator->role)) : null;
                $recordedByStr = $row->creator ? $row->creator->name . ($role ? " ($role)" : "") : 'System';
                fputcsv($file, [
                    $row->payment_date ? $row->payment_date->format('d M Y') : 'N/A',
                    $row->contract->staff->name ?? 'N/A',
                    $row->contract->staff->company->name ?? 'Individual',
                    strtoupper(str_replace('_', ' ', $row->payment_method ?? '')),
                    $row->amount,
                    $recordedByStr,
                    $row->status === 'not_collected' ? 'Not Collected' : 'Amount Collected'
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Income vs Expenditure Report to Excel (CSV)
     */
    public function exportIncomeExpenditure(Request $request)
    {
        $fromDate = $request->from_date;
        $toDate = $request->to_date;
        $search = $request->search;

        // Income (Contract Payments)
        $incomeQuery = DB::table('contract_payments')
            ->join('contracts', 'contract_payments.contract_id', '=', 'contracts.id')
            ->join('staff', 'contracts.staff_id', '=', 'staff.id')
            ->leftJoin('companies', 'staff.company_id', '=', 'companies.id')
            ->select(
                'contract_payments.amount',
                'contract_payments.payment_date as date',
                'contract_payments.payment_method',
                'contract_payments.notes',
                DB::raw("'income' as type"),
                'staff.name as staff_name',
                'staff.qid_number as staff_qid',
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
                'expenses.amount',
                'expenses.expense_date as date',
                'expenses.payment_method',
                DB::raw("CONCAT(COALESCE(expense_categories.name, 'Expense'), ': ', COALESCE(expenses.description, '')) as notes"),
                DB::raw("'expenditure' as type"),
                'staff.name as staff_name',
                'staff.qid_number as staff_qid',
                'companies.name as company_name',
                'expenses.created_at'
            );

        if ($fromDate) $expenseQuery->whereDate('expenses.expense_date', '>=', $fromDate);
        if ($toDate) $expenseQuery->whereDate('expenses.expense_date', '<=', $toDate);

        $unionSub = $incomeQuery->unionAll($expenseQuery);
        $outer = DB::query()->fromSub($unionSub, 'records')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        if ($search) {
            $outer->where(function($q) use ($search) {
                $q->where('staff_name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('payment_method', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhere('amount', 'like', "%{$search}%")
                  ->orWhere('staff_qid', 'like', "%{$search}%");
            });
        }

        if ($request->type) $outer->where('type', $request->type);
        if ($request->method) $outer->where('payment_method', $request->method);

        $records = $outer->get();

        $filename = "Income_Expenditure_Report_" . date('Y-m-d') . ".csv";
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['Date', 'Type', 'Staff / Company', 'QID', 'Amount', 'Method', 'Description'];

        $callback = function () use ($records, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($records as $row) {
                fputcsv($file, [
                    date('d M Y', strtotime($row->date)),
                    ucfirst($row->type),
                    $row->staff_name . ($row->company_name ? " ({$row->company_name})" : ""),
                    $row->staff_qid ?? 'N/A',
                    $row->amount,
                    strtoupper(str_replace('_', ' ', $row->payment_method ?? '')),
                    $row->notes
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
