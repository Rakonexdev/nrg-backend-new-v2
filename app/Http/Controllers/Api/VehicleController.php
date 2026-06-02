<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('vehicles')) {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        }

        $query = Vehicle::with('company');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('plate_number', 'like', "%{$search}%")
                  ->orWhere('vehicle_name', 'like', "%{$search}%")
                  ->orWhere('driver_name', 'like', "%{$search}%")
                  ->orWhere('driver_qid', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $isActive = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $isActive);
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        $perPage = $request->input('per_page', 10);
        $vehicles = $query->latest()->paginate($perPage);

        return response()->json($vehicles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'plate_number' => 'required|string|max:255',
            'vehicle_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'chassis_no' => 'nullable|string|max:255',
            'reg_expiry_date' => 'required|date',
            
            'company_id' => 'required|exists:companies,id',
            
            'driver_qid' => 'required|string|size:11|regex:/^[0-9]+$/',
            'driver_name' => 'required|string|max:255',
            'driver_phone' => 'required|string|size:8|regex:/^[0-9]+$/',
            'driver_alt_phone' => 'nullable|string|size:8|regex:/^[0-9]+$/',
            
            'handover_datetime' => 'nullable|date',
            'return_datetime' => 'nullable|date',
            
            'is_active' => 'boolean'
        ]);

        $vehicle = Vehicle::create($validated);

        return response()->json([
            'message' => 'Vehicle created successfully',
            'data' => $vehicle->load('company')
        ], 201);
    }

    public function show(Vehicle $vehicle)
    {
        return response()->json($vehicle->load('company'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $validated = $request->validate([
            'plate_number' => 'sometimes|string|max:255',
            'vehicle_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'chassis_no' => 'nullable|string|max:255',
            'reg_expiry_date' => 'sometimes|required|date',
            
            'company_id' => 'sometimes|exists:companies,id',
            
            'driver_qid' => 'sometimes|string|size:11|regex:/^[0-9]+$/',
            'driver_name' => 'sometimes|string|max:255',
            'driver_phone' => 'sometimes|string|size:8|regex:/^[0-9]+$/',
            'driver_alt_phone' => 'nullable|string|size:8|regex:/^[0-9]+$/',
            
            'handover_datetime' => 'nullable|date',
            'return_datetime' => 'nullable|date',
            
            'is_active' => 'boolean'
        ]);

        $vehicle->update($validated);

        return response()->json([
            'message' => 'Vehicle updated successfully',
            'data' => $vehicle->load('company')
        ]);
    }

    public function destroy(Vehicle $vehicle)
    {
        $vehicle->delete();
        return response()->json(['message' => 'Vehicle deleted successfully']);
    }
}
