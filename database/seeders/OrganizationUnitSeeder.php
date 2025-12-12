<?php

namespace Database\Seeders;

use App\Models\OrganizationUnit;
use Illuminate\Database\Seeder;

class OrganizationUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            ['code' => '001', 'name' => 'Unit Jakarta Pusat', 'address' => 'Jl. Merdeka Barat No. 1, Jakarta Pusat'],
            ['code' => '002', 'name' => 'Unit Jakarta Selatan', 'address' => 'Jl. Sudirman No. 50, Jakarta Selatan'],
            ['code' => '003', 'name' => 'Unit Surabaya', 'address' => 'Jl. Pemuda No. 10, Surabaya'],
            ['code' => '004', 'name' => 'Unit Bandung', 'address' => 'Jl. Asia Afrika No. 5, Bandung'],
            ['code' => '005', 'name' => 'Unit Medan', 'address' => 'Jl. Diponegoro No. 12, Medan'],
            ['code' => '006', 'name' => 'contoh', 'address' => ''],
            ['code' => '007', 'name' => 'Contoh2', 'address' => ''],
            ['code' => '008', 'name' => 'contoh3', 'address' => ''],
            ['code' => '009', 'name' => 'contoh4', 'address' => ''],
            ['code' => '010', 'name' => 'UBP Banten 1 Suralaya', 'address' => ''],
        ];

        foreach ($units as $unit) {
            OrganizationUnit::firstOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }
    }
}
