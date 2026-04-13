<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Http\Resources\ContractResource;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index()
    {
        return ContractResource::collection(Contract::paginate(15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
        ]); // Need specific validation
        $entity = Contract::create($request->all());
        \App\Events\ContractCreated::dispatch($entity);
        return new ContractResource($entity);
    }

    public function show($id)
    {
        return new ContractResource(Contract::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $entity = Contract::findOrFail($id);
        $entity->update($request->all());
        return new ContractResource($entity);
    }

    public function destroy($id)
    {
        $entity = Contract::findOrFail($id);
        $entity->delete();
        return response()->json(['message' => 'Deleted']);
    }
}
