<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Http\Resources\ContractResource;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(Request $request)
    {
        $query = Contract::with(['staff', 'company']);
        
        if ($request->search) {
            $query->whereHas('staff', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            })->orWhereHas('company', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
        }

        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        return ContractResource::collection($query->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'company_id' => 'required|exists:companies,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'contract_value' => 'required|numeric|min:0',
            'payment_type' => 'required|in:Cash,Online',
        ]);

        // Overlap check
        $overlap = Contract::where('staff_id', $data['staff_id'])
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlap) {
            return response()->json([
                'message' => 'This employee is already assigned to another company for the selected dates.'
            ], 422);
        }

        $entity = Contract::create($data);
        return new ContractResource($entity->load(['staff', 'company']));
    }

    public function show($id)
    {
        return new ContractResource(Contract::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $entity = Contract::findOrFail($id);
        $data = $request->validate([
            'staff_id' => 'sometimes|exists:staff,id',
            'company_id' => 'sometimes|exists:companies,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
            'contract_value' => 'sometimes|numeric|min:0',
            'payment_type' => 'sometimes|in:Cash,Online',
        ]);

        // Overlap check
        if ($request->hasAny(['staff_id', 'start_date', 'end_date'])) {
            $staffId = $request->get('staff_id', $entity->staff_id);
            $startDate = $request->get('start_date', $entity->start_date);
            $endDate = $request->get('end_date', $entity->end_date);

            $overlap = Contract::where('staff_id', $staffId)
                ->where('id', '!=', $id)
                ->where('start_date', '<=', $endDate)
                ->where('end_date', '>=', $startDate)
                ->exists();

            if ($overlap) {
                return response()->json([
                    'message' => 'This employee is already assigned to another company for the selected dates.'
                ], 422);
            }
        }

        $entity->update($data);
        return new ContractResource($entity->load(['staff', 'company']));
    }

    public function destroy($id)
    {
        $entity = Contract::findOrFail($id);
        $entity->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
