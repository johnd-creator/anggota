<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FinanceCategory;
use App\Models\User;

class DefaultFinanceCategorySeeder extends Seeder
{
    public function run(): void
    {
        // Find a super_admin user for created_by
        $superAdmin = User::whereHas('role', function ($q) {
            $q->where('name', 'super_admin');
        })->first();

        $createdBy = $superAdmin?->id ?? 1;

        // Create default "Iuran Anggota" category if not exists
        FinanceCategory::firstOrCreate(
            [
                'name' => 'Iuran Anggota',
                'type' => 'income',
                'organization_unit_id' => null, // Global category
            ],
            [
                'description' => 'Iuran bulanan anggota organisasi',
                'is_recurring' => true,
                'default_amount' => 30000,
                'is_system' => true,
                'created_by' => $createdBy,
            ]
        );
    }
}
