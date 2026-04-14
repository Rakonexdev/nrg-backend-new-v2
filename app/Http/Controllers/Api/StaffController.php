<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Http\Resources\StaffResource;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $query = Staff::query()->with('documents');

        // Search
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('qid_number', 'like', "%{$search}%")
                  ->orWhere('passport_number', 'like', "%{$search}%")
                  ->orWhere('nationality', 'like', "%{$search}%")
                  ->orWhere('profession', 'like', "%{$search}%");
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
            
            switch($request->get('filter')) {
                case 'expiring_qid':
                    $query->whereBetween('qid_expiry', [$now, $thirtyDays]);
                    break;
                case 'expired_qid':
                    $query->where('qid_expiry', '<', $now);
                    break;
                case 'expiring_passport':
                    $query->whereBetween('passport_expiry', [$now, $thirtyDays]);
                    break;
                case 'expired_passport':
                    $query->where('passport_expiry', '<', $now);
                    break;
            }
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
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
            'mobile' => 'required|string|max:20',
            'date_of_birth' => 'required|date',
            'passport_number' => 'required|string|max:50',
            'passport_expiry' => 'required|date',
            'qid_number' => 'required|string|max:50',
            'qid_expiry' => 'required|date',
            'joining_date' => 'nullable|date',
            'status' => 'string|in:active,inactive',
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
            'mobile' => 'sometimes|required|string|max:20',
            'date_of_birth' => 'sometimes|required|date',
            'passport_number' => 'sometimes|required|string|max:50',
            'passport_expiry' => 'sometimes|required|date',
            'qid_number' => 'sometimes|required|string|max:50',
            'qid_expiry' => 'sometimes|required|date',
            'joining_date' => 'nullable|date',
            'status' => 'string|in:active,inactive',
            'qid_files.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
            'passport_files.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
        ]);

        $staff->update($validated);

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
        $staff = Staff::findOrFail($id);
        // Documents are deleted via cascade in DB, but we should delete files from storage too
        foreach ($staff->documents as $doc) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($doc->file_path);
        }
        $staff->delete();
        return response()->json(['message' => 'Staff and associated documents deleted']);
    }
}
