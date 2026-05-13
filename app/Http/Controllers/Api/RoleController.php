<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleController extends Controller
{
    /**
     * List all roles with their permissions.
     */
    public function index()
    {
        $roles = Role::where('name', '!=', 'super_admin')
            ->where('name', '!=', 'collector')
            ->where('name', '!=', 'viewer')
            ->with('permissions')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                    'users_count' => User::role($role->name)->count(),
                    'created_at' => $role->created_at,
                ];
            });

        return response()->json($roles);
    }

    /**
     * Get all available system permissions grouped by category.
     */
    public function permissions()
    {
        // Clear Spatie's internal cache to ensure we see newly seeded permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = Permission::all()->pluck('name');

        // Group permissions into categories for the UI
        $grouped = [
            'menu' => [
                'label' => 'Menu Visibility',
                'description' => 'Control which side menu items are visible',
                'permissions' => []
            ],
            'staff' => [
                'label' => 'Staff Management',
                'description' => 'Control staff CRUD operations',
                'permissions' => []
            ],
            'companies' => [
                'label' => 'Company Management',
                'description' => 'Control company CRUD operations',
                'permissions' => []
            ],
            'contracts' => [
                'label' => 'Contract Management',
                'description' => 'Control contract CRUD operations',
                'permissions' => []
            ],
            'expenses' => [
                'label' => 'Expense Management',
                'description' => 'Control expense CRUD operations',
                'permissions' => []
            ],
            'settlements' => [
                'label' => 'Settlement Management',
                'description' => 'Control settlement CRUD operations',
                'permissions' => []
            ],
            'collectors' => [
                'label' => 'Collector Management',
                'description' => 'Control collector CRUD operations',
                'permissions' => []
            ],
            'reports' => [
                'label' => 'Report Actions',
                'description' => 'Control actions within reports',
                'permissions' => []
            ],
            'documentation' => [
                'label' => 'Documentation',
                'description' => 'Control documentation uploads and management',
                'permissions' => []
            ],
        ];

        foreach ($permissions as $perm) {
            // Priority 1: Documentation Management
            if (str_contains($perm, 'documentation')) {
                $grouped['documentation']['permissions'][] = $perm;
                continue;
            }

            // Priority 2: Menu Visibility
            if (str_starts_with($perm, 'view_')) {
                $grouped['menu']['permissions'][] = $perm;
                continue;
            }

            // Priority 3: Other modules
            if (str_starts_with($perm, 'staff_')) {
                $grouped['staff']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'company_')) {
                $grouped['companies']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'contract_')) {
                $grouped['contracts']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'expense_')) {
                $grouped['expenses']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'settlement_')) {
                $grouped['settlements']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'collector_')) {
                $grouped['collectors']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'report_')) {
                $grouped['reports']['permissions'][] = $perm;
            }
        }

        return response()->json($grouped);
    }

    /**
     * Create a new role.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // Prevent creating reserved role names
        $reserved = ['super_admin', 'collector', 'viewer'];
        if (in_array(strtolower($request->name), $reserved)) {
            return response()->json(['message' => 'This role name is reserved.'], 422);
        }

        $role = Role::create(['name' => strtolower(str_replace(' ', '_', $request->name)), 'guard_name' => 'web']);

        if ($request->permissions) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json([
            'message' => 'Role created successfully.',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ]
        ], 201);
    }

    /**
     * Update a role's permissions.
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        // Prevent editing super_admin role
        if ($role->name === 'super_admin') {
            return response()->json(['message' => 'Cannot modify super admin role.'], 403);
        }

        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->syncPermissions($request->permissions);

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message' => 'Role permissions updated successfully.',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ]
        ]);
    }

    /**
     * Delete a role (only if no users assigned).
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // Prevent deleting system roles
        $protected = ['super_admin', 'admin', 'collector', 'viewer'];
        if (in_array($role->name, $protected)) {
            return response()->json(['message' => 'Cannot delete system roles.'], 403);
        }

        if (User::role($role->name)->count() > 0) {
            return response()->json(['message' => 'Cannot delete a role that has assigned users. Remove users from this role first.'], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully.']);
    }

    /**
     * List admin users (users with admin-type roles, excluding super_admin).
     */
    public function adminUsers()
    {
        $users = User::whereHas('roles', function ($q) {
            $q->whereNotIn('name', ['super_admin', 'collector']);
        })->with('roles', 'permissions')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'is_active' => $user->is_active,
                'role' => $user->roles->first()?->name,
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'created_at' => $user->created_at,
            ];
        });

        return response()->json($users);
    }

    /**
     * Create a new admin user with a role.
     */
    public function createAdminUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'mobile' => 'nullable|string',
            'role' => 'required|string|exists:roles,name',
        ]);

        // Prevent creating super_admin users
        if ($request->role === 'super_admin') {
            return response()->json(['message' => 'Cannot create super admin users.'], 403);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'mobile' => $request->mobile,
            'is_active' => true,
        ]);

        $user->assignRole($request->role);

        return response()->json([
            'message' => 'Admin user created successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->role,
            ]
        ], 201);
    }

    /**
     * Update an admin user's role.
     */
    public function updateAdminUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Prevent modifying super_admin users
        if ($user->hasRole('super_admin')) {
            return response()->json(['message' => 'Cannot modify super admin users.'], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'mobile' => 'nullable|string',
            'role' => 'sometimes|string|exists:roles,name',
            'is_active' => 'sometimes|boolean',
        ]);

        $user->update($request->only(['name', 'email', 'mobile', 'is_active']));

        if ($request->has('role') && $request->role !== 'super_admin') {
            $user->syncRoles([$request->role]);
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message' => 'Admin user updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->roles->first()?->name,
            ]
        ]);
    }

    /**
     * Delete an admin user.
     */
    public function deleteAdminUser($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting super_admin users
        if ($user->hasRole('super_admin')) {
            return response()->json(['message' => 'Cannot delete super admin users.'], 403);
        }

        // Prevent deleting self
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Admin user deleted successfully.']);
    }
}
