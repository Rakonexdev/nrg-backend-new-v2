<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisaApplication;
use Illuminate\Http\Request;

class VisaApplicationController extends Controller
{
    public function index(Request $request)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('visa_applications')) {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        }

        $query = VisaApplication::with('company');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('vp_number', 'like', "%{$search}%")
                  ->orWhere('serial_no', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('passport_number', 'like', "%{$search}%")
                  ->orWhere('visa_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $isActive = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $isActive);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $perPage = $request->input('per_page', 10);
        $applications = $query->latest()->paginate($perPage);

        return response()->json($applications);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'serial_no' => 'required|string|max:255',
            'vp_expiry_date' => 'required|date',
            'vp_number' => 'required|string|max:255',
            'position' => 'required|string|max:255',
            'nationality' => 'required|string|max:255',
            
            'company_id' => 'required|exists:companies,id',
            
            'full_name' => 'required|string|max:255',
            'passport_number' => 'required|string|max:255',
            'visa_number' => 'nullable|string|max:255',
            
            'description' => 'nullable|string|max:255',
            'appointment_date' => 'nullable|date',
            'contract_person' => 'nullable|string|max:255',
            'contract_person_phone' => 'nullable|string|max:255',
            'medical_report' => 'nullable|string|max:255',
            'attestation_details' => 'nullable|string|max:255',
            
            'payment_date' => 'nullable|date',
            'total_amount' => 'required|numeric',
            'total_pay' => 'required|numeric',
            'due_amount' => 'required|numeric',
            
            'is_active' => 'boolean'
        ]);

        $application = VisaApplication::create($validated);

        return response()->json([
            'message' => 'Visa application created successfully',
            'data' => $application->load('company')
        ], 201);
    }

    public function show(VisaApplication $visaApplication)
    {
        return response()->json($visaApplication->load('company'));
    }

    public function update(Request $request, VisaApplication $visaApplication)
    {
        $validated = $request->validate([
            'serial_no' => 'sometimes|required|string|max:255',
            'vp_expiry_date' => 'sometimes|required|date',
            'vp_number' => 'sometimes|required|string|max:255',
            'position' => 'sometimes|required|string|max:255',
            'nationality' => 'sometimes|required|string|max:255',
            
            'company_id' => 'sometimes|required|exists:companies,id',
            
            'full_name' => 'sometimes|required|string|max:255',
            'passport_number' => 'sometimes|required|string|max:255',
            'visa_number' => 'sometimes|nullable|string|max:255',
            
            'description' => 'sometimes|nullable|string|max:255',
            'appointment_date' => 'sometimes|nullable|date',
            'contract_person' => 'sometimes|nullable|string|max:255',
            'contract_person_phone' => 'sometimes|nullable|string|max:255',
            'medical_report' => 'sometimes|nullable|string|max:255',
            'attestation_details' => 'sometimes|nullable|string|max:255',
            
            'payment_date' => 'sometimes|nullable|date',
            'total_amount' => 'sometimes|required|numeric',
            'total_pay' => 'sometimes|required|numeric',
            'due_amount' => 'sometimes|required|numeric',
            
            'is_active' => 'boolean'
        ]);

        $visaApplication->update($validated);

        return response()->json([
            'message' => 'Visa application updated successfully',
            'data' => $visaApplication->load('company')
        ]);
    }

    public function destroy(VisaApplication $visaApplication)
    {
        $visaApplication->delete();
        return response()->json(['message' => 'Visa application deleted successfully']);
    }
}
