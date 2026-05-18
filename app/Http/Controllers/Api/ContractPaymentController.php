<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContractPaymentResource;
use App\Models\Contract;
use App\Models\ContractPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractPaymentController extends Controller
{
    public function index($contractId)
    {
        $contract = Contract::with('payments.creator.roles')->findOrFail($contractId);
        return ContractPaymentResource::collection($contract->payments);
    }

    public function store(Request $request, $contractId)
    {
        $contract = Contract::findOrFail($contractId);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:Cash,Online',
            'subcategory' => 'nullable|string|max:255',
            'next_payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'contract_adjustment_id' => 'nullable|exists:contract_adjustments,id'
        ]);

        $adjustmentId = $data['contract_adjustment_id'] ?? null;
        if ($adjustmentId) {
            // Case 1: Additional Payment (Employee to NRG)
            $adjustment = \App\Models\ContractAdjustment::findOrFail($adjustmentId);
            $currentPaidOnAdjustment = round((float) $adjustment->payments()->sum('amount'), 2);
            $newTotalPaidOnAdjustment = round($currentPaidOnAdjustment + (float) $data['amount'], 2);

            if ($newTotalPaidOnAdjustment > (float) $adjustment->amount) {
                throw ValidationException::withMessages([
                    'amount' => 'This payment would exceed the additional amount total (QAR ' . number_format($adjustment->amount, 2) . ').',
                ]);
            }
        } else {
            // Case 2: Admin Collection (Company to NRG)
            $currentPaidOnContract = round((float) $contract->payments()->whereNull('contract_adjustment_id')->sum('amount'), 2);
            $newTotalPaidOnContract = round($currentPaidOnContract + (float) $data['amount'], 2);
            $contractValue = (float) $contract->total_income;

            if ($newTotalPaidOnContract > $contractValue) {
                throw ValidationException::withMessages([
                    'amount' => 'This payment would exceed the total contract value (QAR ' . number_format($contractValue, 2) . ').',
                ]);
            }
        }

        $payment = DB::transaction(function () use ($contract, $data) {
            $payment = $contract->payments()->create([
                ...$data,
                'created_by' => auth()->id(),
            ]);

            if ($payment->contract_adjustment_id) {
                $adjustment = \App\Models\ContractAdjustment::find($payment->contract_adjustment_id);
                if ($adjustment) {
                    $paid = $adjustment->payments()->sum('amount');
                    $adjustment->update([
                        'paid_amount' => $paid,
                        'pending_amount' => round($adjustment->amount - $paid, 2),
                        'next_payment_date' => $payment->next_payment_date
                    ]);
                }
            }

            $contract->syncPaymentTracking();

            return $payment;
        });

        $payment->load('creator.roles');
        return new ContractPaymentResource($payment);
    }

    public function destroy($contractId, $paymentId)
    {
        $contract = Contract::findOrFail($contractId);
        $payment = ContractPayment::where('contract_id', $contract->id)->findOrFail($paymentId);

        DB::transaction(function () use ($payment, $contract) {
            $adjustmentId = $payment->contract_adjustment_id;
            $payment->delete();

            if ($adjustmentId) {
                $adjustment = \App\Models\ContractAdjustment::find($adjustmentId);
                if ($adjustment) {
                    $paid = $adjustment->payments()->sum('amount');
                    $latestPayment = $adjustment->payments()->latest('payment_date')->first();
                    $nextDate = $latestPayment ? $latestPayment->next_payment_date : null;
                    $adjustment->update([
                        'paid_amount' => $paid,
                        'pending_amount' => round($adjustment->amount - $paid, 2),
                        'next_payment_date' => $nextDate
                    ]);
                }
            }

            $contract->syncPaymentTracking();
        });

        return response()->json(['message' => 'Payment deleted']);
    }

    public function update(Request $request, $contractId, $paymentId)
    {
        $contract = Contract::findOrFail($contractId);
        $payment = ContractPayment::where('contract_id', $contract->id)->findOrFail($paymentId);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:Cash,Online',
            'subcategory' => 'nullable|string|max:255',
            'next_payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $adjustmentId = $payment->contract_adjustment_id;
        if ($adjustmentId) {
            // Case 1: Additional Payment (Employee to NRG)
            $adjustment = \App\Models\ContractAdjustment::findOrFail($adjustmentId);
            $currentPaidOnAdjustment = round((float) $adjustment->payments()->where('id', '!=', $payment->id)->sum('amount'), 2);
            $newTotalPaidOnAdjustment = round($currentPaidOnAdjustment + (float) $data['amount'], 2);

            if ($newTotalPaidOnAdjustment > (float) $adjustment->amount) {
                throw ValidationException::withMessages([
                    'amount' => 'This payment would exceed the additional amount total (QAR ' . number_format($adjustment->amount, 2) . ').',
                ]);
            }
        } else {
            // Case 2: Admin Collection (Company to NRG)
            $currentPaidOnContract = round((float) $contract->payments()->whereNull('contract_adjustment_id')->where('id', '!=', $payment->id)->sum('amount'), 2);
            $newTotalPaidOnContract = round($currentPaidOnContract + (float) $data['amount'], 2);
            $contractValue = (float) $contract->total_income;

            if ($newTotalPaidOnContract > $contractValue) {
                throw ValidationException::withMessages([
                    'amount' => 'This payment would exceed the total contract value (QAR ' . number_format($contractValue, 2) . ').',
                ]);
            }
        }

        DB::transaction(function () use ($contract, $payment, $data, $adjustmentId) {
            $payment->update($data);

            if ($adjustmentId) {
                $adjustment = \App\Models\ContractAdjustment::find($adjustmentId);
                if ($adjustment) {
                    $paid = $adjustment->payments()->sum('amount');
                    $adjustment->update([
                        'paid_amount' => $paid,
                        'pending_amount' => round($adjustment->amount - $paid, 2),
                        'next_payment_date' => $payment->next_payment_date
                    ]);
                }
            }

            $contract->syncPaymentTracking();
        });

        $payment->load('creator.roles');
        return new ContractPaymentResource($payment);
    }
}
