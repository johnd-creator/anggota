<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdminRole = \App\Models\Role::where('name', 'super_admin')->first();
        $adminUnitRole = \App\Models\Role::where('name', 'admin_unit')->first();

        \App\Models\User::updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'email_verified_at' => now(),
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'remember_token' => \Illuminate\Support\Str::random(10),
                'role_id' => $superAdminRole->id,
            ]
        );

        \App\Models\User::updateOrCreate(
            ['email' => 'adminunit@example.com'],
            [
                'name' => 'Admin Unit',
                'email_verified_at' => now(),
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'remember_token' => \Illuminate\Support\Str::random(10),
                'role_id' => $adminUnitRole->id,
                'organization_unit_id' => \App\Models\OrganizationUnit::first()?->id,
            ]
        );
    }
}
