<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Http\Resources\CompanyResource;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('computer_card', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->get('status') === 'active');
        }

        // Simple mode for dropdowns
        if ($request->get('mode') === 'simple') {
            return response()->json($query->select('id', 'name')->orderBy('name')->get());
        }

        // Pagination/All
        if (!$request->has('page') && !$request->has('per_page')) {
            return CompanyResource::collection($query->latest()->get());
        }

        $perPage = $request->get('per_page', 15);
        return CompanyResource::collection($query->latest()->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:companies,name',
            'computer_card' => 'nullable|string|max:255',
            'branch_number' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:255',
            'alternative_phone_number' => 'nullable|string|max:255',
            'is_active' => 'boolean'
        ]);

        if (!isset($validated['is_active'])) {
            $validated['is_active'] = true;
        }

        $company = Company::create($validated);
        return response()->json($company, 201);
    }

    public function show(Company $company)
    {
        return response()->json($company);
    }

    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:companies,name,' . $id,
            'computer_card' => 'nullable|string|max:255',
            'branch_number' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:255',
            'alternative_phone_number' => 'nullable|string|max:255',
            'is_active' => 'sometimes|boolean'
        ]);

        $company->update($validated);
        return response()->json($company);
    }

    public function destroy(Company $company)
    {
        $company->delete();
        return response()->json(null, 204);
    }
}
