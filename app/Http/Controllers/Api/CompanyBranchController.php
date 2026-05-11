<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyBranch;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyBranchController extends Controller
{
    public function index(Request $request, $companyId)
    {
        $company = Company::findOrFail($companyId);
        
        // Auto-sync company's primary branch info into the branches table if it's missing
        // Use company name as default branch name if branch_name is not explicitly set
        if ($company->branch_number || $company->branch_name) {
            CompanyBranch::firstOrCreate(
                ['company_id' => $company->id, 'name' => $company->branch_name ?: $company->name],
                [
                    'branch_number' => $company->branch_number,
                    'contact_person' => $company->contact_person,
                    'contact_number' => $company->phone_number
                ]
            );
        }

        return response()->json($company->branches()->orderBy('name')->get());
    }

    public function store(Request $request, $companyId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'branch_number' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:20',
        ]);

        $branch = CompanyBranch::create([
            'company_id' => $companyId,
            'name' => $request->name,
            'branch_number' => $request->branch_number,
            'location' => $request->location,
            'contact_person' => $request->contact_person,
            'contact_number' => $request->contact_number,
        ]);

        return response()->json($branch, 201);
    }

    public function update(Request $request, $id)
    {
        $branch = CompanyBranch::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'location' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_number' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean'
        ]);

        $branch->update($request->all());

        return response()->json($branch);
    }

    public function destroy($id)
    {
        $branch = CompanyBranch::findOrFail($id);
        $branch->delete();

        return response()->json(['message' => 'Branch deleted successfully']);
    }
}
