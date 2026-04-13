<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Spatie roles: super_admin, admin, collector, viewer
        $roles = ['super_admin', 'admin', 'collector', 'viewer'];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $superAdmin = User::firstOrCreate([
            'email' => 'superadmin@nrg.local'
        ], [
            'name' => 'Super Administrator',
            'password' => Hash::make('password'),
            'mobile' => '+97412345678',
            'is_active' => true,
        ]);

        $superAdmin->assignRole('super_admin');
    }
}
