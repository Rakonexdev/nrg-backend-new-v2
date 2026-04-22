<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = ExpenseCategory::with('parent');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('parent', function($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('type')) {
            if ($request->type === 'main') {
                $query->whereNull('parent_id');
            } elseif ($request->type === 'sub') {
                $query->whereNotNull('parent_id');
            }
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->target_type);
        }

        if ($request->has('per_page')) {
            return $query->latest()->paginate($request->per_page);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'parent_id' => 'nullable|exists:expense_categories,id',
            'description' => 'nullable|string',
            'target_type' => 'nullable|in:Employee,Company'
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
            'description' => 'nullable|string',
            'target_type' => 'nullable|in:Employee,Company'
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