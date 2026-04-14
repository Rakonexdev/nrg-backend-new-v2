<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        return ExpenseCategory::with('parent')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'parent_id' => 'nullable|exists:expense_categories,id',
            'description' => 'nullable|string'
        ]);
        return ExpenseCategory::create($data);
    }

    public function show($id)
    {
        return ExpenseCategory::with('parent')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $cat = ExpenseCategory::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|required|string',
            'parent_id' => 'nullable|exists:expense_categories,id',
            'description' => 'nullable|string'
        ]);
        $cat->update($data);
        return $cat;
    }

    public function destroy($id)
    {
        try {
            $category = ExpenseCategory::findOrFail($id);
            $category->delete();
            return response()->json(['message' => 'Category deleted successfully']);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() == "23000") {
                return response()->json([
                    'message' => 'Cannot delete this category because it is already linked to one or more expense records. Please delete the expenses first or reassign them to a different category.'
                ], 422);
            }
            throw $e;
        }
    }
}