<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankDetail;
use Illuminate\Http\Request;

class BankDetailController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search', '');

        $query = BankDetail::with('company');

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('bank_name', 'like', "%{$search}%")
                  ->orWhere('account_number', 'like', "%{$search}%")
                  ->orWhere('person_name', 'like', "%{$search}%")
                  ->orWhere('qid', 'like', "%{$search}%")
                  ->orWhere('mobile_number', 'like', "%{$search}%")
                  ->orWhere('bank_details_for', 'like', "%{$search}%")
                  ->orWhere('card_type', 'like', "%{$search}%")
                  ->orWhere('card_number', 'like', "%{$search}%")
                  ->orWhereHas('company', function($c) use ($search) {
                      $c->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $creditTotal = (clone $query)->where('card_type', 'Credit Card')->sum('balance');
        $debitTotal = (clone $query)->where('card_type', 'Debit Card')->sum('balance');

        $bankDetails = $query->latest()->paginate($perPage);

        return response()->json(array_merge($bankDetails->toArray(), [
            'summary' => [
                'credit_total' => $creditTotal,
                'debit_total' => $debitTotal
            ]
        ]));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bank_details_for' => 'required|in:Company,Person',
            'company_id' => 'nullable|exists:companies,id|required_if:bank_details_for,Company',
            'person_name' => 'nullable|string|max:255|required_if:bank_details_for,Person',
            'qid' => 'nullable|string|size:11|regex:/^[0-9]+$/|required_if:bank_details_for,Person',
            'mobile_number' => 'required|string|size:8|regex:/^[0-9]+$/',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'balance' => 'required|numeric',
            'card_type' => 'required|string|max:50',
            'card_number' => 'required|string|max:255',
            'updated_date' => 'nullable|date',
            'document' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        if ($validated['bank_details_for'] === 'Company') {
            $validated['person_name'] = null;
            $validated['qid'] = null;
        } else {
            $validated['company_id'] = null;
        }

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $filename = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            $path = $file->storeAs('bank_documents', $filename, 'public');
            $validated['document'] = '/storage/' . $path;
        }

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
            'bank_details_for' => 'required|in:Company,Person',
            'company_id' => 'nullable|exists:companies,id|required_if:bank_details_for,Company',
            'person_name' => 'nullable|string|max:255|required_if:bank_details_for,Person',
            'qid' => 'nullable|string|size:11|regex:/^[0-9]+$/|required_if:bank_details_for,Person',
            'mobile_number' => 'required|string|size:8|regex:/^[0-9]+$/',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'balance' => 'required|numeric',
            'card_type' => 'required|string|max:50',
            'card_number' => 'required|string|max:255',
            'updated_date' => 'nullable|date',
            'document' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        if ($validated['bank_details_for'] === 'Company') {
            $validated['person_name'] = null;
            $validated['qid'] = null;
        } else {
            $validated['company_id'] = null;
        }

        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $filename = time() . '_' . str_replace(' ', '_', $file->getClientOriginalName());
            $path = $file->storeAs('bank_documents', $filename, 'public');
            $validated['document'] = '/storage/' . $path;
        }

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
