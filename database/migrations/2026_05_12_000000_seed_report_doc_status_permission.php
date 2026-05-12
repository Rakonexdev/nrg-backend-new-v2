<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ensure the permission exists
        $permission = Permission::firstOrCreate([
            'name' => 'report_doc_status_edit',
            'guard_name' => 'web'
        ]);

        // Assign to super_admin and admin roles
        $roles = Role::whereIn('name', ['super_admin', 'admin'])->get();
        foreach ($roles as $role) {
            $role->givePermissionTo($permission);
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', 'report_doc_status_edit')->delete();
    }
};
