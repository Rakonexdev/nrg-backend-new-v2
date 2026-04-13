<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        return ExpenseCategory::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'parent_id' => 'nullable|exists:expense_categories,id'
        ]);
        return ExpenseCategory::create($data);
    }

    public function show($id)
    {
        return ExpenseCategory::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $cat = ExpenseCategory::findOrFail($id);
        $cat->update($request->all());
        return $cat;
    }

    public function destroy($id)
    {
        ExpenseCategory::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }
}