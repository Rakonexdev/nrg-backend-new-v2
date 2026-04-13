<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Services\CollectionService;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    protected $collectionService;
    public function __construct(CollectionService $collectionService) {
        $this->collectionService = $collectionService;
    }

    public function index()
    {
        return Collection::with('invoice', 'company', 'collector')->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'company_id' => 'required|exists:companies,id',
            'collected_amount' => 'required|numeric',
            'payment_channel' => 'required|in:cash,mobile_pay,bank_transfer',
            'next_due_date' => 'nullable|date',
            'collection_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);
        
        $data['collector_id'] = $request->user()->id;
        
        $collection = $this->collectionService->createCollection($data);
        return response()->json($collection, 201);
    }

    public function unsettled(Request $request)
    {
        return Collection::where('collector_id', $request->user()->id)
            ->where('is_settled', false)
            ->get();
    }
}