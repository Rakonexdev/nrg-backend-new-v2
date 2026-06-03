<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyVisa;
use Illuminate\Http\Request;

class CompanyVisaController extends Controller
{
    public function index(Request $request)
    {
        $query = CompanyVisa::with('company');

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'profession' => 'required|string|max:255',
            'available_slots' => 'required|integer|min:0',
        ]);

        $companyVisa = CompanyVisa::create($validated);
        return response()->json($companyVisa, 201);
    }

    public function show(CompanyVisa $companyVisa)
    {
        return response()->json($companyVisa->load('company'));
    }

    public function update(Request $request, CompanyVisa $companyVisa)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'profession' => 'required|string|max:255',
            'available_slots' => 'required|integer|min:0',
        ]);

        $companyVisa->update($validated);
        return response()->json($companyVisa);
    }

    public function destroy(CompanyVisa $companyVisa)
    {
        $companyVisa->delete();
        return response()->json(null, 204);
    }
}
