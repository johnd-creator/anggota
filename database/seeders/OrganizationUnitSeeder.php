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
        // Existing units - using firstOrCreate for backward compatibility
        $units = [
            ['code' => '001', 'name' => 'Unit Jakarta Pusat', 'organization_type' => 'DPD', 'abbreviation' => '001', 'address' => 'Jl. Merdeka Barat No. 1, Jakarta Pusat'],
            ['code' => '002', 'name' => 'Unit Jakarta Selatan', 'organization_type' => 'DPD', 'abbreviation' => '002', 'address' => 'Jl. Sudirman No. 50, Jakarta Selatan'],
            ['code' => '003', 'name' => 'Unit Surabaya', 'organization_type' => 'DPD', 'abbreviation' => '003', 'address' => 'Jl. Pemuda No. 10, Surabaya'],
            ['code' => '004', 'name' => 'Unit Bandung', 'organization_type' => 'DPD', 'abbreviation' => '004', 'address' => 'Jl. Asia Afrika No. 5, Bandung'],
            ['code' => '005', 'name' => 'Unit Medan', 'organization_type' => 'DPD', 'abbreviation' => '005', 'address' => 'Jl. Diponegoro No. 12, Medan'],
            ['code' => '006', 'name' => 'contoh', 'organization_type' => 'DPD', 'abbreviation' => '006', 'address' => ''],
            ['code' => '007', 'name' => 'Contoh2', 'organization_type' => 'DPD', 'abbreviation' => '007', 'address' => ''],
            ['code' => '008', 'name' => 'contoh3', 'organization_type' => 'DPD', 'abbreviation' => '008', 'address' => ''],
            ['code' => '009', 'name' => 'contoh4', 'organization_type' => 'DPD', 'abbreviation' => '009', 'address' => ''],
            ['code' => '010', 'name' => 'UBP Banten 1 Suralaya', 'organization_type' => 'DPD', 'abbreviation' => '010', 'address' => ''],
        ];

        foreach ($units as $unit) {
            OrganizationUnit::firstOrCreate(
                ['code' => $unit['code']],
                $unit
            );
        }

        // Special "Pusat" unit - using updateOrCreate for idempotency
        OrganizationUnit::updateOrCreate(
            ['code' => 'PST'],
            [
                'name' => 'Pusat',
                'organization_type' => 'DPP',
                'abbreviation' => 'DPP',
                'address' => '',
                'letterhead_name' => 'Serikat Pekerja PT PLN Indonesia Power Services',
                'letterhead_address' => '',
                'letterhead_city' => 'Jakarta',
                'letterhead_postal_code' => '',
                'letterhead_phone' => '',
                'letterhead_email' => '',
                'letterhead_website' => '',
                'letterhead_fax' => '',
                'letterhead_whatsapp' => '',
                'letterhead_footer_text' => '',
                'letterhead_logo_path' => '',
            ]
        );
    }
}
