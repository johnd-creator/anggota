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

        // Gunakan kredensial dari ENV agar bisa diganti di prod tanpa ubah kode
        $email = env('SUPERADMIN_EMAIL', 'superadmin@example.com');
        $password = env('SUPERADMIN_PASSWORD', 'password');
        $name = env('SUPERADMIN_NAME', 'Super Admin');

        if ($superAdminRole) {
            \App\Models\User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'email_verified_at' => now(),
                    'password' => \Illuminate\Support\Facades\Hash::make($password),
                    'remember_token' => \Illuminate\Support\Str::random(10),
                    'role_id' => $superAdminRole->id,
                ]
            );
        }
    }
}
