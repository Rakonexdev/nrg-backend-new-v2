<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with(['category', 'subcategory', 'recorder', 'company', 'staff', 'contract.staff', 'contract.company']);

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->date) {
            $query->whereDate('expense_date', $request->date);
        }

        if ($request->contract_id) {
            $query->where('contract_id', $request->contract_id);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('vendor_name', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%")
                  ->orWhereHas('category', function($cq) use ($request) {
                      $cq->where('name', 'like', "%{$request->search}%");
                  })
                  ->orWhereHas('subcategory', function($sq) use ($request) {
                      $sq->where('name', 'like', "%{$request->search}%");
                  });
            });
        }

        return $query->orderBy('expense_date', 'desc')->paginate(15);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'expense_date' => 'required|date',
                'category_id' => 'required|exists:expense_categories,id',
                'subcategory_id' => 'nullable|exists:expense_categories,id',
                'amount' => 'required|numeric',
                'payment_method' => 'required|string',
                'vendor_name' => 'nullable|string',
                'description' => 'nullable|string',
                'company_id' => 'nullable|exists:companies,id',
                'staff_id' => 'nullable|exists:staff,id',
                'contract_id' => 'nullable|exists:contracts,id',
            ]);

            if (!empty($data['contract_id'])) {
                $contract = \App\Models\Contract::findOrFail($data['contract_id']);
                $data['company_id'] = $contract->company_id;
                $data['staff_id'] = $contract->staff_id;
            }
            
            $data['recorded_by'] = $request->user()?->id;
            
            if (!$data['recorded_by']) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            
            $expense = Expense::create($data);
            return $expense->load(['category', 'subcategory', 'company', 'staff', 'contract.staff', 'contract.company']);
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
        return Expense::with(['category', 'subcategory', 'recorder', 'company', 'staff', 'contract.staff', 'contract.company'])->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        try {
            $expense = Expense::findOrFail($id);
            $data = $request->validate([
                'expense_date' => 'sometimes|required|date',
                'category_id' => 'sometimes|required|exists:expense_categories,id',
                'subcategory_id' => 'nullable|exists:expense_categories,id',
                'amount' => 'sometimes|required|numeric',
                'payment_method' => 'sometimes|required|string',
                'vendor_name' => 'nullable|string',
                'description' => 'nullable|string',
                'company_id' => 'nullable|exists:companies,id',
                'staff_id' => 'nullable|exists:staff,id',
                'contract_id' => 'nullable|exists:contracts,id',
            ]);

            if (array_key_exists('contract_id', $data) && !empty($data['contract_id'])) {
                $contract = \App\Models\Contract::findOrFail($data['contract_id']);
                $data['company_id'] = $contract->company_id;
                $data['staff_id'] = $contract->staff_id;
            }

            if (array_key_exists('contract_id', $data) && empty($data['contract_id'])) {
                $data['contract_id'] = null;
                $data['company_id'] = null;
                $data['staff_id'] = null;
            }
            
            $expense->update($data);
            return $expense->load(['category', 'subcategory', 'company', 'staff', 'contract.staff', 'contract.company']);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Update failed: ' . $e->getMessage()], 500);
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
