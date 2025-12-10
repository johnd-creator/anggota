<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin']);
        \App\Models\Role::firstOrCreate(['name' => 'admin_pusat'], ['label' => 'Admin Pusat']);
        \App\Models\Role::firstOrCreate(['name' => 'admin_unit'], ['label' => 'Admin Unit']);
        \App\Models\Role::firstOrCreate(['name' => 'anggota'], ['label' => 'Anggota']);
        \App\Models\Role::firstOrCreate(['name' => 'reguler'], ['label' => 'Reguler']);
        \App\Models\Role::firstOrCreate(['name' => 'bendahara'], ['label' => 'Bendahara']);
    }
}
