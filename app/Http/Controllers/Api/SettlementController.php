<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Settlement;
use App\Services\SettlementService;
use Illuminate\Http\Request;

class SettlementController extends Controller
{
    protected $service;
    public function __construct(SettlementService $service) {
        $this->service = $service;
    }

    public function index()
    {
        return Settlement::with('collector', 'collections')->paginate(15);
    }

    public function todaySummary(Request $request)
    {
        $userId = $request->user()->id;
        $today = now()->toDateString();

        // 1. Unsettled (recorded but not in a settlement request yet)
        $unsettled = \App\Models\ContractPayment::with('contract.staff.company')
            ->where('created_by', $userId)
            ->where('is_settled', false)
            ->whereNull('settlement_id')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'company' => $p->contract?->staff?->company?->name ?? 'N/A',
                'amount' => (float)$p->amount,
                'method' => $p->payment_method,
                'time' => $p->created_at->format('h:i A')
            ]);

        // 2. Pending (requested but not confirmed by admin)
        $pendingSettlements = Settlement::where('collector_id', $userId)
            ->where('status', 'pending')
            ->where('settlement_date', $today)
            ->with(['collections.company', 'collections.invoice']) // for old system if any
            ->get();
        
        // We'll also get ContractPayments for these pending settlements
        $pendingPayments = \App\Models\ContractPayment::with('contract.staff.company')
            ->whereIn('settlement_id', $pendingSettlements->pluck('id'))
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'company' => $p->contract?->staff?->company?->name ?? 'N/A',
                'amount' => (float)$p->amount,
                'method' => $p->payment_method,
                'time' => $p->created_at->format('h:i A')
            ]);

        // 3. Settled Today
        $settledToday = Settlement::where('collector_id', $userId)
            ->where('status', 'confirmed')
            ->whereDate('settled_at', $today)
            ->sum('total_settled');

        // 4. Full Collection History for Today
        $history = \App\Models\ContractPayment::with('contract.staff.company')
            ->where('created_by', $userId)
            ->whereDate('payment_date', $today)
            ->latest()
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'company' => $p->contract?->staff?->company?->name ?? 'N/A',
                'amount' => (float)$p->amount,
                'method' => $p->payment_method,
                'time' => $p->created_at->format('h:i A'),
                'is_settled' => $p->is_settled,
                'is_pending' => (bool)$p->settlement_id && !$p->is_settled
            ]);

        return response()->json([
            'unsettled' => $unsettled,
            'pending_payments' => $pendingPayments,
            'history' => $history,
            'total_unsettled_amount' => $unsettled->sum('amount'),
            'total_pending_amount' => $pendingPayments->sum('amount'),
            'total_settled_today' => (float)$settledToday,
            'total_collected_today' => $history->sum('amount')
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'collection_ids' => 'nullable|array',
            'contract_payment_ids' => 'nullable|array',
            'notes' => 'nullable|string'
        ]);

        $settlement = $this->service->createSettlement(
            $request->user()->id,
            $request->collection_ids ?? [],
            $request->contract_payment_ids ?? [],
            $request->notes
        );

        return response()->json($settlement, 201);
    }

    public function confirm(Request $request, $id)
    {
        $request->validate([
            'total_settled' => 'required|numeric'
        ]);

        $settlement = Settlement::findOrFail($id);
        $confirmed = $this->service->confirmSettlement(
            $settlement, 
            $request->user()->id, 
            $request->total_settled
        );

        return response()->json($confirmed);
    }
}