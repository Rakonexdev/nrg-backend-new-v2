<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankDetail;
use Illuminate\Http\Request;

class BankDetailController extends Controller
{
    public function index()
    {
        $bankDetails = BankDetail::with('company')->get();
        return response()->json($bankDetails);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'person_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'balance' => 'required|numeric',
            'card_type' => 'nullable|string|max:50',
            'updated_date' => 'nullable|date',
        ]);

        $bankDetail = BankDetail::create($validated);
        $bankDetail->load('company');

        return response()->json([
            'message' => 'Bank detail added successfully',
            'bank_detail' => $bankDetail
        ], 201);
    }

    public function show($id)
    {
        $bankDetail = BankDetail::with('company')->findOrFail($id);
        return response()->json($bankDetail);
    }

    public function update(Request $request, $id)
    {
        $bankDetail = BankDetail::findOrFail($id);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'person_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'balance' => 'required|numeric',
            'card_type' => 'nullable|string|max:50',
            'updated_date' => 'nullable|date',
        ]);

        $bankDetail->update($validated);
        $bankDetail->load('company');

        return response()->json([
            'message' => 'Bank detail updated successfully',
            'bank_detail' => $bankDetail
        ]);
    }

    public function destroy($id)
    {
        $bankDetail = BankDetail::findOrFail($id);
        $bankDetail->delete();

        return response()->json([
            'message' => 'Bank detail deleted successfully'
        ]);
    }
}
