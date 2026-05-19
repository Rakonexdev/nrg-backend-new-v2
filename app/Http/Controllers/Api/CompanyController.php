<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Http\Resources\CompanyResource;
use Illuminate\Http\Request;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CompanyController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_companies', only: ['index', 'show']),
            new Middleware('permission:company_create', only: ['store']),
            new Middleware('permission:company_edit', only: ['update']),
            new Middleware('permission:company_delete', only: ['destroy']),
        ];
    }
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
            return response()->json($query->select('id', 'name', 'branch_number')->orderBy('name')->get());
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
            'name' => 'required|string|max:255',
            'computer_card' => 'required|string|digits:8',
            'branch_name' => 'nullable|string|max:255',
            'branch_number' => 'required|string|max:255',
            'contact_person' => 'required|string|max:255',
            'phone_number' => 'required|string|digits:8',
            'alternative_phone_number' => 'nullable|string|digits:8',
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
            'name' => 'sometimes|required|string|max:255',
            'computer_card' => 'sometimes|required|string|digits:8',
            'branch_name' => 'sometimes|nullable|string|max:255',
            'branch_number' => 'sometimes|required|string|max:255',
            'contact_person' => 'sometimes|required|string|max:255',
            'phone_number' => 'sometimes|required|string|digits:8',
            'alternative_phone_number' => 'nullable|string|digits:8',
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

    public function getPendingCollections($id)
    {
        $company = Company::findOrFail($id);
        
        $pendingContracts = \App\Models\Contract::with(['staff'])
            ->where('pending_amount', '>', 0)
            ->whereHas('staff', function($q) use ($id) {
                $q->where('company_id', $id);
            })
            ->get()
            ->map(function($contract) {
                return [
                    'id' => $contract->id,
                    'staff_name' => $contract->staff?->name,
                    'total_income' => (float) $contract->total_income,
                    'paid_amount' => (float) $contract->paid_amount,
                    'pending_amount' => (float) $contract->pending_amount,
                    'payment_status' => $contract->payment_status
                ];
            });

        return response()->json([
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'contact_person' => $company->contact_person,
                'phone_number' => $company->phone_number
            ],
            'pending_contracts' => $pendingContracts
        ]);
    }
}
