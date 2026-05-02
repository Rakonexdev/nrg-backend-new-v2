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
        // 1. Seed Roles and Permissions first
        $this->call(PermissionSeeder::class);

        // 2. Create Super Admin
        $superAdmin = User::firstOrCreate([
            'email' => 'superadmin@nrg.local'
        ], [
            'name' => 'Super Administrator',
            'password' => Hash::make('password'),
            'mobile' => '+97412345678',
            'is_active' => true,
        ]);
        $superAdmin->assignRole('super_admin');

        // 3. Create Default Admin
        $admin = User::firstOrCreate([
            'email' => 'admin@nrg.local'
        ], [
            'name' => 'NRG Administrator',
            'password' => Hash::make('password'),
            'mobile' => '+97488888888',
            'is_active' => true,
        ]);
        $admin->assignRole('admin');
    }
}
