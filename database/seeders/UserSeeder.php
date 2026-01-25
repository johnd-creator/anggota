<?php

namespace Database\Seeders;

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
        // Default ke superadmin@waspro.com sesuai setup baru
        $email = env('SUPERADMIN_EMAIL', 'superadmin@waspro.com');
        $password = env('SUPERADMIN_PASSWORD', 'password123');
        $name = env('SUPERADMIN_NAME', 'Super Admin');

        if ($superAdminRole) {
            $user = \App\Models\User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'email_verified_at' => now(),
                    'password' => \Illuminate\Support\Facades\Hash::make($password),
                    'remember_token' => \Illuminate\Support\Str::random(10),
                    'role_id' => $superAdminRole->id,
                ]
            );

            // Update role if user exists and has wrong role
            if ($user && $user->role_id !== $superAdminRole->id) {
                $user->role_id = $superAdminRole->id;
                $user->save();
            }
        }
    }
}
