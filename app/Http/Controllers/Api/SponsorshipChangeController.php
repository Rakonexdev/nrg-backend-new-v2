<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SponsorshipChange;
use Illuminate\Http\Request;

class SponsorshipChangeController extends Controller
{
    public function index(Request $request)
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('sponsorship_changes', 'password')) {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        }

        $query = SponsorshipChange::with(['company', 'payments.user']);

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('sr_number', 'like', "%{$search}%")
                  ->orWhere('qid_number', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('identity_phone', 'like', "%{$search}%")
                  ->orWhere('alt_phone', 'like', "%{$search}%")
                  ->orWhere('ec_number', 'like', "%{$search}%")
                  ->orWhere('referral_contact_person', 'like', "%{$search}%")
                  ->orWhere('reference_contact_number', 'like', "%{$search}%")
                  ->orWhere('reference_alt_number', 'like', "%{$search}%")
                  ->orWhereHas('company', function($c) use ($search) {
                      $c->where('name', 'like', "%{$search}%")
                        ->orWhere('computer_card', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('tab')) {
            if ($request->tab === 'approved') {
                $query->where('final_status', 'Approval');
            } elseif ($request->tab === 'rejected') {
                $query->where('final_status', 'Rejected');
            } elseif ($request->tab === 'completed') {
                $query->where('final_status', 'Completed');
            } elseif ($request->tab === 'stopped') {
                $query->where('final_status', 'stopped');
            } else {
                $query->where(function($q) {
                    $q->whereNotIn('final_status', ['Approval', 'Rejected', 'Completed', 'stopped'])
                      ->orWhereNull('final_status')
                      ->orWhere('final_status', 'submission');
                });
            }
        }

        if ($request->has('from_date') && !empty($request->from_date)) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        
        if ($request->has('to_date') && !empty($request->to_date)) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $perPage = $request->input('per_page', 10);
        
        $sponsorships = $query->latest()->paginate($perPage);

        return response()->json($sponsorships);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'qid_number' => 'required|string|max:255',
            'qid_expiry_date' => 'nullable|date',
            'full_name' => 'required|string|max:255',
            'submitted_date' => 'nullable|date',
            'being_here' => 'nullable|string|max:255',
            'ec_number' => 'nullable|string|max:255',
            'computer_card' => 'nullable|string|max:255',
            'new_company_id' => 'required|exists:companies,id',
            'phone' => 'nullable|string|max:255',
            'alt_phone' => 'nullable|string|max:255',
            'identity_phone' => 'required|string|max:255',
            'identity_alt_phone' => 'nullable|string|max:255',
            'referral_contact_person' => 'required|string|max:255',
            'reference_contact_number' => 'required|string|max:255',
            'reference_alt_number' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'document' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'approval_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'approval_date' => 'required_if:final_status,Approval|nullable|date',
            'approval_expiry' => 'required_if:final_status,Approval|nullable|date',
            'labour_contract' => 'nullable|string|max:255',
            'final_status' => 'nullable|string|max:255',
            'total_contract_amount' => 'nullable|numeric|min:0',
            'pay_amount' => 'nullable|numeric|min:0',
            'remark' => 'nullable|string',
        ]);

        $validated['total_contract_amount'] = $validated['total_contract_amount'] ?? 0;
        $validated['pay_amount'] = $validated['pay_amount'] ?? 0;
        $validated['due_amount'] = $validated['total_contract_amount'] - $validated['pay_amount'];

        if ($request->hasFile('document')) {
            $validated['document'] = $request->file('document')->store('sponsorship_documents', 'public');
        }

        if ($request->hasFile('approval_file')) {
            $validated['approval_file'] = $request->file('approval_file')->store('sponsorship_documents', 'public');
        }

        $lastSponsorship = SponsorshipChange::orderByRaw('CAST(sr_number AS UNSIGNED) DESC')->first();
        $nextSerial = 1;
        if ($lastSponsorship && is_numeric($lastSponsorship->sr_number)) {
            $nextSerial = intval($lastSponsorship->sr_number) + 1;
        }
        $validated['sr_number'] = str_pad($nextSerial, 3, '0', STR_PAD_LEFT);

        $sponsorship = SponsorshipChange::create($validated);

        return response()->json($sponsorship->load('company'), 201);
    }

    public function show(SponsorshipChange $sponsorshipChange)
    {
        return response()->json($sponsorshipChange->load('company'));
    }

    public function update(Request $request, SponsorshipChange $sponsorshipChange)
    {
        $validated = $request->validate([
            'sr_number' => 'required|string|max:255',
            'qid_number' => 'required|string|max:255',
            'qid_expiry_date' => 'nullable|date',
            'full_name' => 'required|string|max:255',
            'submitted_date' => 'nullable|date',
            'being_here' => 'nullable|string|max:255',
            'ec_number' => 'nullable|string|max:255',
            'computer_card' => 'nullable|string|max:255',
            'new_company_id' => 'required|exists:companies,id',
            'phone' => 'nullable|string|max:255',
            'alt_phone' => 'nullable|string|max:255',
            'identity_phone' => 'required|string|max:255',
            'identity_alt_phone' => 'nullable|string|max:255',
            'referral_contact_person' => 'required|string|max:255',
            'reference_contact_number' => 'required|string|max:255',
            'reference_alt_number' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:255',
            'document' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'approval_file' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120',
            'approval_date' => 'required_if:final_status,Approval|nullable|date',
            'approval_expiry' => 'required_if:final_status,Approval|nullable|date',
            'labour_contract' => 'nullable|string|max:255',
            'final_status' => 'nullable|string|max:255',
            'total_contract_amount' => 'nullable|numeric|min:0',
            'pay_amount' => 'nullable|numeric|min:0',
            'remark' => 'nullable|string',
        ]);

        $validated['total_contract_amount'] = $validated['total_contract_amount'] ?? 0;
        $validated['pay_amount'] = $validated['pay_amount'] ?? 0;
        $validated['due_amount'] = $validated['total_contract_amount'] - $validated['pay_amount'];

        if ($request->hasFile('document')) {
            if ($sponsorshipChange->document) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($sponsorshipChange->document);
            }
            $validated['document'] = $request->file('document')->store('sponsorship_documents', 'public');
        }

        if ($request->hasFile('approval_file')) {
            if ($sponsorshipChange->approval_file) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($sponsorshipChange->approval_file);
            }
            $validated['approval_file'] = $request->file('approval_file')->store('sponsorship_documents', 'public');
        }

        $sponsorshipChange->update($validated);

        return response()->json($sponsorshipChange->load('company'));
    }

    public function destroy(SponsorshipChange $sponsorshipChange)
    {
        $sponsorshipChange->delete();
        return response()->json(null, 204);
    }

    public function addPayment(Request $request, SponsorshipChange $sponsorshipChange)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'next_payment_date' => 'nullable|date',
            'method' => 'required|string',
        ]);

        $validated['user_id'] = auth()->id();

        $payment = $sponsorshipChange->payments()->create($validated);

        // Update paid amount and due amount
        $sponsorshipChange->pay_amount = $sponsorshipChange->payments()->sum('amount');
        $sponsorshipChange->due_amount = $sponsorshipChange->total_contract_amount - $sponsorshipChange->pay_amount;
        $sponsorshipChange->save();

        return response()->json([
            'message' => 'Payment added successfully',
            'payment' => $payment->load('user'),
            'sponsorship' => $sponsorshipChange->load(['company', 'payments.user'])
        ], 201);
    }

    public function updatePayment(Request $request, SponsorshipChange $sponsorshipChange, \App\Models\SponsorshipPayment $payment)
    {
        if ($payment->sponsorship_change_id !== $sponsorshipChange->id) {
            return response()->json(['message' => 'Payment does not belong to this sponsorship'], 400);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'next_payment_date' => 'nullable|date',
            'method' => 'required|string',
        ]);

        $payment->update($validated);

        // Update paid amount and due amount
        $sponsorshipChange->pay_amount = $sponsorshipChange->payments()->sum('amount');
        $sponsorshipChange->due_amount = $sponsorshipChange->total_contract_amount - $sponsorshipChange->pay_amount;
        $sponsorshipChange->save();

        return response()->json([
            'message' => 'Payment updated successfully',
            'payment' => $payment->load('user'),
            'sponsorship' => $sponsorshipChange->load(['company', 'payments.user'])
        ]);
    }

    public function deletePayment(SponsorshipChange $sponsorshipChange, \App\Models\SponsorshipPayment $payment)
    {
        if ($payment->sponsorship_change_id !== $sponsorshipChange->id) {
            return response()->json(['message' => 'Payment does not belong to this sponsorship'], 400);
        }

        $payment->delete();

        // Update paid amount and due amount
        $sponsorshipChange->pay_amount = $sponsorshipChange->payments()->sum('amount');
        $sponsorshipChange->due_amount = $sponsorshipChange->total_contract_amount - $sponsorshipChange->pay_amount;
        $sponsorshipChange->save();

        return response()->json([
            'message' => 'Payment deleted successfully',
            'sponsorship' => $sponsorshipChange->load(['company', 'payments.user'])
        ]);
    }
}
