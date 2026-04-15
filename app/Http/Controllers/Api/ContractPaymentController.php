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
            'notes' => 'nullable|string|max:1000',
        ]);

        $currentPaid = round((float) $contract->payments()->sum('amount'), 2);
        $newTotalPaid = round($currentPaid + (float) $data['amount'], 2);

        if ($newTotalPaid > (float) $contract->contract_value) {
            throw ValidationException::withMessages([
                'amount' => 'This payment would exceed the total contract value.',
            ]);
        }

        $payment = DB::transaction(function () use ($contract, $data) {
            $payment = $contract->payments()->create([
                ...$data,
                'created_by' => auth()->id(),
            ]);

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
            $payment->delete();
            $contract->syncPaymentTracking();
        });

        return response()->json(['message' => 'Payment deleted']);
    }
}
