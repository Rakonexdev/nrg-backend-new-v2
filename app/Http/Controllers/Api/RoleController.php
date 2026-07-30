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

        $permissions = Permission::all()->pluck('name')->unique()->values();

        // Group permissions into categories for the UI
        $grouped = [
            'menu' => [
                'label' => 'Menu Visibility',
                'description' => 'Control which side menu items are visible',
                'permissions' => []
            ],
            'dashboard' => [
                'label' => 'Dashboard Visibility',
                'description' => 'Control which dashboard cards are visible',
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
            'income_cards' => [
                'label' => 'Income Report Cards',
                'description' => 'Control visibility of summary cards in Income & Expenditure report',
                'permissions' => []
            ],
            'documentation' => [
                'label' => 'Documentation',
                'description' => 'Control documentation uploads and management',
                'permissions' => []
            ],
            'vehicles' => [
                'label' => 'Vehicle Management',
                'description' => 'Control vehicle CRUD operations',
                'permissions' => []
            ],
            'visa_applications' => [
                'label' => 'Visa Applications',
                'description' => 'Control visa application CRUD operations',
                'permissions' => []
            ],
            'bank_details' => [
                'label' => 'Bank Details',
                'description' => 'Control bank details CRUD operations',
                'permissions' => []
            ],
            'company_visas' => [
                'label' => 'Company Visas',
                'description' => 'Control company visa CRUD operations',
                'permissions' => []
            ],
            'sponsorship_changes' => [
                'label' => 'Sponsorship Changes',
                'description' => 'Control sponsorship change CRUD operations',
                'permissions' => []
            ],
            'employee_list_moi' => [
                'label' => 'Employee List MOI',
                'description' => 'Control Employee List MOI CRUD & download operations',
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

            // Dashboard Cards
            if (str_starts_with($perm, 'dashboard_')) {
                $grouped['dashboard']['permissions'][] = $perm;
                continue;
            }

            // Priority 3: Other modules
            if (str_starts_with($perm, 'staff_')) {
                $grouped['staff']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'company_visa_')) {
                $grouped['company_visas']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'company_')) {
                $grouped['companies']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'contract_')) {
                $grouped['contracts']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'expense_')) {
                $grouped['expenses']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'collector_')) {
                $grouped['collectors']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'report_')) {
                $grouped['reports']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'income_card_')) {
                $grouped['income_cards']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'vehicle_')) {
                $grouped['vehicles']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'visa_application_')) {
                $grouped['visa_applications']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'bank_detail_')) {
                $grouped['bank_details']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'sponsorship_change_')) {
                $grouped['sponsorship_changes']['permissions'][] = $perm;
            } elseif (str_starts_with($perm, 'employee_list_moi_')) {
                $grouped['employee_list_moi']['permissions'][] = $perm;
            }
        }

        return response()->json($grouped);
    }

    /**
     * Create a new role.
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:50|unique:roles,name',
                'permissions' => 'nullable|array',
                'permissions.*' => 'string',
            ]);

            // Prevent creating reserved role names
            $reserved = ['super_admin', 'collector', 'viewer'];
            if (in_array(strtolower($request->name), $reserved)) {
                return response()->json(['message' => 'This role name is reserved.'], 422);
            }

            $role = Role::create(['name' => strtolower(str_replace(' ', '_', $request->name)), 'guard_name' => 'web']);

            if ($request->permissions && is_array($request->permissions)) {
                if (\Illuminate\Support\Facades\Schema::hasTable('permissions')) {
                    foreach ($request->permissions as $permName) {
                        if (!empty($permName)) {
                            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
                        }
                    }
                }
                $role->syncPermissions($request->permissions);
            }

            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            return response()->json([
                'message' => 'Role created successfully.',
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation error: ' . implode(', ', \Illuminate\Support\Arr::flatten($e->errors()))], 422);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create role', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create role: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update a role's permissions.
     */
    public function update(Request $request, $id)
    {
        try {
            $role = Role::find($id);
            if (!$role) {
                return response()->json(['message' => 'Role not found.'], 404);
            }

            // Prevent editing super_admin role
            if ($role->name === 'super_admin') {
                return response()->json(['message' => 'Cannot modify super admin role.'], 403);
            }

            $request->validate([
                'permissions' => 'required|array',
                'permissions.*' => 'string',
            ]);

            // Self-healing: Ensure all submitted permissions exist in DB permissions table
            if (\Illuminate\Support\Facades\Schema::hasTable('permissions')) {
                foreach ($request->permissions as $permName) {
                    if (!empty($permName)) {
                        Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'web']);
                    }
                }
            }

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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => 'Validation error: ' . implode(', ', \Illuminate\Support\Arr::flatten($e->errors()))], 422);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update role permissions', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to save role: ' . $e->getMessage()], 500);
        }
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
                'allowed_login_shifts' => $user->allowed_login_shifts,
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
            'allowed_login_shifts' => 'nullable|array',
        ]);

        // Prevent creating super_admin users
        if ($request->role === 'super_admin') {
            return response()->json(['message' => 'Cannot create super admin users.'], 403);
        }

        $formattedShifts = [];
        if (!empty($request->allowed_login_shifts) && is_array($request->allowed_login_shifts)) {
            foreach ($request->allowed_login_shifts as $shift) {
                if (empty($shift['start']) || empty($shift['end'])) continue;
                $start = date('H:i', strtotime($shift['start']));
                $end = date('H:i', strtotime($shift['end']));
                $formattedShifts[] = ['start' => $start, 'end' => $end];
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'mobile' => $request->mobile,
            'is_active' => true,
            'allowed_login_shifts' => $formattedShifts,
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
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'password' => 'sometimes|nullable|string|min:8',
            'mobile' => 'nullable|string',
            'role' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
            'allowed_login_shifts' => 'nullable|array',
        ]);

        $updateData = $request->only(['name', 'email', 'mobile']);

        if ($request->has('allowed_login_shifts')) {
            $formattedShifts = [];
            if (is_array($request->allowed_login_shifts)) {
                foreach ($request->allowed_login_shifts as $shift) {
                    if (empty($shift['start']) || empty($shift['end'])) continue;
                    $start = date('H:i', strtotime($shift['start']));
                    $end = date('H:i', strtotime($shift['end']));
                    $formattedShifts[] = ['start' => $start, 'end' => $end];
                }
            }
            $updateData['allowed_login_shifts'] = $formattedShifts;
        }
        
        if ($request->has('is_active')) {
            $updateData['is_active'] = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
        }
        
        if ($request->filled('password')) {
            $updateData['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
        }

        $user->update($updateData);

        if ($request->filled('role') && $request->role !== 'super_admin') {
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
