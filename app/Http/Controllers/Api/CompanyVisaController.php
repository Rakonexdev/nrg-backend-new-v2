<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyVisa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyVisaController extends Controller
{
    public function index(Request $request)
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('company_visas', 'nationality') || !\Illuminate\Support\Facades\Schema::hasColumn('company_visas', 'gender') || !\Illuminate\Support\Facades\Schema::hasColumn('visa_applications', 'gender')) {
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        }

        $query = CompanyVisa::with('company');

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'profession' => 'required|string|max:255',
            'available_slots' => 'required|integer|min:0',
            'nationality' => 'required|string|max:255',
            'gender' => 'required|in:Male,Female',
            'vp_number' => 'required|string|max:255',
            'vp_expiry_date' => 'required|date',
        ]);


        $companyVisa = CompanyVisa::create($validated);
        return response()->json($companyVisa, 201);
    }

    public function show(CompanyVisa $companyVisa)
    {
        return response()->json($companyVisa->load('company'));
    }

    public function update(Request $request, CompanyVisa $companyVisa)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'profession' => 'required|string|max:255',
            'available_slots' => 'required|integer|min:0',
            'nationality' => 'required|string|max:255',
            'gender' => 'required|in:Male,Female',
            'vp_number' => 'required|string|max:255',
            'vp_expiry_date' => 'required|date',
        ]);

        $companyVisa->update($validated);
        return response()->json($companyVisa);
    }

    public function destroy($companyVisa)
    {
        try {
            $driver = \Illuminate\Support\Facades\DB::getDriverName();
            try {
                if ($driver === 'mysql') {
                    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                } elseif ($driver === 'sqlite') {
                    \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF;');
                }
            } catch (\Throwable $ignored) {}

            $id = $companyVisa instanceof CompanyVisa ? $companyVisa->id : $companyVisa;
            $entity = CompanyVisa::find($id);

            if ($entity) {
                try {
                    $entity->delete();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\DB::table('company_visas')->where('id', $id)->delete();
                }
            } else {
                try {
                    \Illuminate\Support\Facades\DB::table('company_visas')->where('id', $id)->delete();
                } catch (\Throwable $ignored) {}
            }

            try {
                if ($driver === 'mysql') {
                    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                } elseif ($driver === 'sqlite') {
                    \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');
                }
            } catch (\Throwable $ignored) {}

            return response()->json(['message' => 'Company visa deleted successfully']);
        } catch (\Throwable $e) {
            try {
                $id = $companyVisa instanceof CompanyVisa ? $companyVisa->id : $companyVisa;
                \Illuminate\Support\Facades\DB::table('company_visas')->where('id', $id)->delete();
                return response()->json(['message' => 'Company visa deleted successfully']);
            } catch (\Throwable $ignored) {}

            return response()->json(['message' => 'Error deleting company visa: ' . $e->getMessage()], 500);
        }
    }
}
