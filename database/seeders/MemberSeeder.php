<?php

namespace Database\Seeders;

use App\Models\Member;
use App\Models\UnionPosition;
use App\Models\OrganizationUnit;
use App\Services\NraGenerator;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $units = OrganizationUnit::take(3)->get();
        if ($units->isEmpty()) {
            $this->call(OrganizationUnitSeeder::class);
            $units = OrganizationUnit::take(3)->get();
        }

        $data = [
            ['full_name' => 'Budi Santoso', 'email' => 'budi@unit.local', 'kta_number' => '001-SPPIPS-24001', 'nip' => 'NIP0001', 'position_name' => 'Ketua', 'unit' => $units[0] ?? null],
            ['full_name' => 'Siti Aminah', 'email' => 'siti@unit.local', 'kta_number' => '002-SPPIPS-24001', 'nip' => 'NIP0002', 'position_name' => 'Sekretaris', 'unit' => $units[1] ?? null],
            ['full_name' => 'Andi Wijaya', 'email' => 'andi@unit.local', 'kta_number' => '003-SPPIPS-24001', 'nip' => 'NIP0003', 'position_name' => 'Bendahara', 'unit' => $units[2] ?? null],
        ];

        foreach ($data as $i => $row) {
            $unit = $row['unit'] ?: OrganizationUnit::first();
            $joinDate = now()->subDays(30 + $i);
            $joinYear = (int) $joinDate->year;
            $gen = NraGenerator::generate((int)$unit->id, $joinYear);

            $posId = optional(UnionPosition::firstOrCreate(['name' => $row['position_name']]))->id;
            Member::firstOrCreate(
                ['email' => $row['email']],
                [
                    'full_name' => $row['full_name'],
                    'email' => $row['email'],
                    'kta_number' => $row['kta_number'],
                    'nip' => $row['nip'],
                    'union_position_id' => $posId,
                    'employment_type' => 'organik',
                    'status' => 'aktif',
                    'join_date' => $joinDate->toDateString(),
                    'organization_unit_id' => $unit->id,
                    'nra' => $gen['nra'],
                    'join_year' => $joinYear,
                    'sequence_number' => $gen['sequence'],
                ]
            );
        }
    }
}
