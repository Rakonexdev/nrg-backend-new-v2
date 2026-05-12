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
        $contract = Contract::with('payments')->findOrFail($contractId);
        return ContractPaymentResource::collection($contract->payments);
    }

    public function store(Request $request, $contractId)
    {
        $contract = Contract::findOrFail($contractId);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:Cash,Online',
            'subcategory' => 'nullable|string|max:255',
            'next_payment_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'contract_adjustment_id' => 'nullable|exists:contract_adjustments,id'
        ]);

        $currentPaid = round((float) $contract->payments()->sum('amount'), 2);
        $newTotalPaid = round($currentPaid + (float) $data['amount'], 2);

        // Calculate total income (contract value + adjustments)
        $totalIncome = (float) $contract->total_income;

        if ($newTotalPaid > $totalIncome) {
            throw ValidationException::withMessages([
                'amount' => 'This payment would exceed the total contract value (including adjustments).',
            ]);
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
                        'pending_amount' => round($adjustment->amount - $paid, 2)
                    ]);
                }
            }

            $contract->syncPaymentTracking();

            return $payment;
        });

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
                    $adjustment->update([
                        'paid_amount' => $paid,
                        'pending_amount' => round($adjustment->amount - $paid, 2)
                    ]);
                }
            }

            $contract->syncPaymentTracking();
        });

        return response()->json(['message' => 'Payment deleted']);
    }
}
