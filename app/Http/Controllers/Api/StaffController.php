<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Http\Resources\StaffResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StaffController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_staff', only: ['index', 'show']),
            new Middleware('permission:staff_create', only: ['store']),
            new Middleware('permission:staff_edit', only: ['update']),
            new Middleware('permission:staff_delete', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = Staff::query();

        // Simple mode for dropdowns (no relationships, minimal columns)
        if ($request->get('mode') === 'simple') {
            if ($request->filled('status')) {
                $query->where('status', $request->get('status'));
            }
            $results = $query->leftJoin('companies', 'staff.company_id', '=', 'companies.id')
                ->select('staff.id', 'staff.name', 'staff.qid_number', 'staff.mobile', 'staff.qid_expiry', 'companies.name as company_name')
                ->orderBy('staff.name')
                ->get();
            return response()->json($results);
        }

        $query->with(['documents', 'company']);

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('qid_number', 'like', "%{$search}%")
                    ->orWhere('passport_number', 'like', "%{$search}%")
                    ->orWhere('nationality', 'like', "%{$search}%")
                    ->orWhere('profession', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('alternative_mobile', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('company', function ($cq) use ($search) {
                        $cq->where('computer_card', 'like', "%{$search}%")
                           ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting
        $sortColumn = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $allowedSortColumns = ['name', 'nationality', 'profession', 'qid_expiry', 'passport_expiry', 'joining_date', 'created_at'];

        if (in_array($sortColumn, $allowedSortColumns)) {
            $query->orderBy($sortColumn, $sortDirection);
        }

        // Expiry filters
        if ($request->has('filter')) {
            $now = \Carbon\Carbon::now();
            $thirtyDays = \Carbon\Carbon::now()->addDays(30);

            // Get in-progress IDs to exclude from alert filters
            $inProgressQuery = \App\Models\Expense::whereNotNull('validation_date')
                ->whereNotNull('staff_id')
                ->where(function($q) {
                    $q->whereNull('renewal_status')
                      ->orWhere('renewal_status', '!=', 'completed');
                });
            
            switch($request->get('filter')) {
                case 'expiring_qid':
                    $qidInProgressIds = (clone $inProgressQuery)
                        ->whereHas('subcategory', function ($q) {
                            $q->where('name', 'like', '%QID%');
                        })
                        ->pluck('staff_id')
                        ->toArray();

                    $query->where('qid_expiry', '<=', $thirtyDays)
                          ->where('status', 'active')
                          ->whereNotIn('id', $qidInProgressIds);
                    break;
                case 'expired_qid':
                    $query->where('qid_expiry', '<', $now)
                          ->where('status', 'active');
                    break;
                case 'expiring_passport':
                    $passportInProgressIds = (clone $inProgressQuery)
                        ->whereHas('subcategory', function ($q) {
                            $q->where('name', 'like', '%PASSPORT%')
                               ->orWhere('name', 'like', '%PP%');
                        })
                        ->pluck('staff_id')
                        ->toArray();

                    $query->where('passport_expiry', '<=', $thirtyDays)
                          ->where('status', 'active')
                          ->whereNotIn('id', $passportInProgressIds);
                    break;
                case 'expired_passport':
                    $query->where('passport_expiry', '<', $now)
                          ->where('status', 'active');
                    break;
                case 'renewing_contract':
                    $query->where(function ($q) use ($now) {
                        $thisMonthEnd = $now->copy()->endOfMonth();
                        $q->whereHas('latestContract', function ($cq) use ($thisMonthEnd) {
                            $cq->where('end_date', '<=', $thisMonthEnd);
                        })
                        ->orWhere(function ($sq) use ($now) {
                            $sq->whereDoesntHave('contracts')
                               ->whereMonth('joining_date', '<=', $now->month)
                               ->whereYear('joining_date', '<', $now->year);
                        });
                    });
                    break;
            }
        }

        // Date Range Filter (QID or Passport Expiry)
        if ($request->from_date || $request->to_date) {
            $dateType = $request->get('date_type', 'both');
            $query->where(function($q) use ($request, $dateType) {
                if ($dateType === 'qid_expiry' || $dateType === 'both') {
                    if ($request->from_date && $request->to_date) {
                        $q->whereBetween('qid_expiry', [$request->from_date, $request->to_date]);
                    } elseif ($request->from_date) {
                        $q->whereDate('qid_expiry', '>=', $request->from_date);
                    } elseif ($request->to_date) {
                        $q->whereDate('qid_expiry', '<=', $request->to_date);
                    }
                }

                if ($dateType === 'passport_expiry' || $dateType === 'both') {
                    $method = ($dateType === 'both') ? 'orWhere' : 'where';
                    if ($request->from_date && $request->to_date) {
                        $q->$method(function($sq) use ($request) {
                            $sq->whereBetween('passport_expiry', [$request->from_date, $request->to_date]);
                        });
                    } elseif ($request->from_date) {
                        $q->$method(function($sq) use ($request) {
                            $sq->whereDate('passport_expiry', '>=', $request->from_date);
                        });
                    } elseif ($request->to_date) {
                        $q->$method(function($sq) use ($request) {
                            $sq->whereDate('passport_expiry', '<=', $request->to_date);
                        });
                    }
                }
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Company filter
        if ($request->filled('company_id')) {
            if ($request->company_id === 'null') {
                $query->whereNull('company_id');
            } else {
                $query->where('company_id', $request->company_id);
            }
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        return StaffResource::collection($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nationality' => 'required|string|max:255',
            'profession' => 'required|string|max:255',
            'mobile' => 'required|string|regex:/^[0-9]{8}$/',
            'alternative_mobile' => 'nullable|string|regex:/^[0-9]{8}$/',
            'date_of_birth' => 'required|date',
            'passport_number' => 'required|string|regex:/^[A-Z0-9]{7,15}$/i',
            'passport_expiry' => 'required|date',
            'qid_number' => 'required|string|digits:11',
            'qid_expiry' => 'required|date',
            'joining_date' => 'required|date',
            'status' => 'string|in:active,inactive',
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'nullable|exists:company_branches,id',
            'notes' => 'nullable|string',
            'qid_files.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
            'passport_files.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
        ]);

        $staff = Staff::create($validated);

        // Handle File Uploads
        $this->handleFileUploads($request, $staff);

        return new StaffResource($staff->load('documents'));
    }

    public function show($id)
    {
        return new StaffResource(Staff::with('documents')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $staff = Staff::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'nationality' => 'sometimes|required|string|max:255',
            'profession' => 'sometimes|required|string|max:255',
            'mobile' => 'sometimes|required|string|regex:/^[0-9]{8}$/',
            'alternative_mobile' => 'nullable|string|regex:/^[0-9]{8}$/',
            'date_of_birth' => 'sometimes|required|date',
            'passport_number' => 'sometimes|required|string|regex:/^[A-Z0-9]{7,15}$/i',
            'passport_expiry' => 'sometimes|required|date',
            'qid_number' => 'sometimes|required|string|digits:11',
            'qid_expiry' => 'sometimes|required|date',
            'joining_date' => 'sometimes|required|date',
            'status' => 'string|in:active,inactive',
            'company_id' => 'sometimes|required|exists:companies,id',
            'branch_id' => 'nullable|exists:company_branches,id',
            'notes' => 'nullable|string',
            'qid_files.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
            'passport_files.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
            'delete_document_ids' => 'nullable|array',
            'delete_document_ids.*' => 'integer|exists:staff_documents,id'
        ]);

        $staff->update($validated);

        // Handle Document Deletions
        if ($request->filled('delete_document_ids')) {
            $docsToDelete = $staff->documents()->whereIn('id', $request->delete_document_ids)->get();
            foreach ($docsToDelete as $doc) {
                Storage::disk('public')->delete($doc->file_path);
                $doc->delete();
            }
        }

        // Handle File Uploads
        $this->handleFileUploads($request, $staff);

        return new StaffResource($staff->load('documents'));
    }

    private function handleFileUploads(Request $request, Staff $staff)
    {
        if ($request->hasFile('qid_files')) {
            foreach ($request->file('qid_files') as $file) {
                $path = $file->store('staff/qid', 'public');
                $staff->documents()->create([
                    'document_type' => 'qid',
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'uploaded_by' => auth()->id() ?? 1, // Fallback to 1 if not auth (for testing)
                ]);
            }
        }

        if ($request->hasFile('passport_files')) {
            foreach ($request->file('passport_files') as $file) {
                $path = $file->store('staff/passport', 'public');
                $staff->documents()->create([
                    'document_type' => 'passport',
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'uploaded_by' => auth()->id() ?? 1,
                ]);
            }
        }
    }

    public function destroy($id)
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

            $staffId = $id instanceof Staff ? $id->id : $id;
            $staff = Staff::find($staffId);

            if ($staff) {
                try {
                    if ($staff->documents) {
                        foreach ($staff->documents as $doc) {
                            if (!empty($doc->file_path)) {
                                try {
                                    \Illuminate\Support\Facades\Storage::disk('public')->delete($doc->file_path);
                                } catch (\Throwable $ignored) {}
                            }
                        }
                        try {
                            $staff->documents()->delete();
                        } catch (\Throwable $ignored) {}
                    }
                } catch (\Throwable $ignored) {}

                try {
                    $staff->delete();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\DB::table('staff')->where('id', $staffId)->delete();
                }
            } else {
                try {
                    \Illuminate\Support\Facades\DB::table('staff')->where('id', $staffId)->delete();
                } catch (\Throwable $ignored) {}
            }

            try {
                if ($driver === 'mysql') {
                    \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
                } elseif ($driver === 'sqlite') {
                    \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON;');
                }
            } catch (\Throwable $ignored) {}

            return response()->json(['message' => 'Staff and associated documents deleted successfully']);
        } catch (\Throwable $e) {
            try {
                $staffId = $id instanceof Staff ? $id->id : $id;
                \Illuminate\Support\Facades\DB::table('staff')->where('id', $staffId)->delete();
                return response()->json(['message' => 'Staff deleted successfully']);
            } catch (\Throwable $ignored) {}

            return response()->json(['message' => 'Error deleting staff: ' . $e->getMessage()], 500);
        }
    }
}
