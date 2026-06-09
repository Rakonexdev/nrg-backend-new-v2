<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\StaffDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ExpenseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_expenses', only: ['index', 'show', 'export', 'getStats']),
            new Middleware('permission:expense_create', only: ['store']),
            new Middleware('permission:expense_edit|report_doc_status_edit', only: ['update', 'finalizeRenewal']),
            new Middleware('permission:expense_delete', only: ['destroy']),
        ];
    }
    public function index(Request $request)
    {
        $query = $this->buildFilteredQuery($request);
        $expenses = $query->orderBy('expense_date', 'desc')->paginate(15);

        return response()->json([
            'expenses' => $expenses,
            'stats' => $this->getStats()
        ]);
    }

    private function buildFilteredQuery(Request $request)
    {
        $query = Expense::with(['category', 'subcategory', 'recorder', 'staff.branch', 'contract.staff.branch']);

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->start_date) {
            $query->whereDate('expense_date', '>=', $request->start_date);
        }

        if ($request->end_date) {
            $query->whereDate('expense_date', '<=', $request->end_date);
        }

        if ($request->date) {
            $query->whereDate('expense_date', $request->date);
        }

        if ($request->contract_id) {
            if ($request->contract_id === 'null') {
                $query->whereNull('contract_id');
            } else {
                $query->where('contract_id', $request->contract_id);
            }
        }

        if ($request->search) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('category', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('contract.staff', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('qid_number', 'like', "%{$search}%")
                            ->orWhereHas('company', function ($cq) use ($search) {
                                $cq->where('name', 'like', "%{$search}%");
                            });
                    });

                // Allow searching for "general" or "overhead" to find expenses with no contract
                if (stripos('general expense', $search) !== false || stripos('overhead', $search) !== false) {
                    $q->orWhereNull('contract_id');
                }
            });
        }

        return $query;
    }

    public function export(Request $request)
    {
        $query = $this->buildFilteredQuery($request);
        $expenses = $query->orderBy('expense_date', 'desc')->get();

        $filename = "Expenses_" . date('Y-m-d') . ".csv";
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = ['Date', 'Contract/Staff', 'Category', 'Reason', 'Payment Method', 'Amount'];

        $callback = function () use ($expenses, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $total = 0;
            foreach ($expenses as $expense) {
                $contractInfo = $expense->contract_id
                    ? ($expense->contract->staff->name . ' (' . ($expense->contract->staff->company->name ?? 'Individual') . ')')
                    : 'General Expense';

                fputcsv($file, [
                    $expense->expense_date->format('Y-m-d'),
                    $contractInfo,
                    $expense->category->name ?? 'N/A',
                    $expense->description,
                    $expense->payment_method,
                    $expense->amount
                ]);

                $total += $expense->amount;
            }

            // Add a separator row
            fputcsv($file, ['', '', '', '', '', '']);
            // Add the total row
            fputcsv($file, ['TOTAL', '', '', '', '', $total]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function getStats()
    {
        $now = now();
        $thisMonth = Expense::whereYear('expense_date', $now->year)
            ->whereMonth('expense_date', $now->month);

        $lastMonth = Expense::whereYear('expense_date', $now->copy()->subMonth()->year)
            ->whereMonth('expense_date', $now->copy()->subMonth()->month);

        $thisYear = Expense::whereYear('expense_date', $now->year);

        // Separate totals by category target_type and is_recoverable
        $employeeExpensesThisMonth = (clone $thisMonth)
            ->whereHas('category', fn($q) => $q->where('target_type', 'Employee'))
            ->where('is_recoverable', false)
            ->sum('amount');

        $personalDueExpensesThisMonth = (clone $thisMonth)
            ->where('is_recoverable', true)
            ->sum('amount');

        $companyExpensesThisMonth = (clone $thisMonth)
            ->whereHas('category', fn($q) => $q->where('target_type', 'Company'))
            ->sum('amount');

        return [
            'this_month' => $thisMonth->sum('amount'),
            'this_month_employee' => $employeeExpensesThisMonth,
            'this_month_personal_due' => $personalDueExpensesThisMonth,
            'this_month_company' => $companyExpensesThisMonth,
            'last_month' => $lastMonth->sum('amount'),
            'yearly' => $thisYear->sum('amount')
        ];
    }

    public function store(Request $request)
    {
        try {
            $category = \App\Models\ExpenseCategory::find($request->category_id);
            $isEmployeeExpense = $category && $category->target_type === 'Employee';
            
            $subCategory = \App\Models\ExpenseCategory::find($request->subcategory_id);
            $subName = $subCategory ? strtolower($subCategory->name) : '';
            $isRenewal = str_contains($subName, 'qid') || str_contains($subName, 'passport') || str_contains($subName, 'pp');

            $data = $request->validate([
                'expense_date' => 'required|date',
                'category_id' => 'required|exists:expense_categories,id',
                'subcategory_id' => 'required|exists:expense_categories,id',
                'amount' => 'required|numeric',
                'payment_method' => 'required|string',
                'description' => 'required|string',
                'contract_id' => $isEmployeeExpense ? 'required|exists:contracts,id' : 'nullable|exists:contracts,id',
                'validation_date' => $isRenewal ? 'required|date' : 'nullable|date',
                'staff_id' => 'nullable|exists:staff,id',
                'is_recoverable' => 'nullable|boolean',
                'renewal_status' => 'nullable|string',
                'renewal_notes' => 'nullable|string',
                'notes' => 'nullable|string',
                'receipt_document' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            ]);

            if ($request->hasFile('receipt_document')) {
                $data['receipt_document'] = $request->file('receipt_document')->store('expenses/receipts', 'public');
            } else {
                unset($data['receipt_document']);
            }

            if (!empty($data['contract_id'])) {
                $contract = \App\Models\Contract::findOrFail($data['contract_id']);
                $data['staff_id'] = $contract->staff_id;


            }

            $data['recorded_by'] = $request->user()?->id;

            if (!$data['recorded_by']) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $expense = Expense::create($data);
            return $expense->load(['category', 'subcategory', 'staff', 'contract.staff']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Expense creation failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create expense: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        return Expense::with(['category', 'subcategory', 'recorder', 'staff', 'contract.staff'])->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        try {
            $expense = Expense::findOrFail($id);
            
            $category = \App\Models\ExpenseCategory::find($request->category_id ?? $expense->category_id);
            $isEmployeeExpense = $category && $category->target_type === 'Employee';
            
            $subCategory = \App\Models\ExpenseCategory::find($request->subcategory_id ?? $expense->subcategory_id);
            $subName = $subCategory ? strtolower($subCategory->name) : '';
            $isRenewal = str_contains($subName, 'qid') || str_contains($subName, 'passport') || str_contains($subName, 'pp');

            $data = $request->validate([
                'expense_date' => 'sometimes|required|date',
                'category_id' => 'sometimes|required|exists:expense_categories,id',
                'subcategory_id' => 'sometimes|required|exists:expense_categories,id',
                'amount' => 'sometimes|required|numeric',
                'payment_method' => 'sometimes|required|string',
                'description' => 'sometimes|required|string',
                'contract_id' => $isEmployeeExpense ? 'sometimes|required|exists:contracts,id' : 'nullable|exists:contracts,id',
                'validation_date' => $isRenewal ? 'sometimes|required|date' : 'nullable|date',
                'staff_id' => 'nullable|exists:staff,id',
                'is_recoverable' => 'nullable|boolean',
                'renewal_status' => 'nullable|string',
                'renewal_notes' => 'nullable|string',
                'notes' => 'nullable|string',
                'receipt_document' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:10240',
            ]);

            if ($request->hasFile('receipt_document')) {
                if ($expense->receipt_document) {
                    Storage::disk('public')->delete($expense->receipt_document);
                }
                $data['receipt_document'] = $request->file('receipt_document')->store('expenses/receipts', 'public');
            } else {
                unset($data['receipt_document']);
            }

            if (array_key_exists('contract_id', $data) && !empty($data['contract_id'])) {
                $contract = \App\Models\Contract::findOrFail($data['contract_id']);
                $data['staff_id'] = $contract->staff_id;


            }

            if (array_key_exists('contract_id', $data) && empty($data['contract_id'])) {
                $data['contract_id'] = null;
                $data['staff_id'] = null;
            }

            $expense->update($data);

            // Handle File Uploads (QID/Passport) for the associated staff member
            if ($expense->staff_id) {
                if ($request->hasFile('qid_files')) {
                    foreach ($request->file('qid_files') as $file) {
                        $path = $file->store('staff/qid', 'public');
                        StaffDocument::create([
                            'staff_id' => $expense->staff_id,
                            'document_type' => 'qid',
                            'file_path' => $path,
                            'file_name' => $file->getClientOriginalName(),
                            'uploaded_by' => auth()->id() ?? 1,
                            'uploaded_at' => now(),
                        ]);
                    }
                }

                if ($request->hasFile('passport_files')) {
                    foreach ($request->file('passport_files') as $file) {
                        $path = $file->store('staff/passport', 'public');
                        StaffDocument::create([
                            'staff_id' => $expense->staff_id,
                            'document_type' => 'passport',
                            'file_path' => $path,
                            'file_name' => $file->getClientOriginalName(),
                            'uploaded_by' => auth()->id() ?? 1,
                            'uploaded_at' => now(),
                        ]);
                    }
                }
            }

            return $expense->load(['category', 'subcategory', 'staff', 'contract.staff']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    public function finalizeRenewal($id)
    {
        try {
            $expense = Expense::with(['staff', 'subcategory'])->findOrFail($id);

            if (!$expense->staff || !$expense->validation_date) {
                return response()->json(['message' => 'Missing staff or validation date'], 400);
            }

            $subName = strtoupper($expense->subcategory?->name ?? '');
            $staff = $expense->staff;

            if (str_contains($subName, 'QID')) {
                $staff->qid_expiry = $expense->validation_date;
            } elseif (str_contains($subName, 'PASSPORT') || str_contains($subName, 'PP')) {
                $staff->passport_expiry = $expense->validation_date;
            } else {
                return response()->json(['message' => 'Not a QID or Passport renewal expense'], 400);
            }

            $staff->save();

            // Mark expense as completed
            $expense->update([
                'renewal_status' => 'completed',
                'renewal_notes' => ($expense->renewal_notes ? $expense->renewal_notes . "\n" : "") . "Finalized on " . now()->format('d M Y')
            ]);

            return response()->json([
                'message' => 'Staff record updated successfully',
                'staff' => $staff
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Finalization failed: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $expense = Expense::findOrFail($id);
            $expense->delete();
            return response()->json(['message' => 'Expense deleted successfully']);
        } catch (\Exception $e) {
            \Log::error('Expense deletion failed: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete: ' . $e->getMessage()], 500);
        }
    }
}
