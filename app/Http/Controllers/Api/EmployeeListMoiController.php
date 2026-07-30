<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeListMoi;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EmployeeListMoiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:view_employee_list_moi', only: ['index', 'show']),
            new Middleware('can:employee_list_moi_create', only: ['store']),
            new Middleware('can:employee_list_moi_edit', only: ['update']),
            new Middleware('can:employee_list_moi_delete', only: ['destroy']),
            new Middleware('can:employee_list_moi_download', only: ['download']),
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = EmployeeListMoi::query();

            // Search filter
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('document_name', 'like', "%{$search}%")
                      ->orWhere('computer_card_number', 'like', "%{$search}%")
                      ->orWhere('company_name', 'like', "%{$search}%")
                      ->orWhere('salary_month', 'like', "%{$search}%");
                });
            }

            // Company filter
            if ($request->filled('company_name')) {
                $query->where('company_name', $request->company_name);
            }

            if ($request->filled('company_id')) {
                $query->where('company_id', $request->company_id);
            }

            // Salary month filter
            if ($request->filled('salary_month')) {
                $query->where('salary_month', $request->salary_month);
            }

            $perPage = $request->input('per_page', 15);

            return $query->with(['uploader:id,name', 'company:id,name,computer_card'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('Error fetching Employee List MOI records: ' . $e->getMessage());
            return response()->json(['message' => 'Error loading records: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'document_name' => 'required|string|max:255',
                'computer_card_number' => 'required|string|max:255',
                'company_name' => 'required|string|max:255',
                'company_id' => 'nullable|exists:companies,id',
                'upload_date' => 'required|date',
                'salary_month' => 'required|string|max:255',
                'file' => 'required|file|max:10240', // Max 10MB
            ]);

            $companyName = $request->company_name;
            if ($request->filled('company_id')) {
                $company = Company::find($request->company_id);
                if ($company) {
                    $companyName = $companyName ?: $company->name;
                    if (empty($request->computer_card_number) && !empty($company->computer_card)) {
                        $request->merge(['computer_card_number' => $company->computer_card]);
                    }
                }
            }

            $file = $request->file('file');
            $path = $file->store('employee_list_moi', 'public');

            $record = EmployeeListMoi::create([
                'document_name' => $request->document_name,
                'computer_card_number' => $request->computer_card_number,
                'company_name' => $companyName,
                'company_id' => $request->company_id,
                'upload_date' => $request->upload_date ?: now()->toDateString(),
                'salary_month' => $request->salary_month,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()?->id
            ]);

            return response()->json($record->load(['uploader:id,name', 'company:id,name,computer_card']), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error creating Employee List MOI: ' . $e->getMessage());
            return response()->json(['message' => 'Error saving record: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $record = EmployeeListMoi::with(['uploader:id,name', 'company:id,name,computer_card'])->findOrFail($id);
        return response()->json($record);
    }

    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'document_name' => 'required|string|max:255',
                'computer_card_number' => 'required|string|max:255',
                'company_name' => 'required|string|max:255',
                'company_id' => 'nullable|exists:companies,id',
                'upload_date' => 'required|date',
                'salary_month' => 'required|string|max:255',
                'file' => 'nullable|file|max:10240',
            ]);

            $record = EmployeeListMoi::findOrFail($id);

            $companyName = $request->company_name;
            if ($request->filled('company_id')) {
                $company = Company::find($request->company_id);
                if ($company && empty($companyName)) {
                    $companyName = $company->name;
                }
            }

            $data = [
                'document_name' => $request->document_name,
                'computer_card_number' => $request->computer_card_number,
                'company_name' => $companyName,
                'company_id' => $request->company_id,
                'upload_date' => $request->upload_date ?: $record->upload_date,
                'salary_month' => $request->salary_month,
            ];

            if ($request->hasFile('file')) {
                if ($record->file_path && Storage::disk('public')->exists($record->file_path)) {
                    Storage::disk('public')->delete($record->file_path);
                }

                $file = $request->file('file');
                $path = $file->store('employee_list_moi', 'public');

                $data['file_path'] = $path;
                $data['file_name'] = $file->getClientOriginalName();
                $data['file_type'] = $file->getClientMimeType();
                $data['file_size'] = $file->getSize();
            }

            $record->update($data);

            return response()->json([
                'message' => 'Record updated successfully',
                'record' => $record->load(['uploader:id,name', 'company:id,name,computer_card'])
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error updating Employee List MOI: ' . $e->getMessage());
            return response()->json(['message' => 'Error updating record: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $driver = DB::getDriverName();
            try {
                if ($driver === 'mysql') {
                    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
                } elseif ($driver === 'sqlite') {
                    DB::statement('PRAGMA foreign_keys = OFF;');
                }
            } catch (\Throwable $ignored) {}

            $record = $id instanceof EmployeeListMoi ? $id : EmployeeListMoi::find($id);

            if ($record) {
                try {
                    if (!empty($record->file_path) && Storage::disk('public')->exists($record->file_path)) {
                        Storage::disk('public')->delete($record->file_path);
                    }
                } catch (\Throwable $ignored) {}

                try {
                    $record->delete();
                } catch (\Throwable $e) {
                    DB::table('employee_list_mois')->where('id', $record->id)->delete();
                }
            } else {
                try {
                    DB::table('employee_list_mois')->where('id', $id)->delete();
                } catch (\Throwable $ignored) {}
            }

            try {
                if ($driver === 'mysql') {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                } elseif ($driver === 'sqlite') {
                    DB::statement('PRAGMA foreign_keys = ON;');
                }
            } catch (\Throwable $ignored) {}

            return response()->json(['message' => 'Record deleted successfully']);
        } catch (\Throwable $e) {
            Log::error('Failed to delete Employee List MOI', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error deleting record: ' . $e->getMessage()], 500);
        }
    }

    public function download($id)
    {
        $record = EmployeeListMoi::findOrFail($id);

        if (!$record->file_path || !Storage::disk('public')->exists($record->file_path)) {
            return response()->json(['message' => 'File not found on server'], 404);
        }

        return Storage::disk('public')->download($record->file_path, $record->file_name);
    }
}
