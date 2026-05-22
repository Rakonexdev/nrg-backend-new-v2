<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CollectorController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view_collectors', only: ['index', 'show']),
            new Middleware('permission:collector_create', only: ['store']),
            new Middleware('permission:collector_edit', only: ['update']),
            new Middleware('permission:collector_delete', only: ['destroy']),
        ];
    }
    public function index(Request $request)
    {
        $query = User::role('collector');

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('mobile', 'like', "%{$search}%");
            });
        }

        $sortColumn = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $allowedSortColumns = ['name', 'email', 'mobile', 'created_at'];

        if (in_array($sortColumn, $allowedSortColumns)) {
            $query->orderBy($sortColumn, $sortDirection);
        }

        $perPage = $request->get('per_page', 15);
        return $query->paginate($perPage);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', 'min:8'],
            'mobile' => 'required|string|max:20',
        ]);

        $validated['raw_password'] = $validated['password'];
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;

        $user = User::create($validated);
        $user->assignRole('collector');

        return response()->json($user, 201);
    }

    public function show($id)
    {
        $user = User::role('collector')->findOrFail($id);
        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::role('collector')->findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,'.$id,
            'mobile' => 'sometimes|required|string|max:20',
            'old_password' => 'nullable|string',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (!empty($validated['password'])) {
            if (!Hash::check($request->old_password, $user->password)) {
                return response()->json(['message' => 'Old password does not match.'], 400);
            }
            $validated['raw_password'] = $validated['password'];
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }
        unset($validated['old_password']);

        $user->update($validated);
        return response()->json($user);
    }

    public function destroy($id)
    {
        $user = User::role('collector')->findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'Collector deleted']);
    }
}
