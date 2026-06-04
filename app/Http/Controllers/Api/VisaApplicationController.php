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

        $query = VisaApplication::with(['company', 'payments.user']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('vp_number', 'like', "%{$search}%")
                  ->orWhere('serial_no', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('passport_number', 'like', "%{$search}%")
                  ->orWhere('visa_number', 'like', "%{$search}%")
                  ->orWhere('contract_person', 'like', "%{$search}%")
                  ->orWhere('contract_person_phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $isActive = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $isActive);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('visa_status')) {
            $query->where('medical_report', $request->visa_status);
        }

        $totalCollected = (clone $query)->sum('total_pay');
        $totalPending = (clone $query)->sum('due_amount');
        $totalExpiredVps = (clone $query)->whereNotNull('vp_expiry_date')->whereDate('vp_expiry_date', '<', now())->count();

        if ($request->filled('vp_expired') && $request->vp_expired) {
            $query->whereNotNull('vp_expiry_date')->whereDate('vp_expiry_date', '<', now());
        }

        if ($request->filled('has_due') && $request->has_due) {
            $query->where('due_amount', '>', 0);
        }

        $perPage = $request->input('per_page', 10);
        $applications = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $applications->items(),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'total' => $applications->total(),
                'per_page' => $applications->perPage(),
            ],
            'summary' => [
                'total_collected' => $totalCollected,
                'total_pending' => $totalPending,
                'total_expired_vps' => $totalExpiredVps,
            ]
        ]);
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
            'visa_expiry_date' => 'nullable|date',
            
            'description' => 'nullable|string|max:255',
            'appointment_date' => 'nullable|date',
            'contract_person' => 'required|string|max:255',
            'contract_person_phone' => 'required|string|max:255',
            'medical_report' => 'nullable|string|max:255',
            'attestation_details' => 'nullable|string|max:255',
            
            'payment_date' => 'nullable|date',
            'total_amount' => 'required|numeric',
            'total_pay' => 'required|numeric',
            'due_amount' => 'required|numeric',
            
            'passport_photo' => 'required|file|max:5120',
            'personal_photo' => 'required|file|max:5120',
            'medical_appointment_page' => 'nullable|file|max:5120',
            'visa_copy' => 'nullable|file|max:5120',
            
            'is_active' => 'boolean'
        ]);

        $fileFields = ['passport_photo', 'personal_photo', 'medical_appointment_page', 'visa_copy'];
        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                $validated[$field] = $request->file($field)->store('visa_documents', 'public');
            }
        }

        $application = VisaApplication::create($validated);

        return response()->json([
            'message' => 'Visa application created successfully',
            'data' => $application->load(['company', 'payments.user'])
        ], 201);
    }

    public function show(VisaApplication $visaApplication)
    {
        return response()->json($visaApplication->load(['company', 'payments.user']));
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
            'visa_expiry_date' => 'sometimes|nullable|date',
            
            'description' => 'sometimes|nullable|string|max:255',
            'appointment_date' => 'sometimes|nullable|date',
            'contract_person' => 'sometimes|required|string|max:255',
            'contract_person_phone' => 'sometimes|required|string|max:255',
            'medical_report' => 'sometimes|nullable|string|max:255',
            'attestation_details' => 'sometimes|nullable|string|max:255',
            
            'payment_date' => 'sometimes|nullable|date',
            'total_amount' => 'sometimes|required|numeric',
            'total_pay' => 'sometimes|required|numeric',
            'due_amount' => 'sometimes|required|numeric',
            
            'passport_photo' => 'nullable|file|max:5120',
            'personal_photo' => 'nullable|file|max:5120',
            'medical_appointment_page' => 'nullable|file|max:5120',
            'visa_copy' => 'nullable|file|max:5120',
            
            'is_active' => 'boolean'
        ]);

        $fileFields = ['passport_photo', 'personal_photo', 'medical_appointment_page', 'visa_copy'];
        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                $validated[$field] = $request->file($field)->store('visa_documents', 'public');
            }
        }

        $visaApplication->update($validated);

        return response()->json([
            'message' => 'Visa application updated successfully',
            'data' => $visaApplication->load(['company', 'payments.user'])
        ]);
    }

    public function destroy(VisaApplication $visaApplication)
    {
        $visaApplication->delete();
        return response()->json(['message' => 'Visa application deleted successfully']);
    }

    public function addPayment(Request $request, VisaApplication $visaApplication)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method' => 'nullable|string',
            'notes' => 'nullable|string',
            'next_due_date' => 'nullable|date'
        ]);

        $visaApplication->payments()->create([
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'method' => $validated['method'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'user_id' => auth()->id(),
        ]);

        // Update the application's totals
        $visaApplication->total_pay = $visaApplication->total_pay + $validated['amount'];
        $visaApplication->due_amount = $visaApplication->total_amount - $visaApplication->total_pay;
        if ($visaApplication->due_amount < 0) {
            $visaApplication->due_amount = 0;
        }
        // Save the updated amounts and the last payment date
        $visaApplication->payment_date = $validated['payment_date'];
        
        if ($request->filled('next_due_date')) {
            $visaApplication->next_due_date = $validated['next_due_date'];
        }
        
        $visaApplication->save();

        return response()->json([
            'message' => 'Payment added successfully',
            'data' => $visaApplication->load(['company', 'payments.user'])
        ]);
    }

    public function updatePayment(Request $request, VisaApplication $visaApplication, \App\Models\VisaPayment $payment)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'method' => 'nullable|string',
            'notes' => 'nullable|string',
            'next_due_date' => 'nullable|date'
        ]);

        // Revert previous payment amount from totals
        $visaApplication->total_pay = $visaApplication->total_pay - $payment->amount;
        
        // Update payment record
        $payment->update([
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'method' => $validated['method'] ?? null,
            'notes' => $validated['notes'] ?? null,
            // optionally keep user_id same or update to current user
            'user_id' => auth()->id(),
        ]);

        // Apply new payment amount to totals
        $visaApplication->total_pay = $visaApplication->total_pay + $validated['amount'];
        $visaApplication->due_amount = $visaApplication->total_amount - $visaApplication->total_pay;
        if ($visaApplication->due_amount < 0) {
            $visaApplication->due_amount = 0;
        }
        
        $visaApplication->payment_date = $validated['payment_date'];
        
        if ($request->filled('next_due_date')) {
            $visaApplication->next_due_date = $validated['next_due_date'];
        }
        
        $visaApplication->save();

        return response()->json([
            'message' => 'Payment updated successfully',
            'data' => $visaApplication->load(['company', 'payments.user'])
        ]);
    }

    public function deletePayment(VisaApplication $visaApplication, \App\Models\VisaPayment $payment)
    {
        // Revert payment from totals
        $visaApplication->total_pay = $visaApplication->total_pay - $payment->amount;
        $visaApplication->due_amount = $visaApplication->total_amount - $visaApplication->total_pay;
        $visaApplication->save();

        $payment->delete();

        return response()->json([
            'message' => 'Payment deleted successfully',
            'data' => $visaApplication->load(['company', 'payments.user'])
        ]);
    }
}
