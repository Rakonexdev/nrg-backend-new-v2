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

    public function store(Request $request)
    {
        $request->validate([
            'collection_ids' => 'required|array',
            'notes' => 'nullable|string'
        ]);

        $settlement = $this->service->createSettlement(
            $request->user()->id,
            $request->collection_ids,
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