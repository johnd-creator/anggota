<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Services\NraGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NraGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_nra_is_unique_and_sequences_increment()
    {
        $this->seed(\Database\Seeders\OrganizationUnitSeeder::class);
        $unit = OrganizationUnit::first();
        $year = (int) now()->year;

        $g1 = NraGenerator::generate($unit->id, $year);
        Member::create([
            'full_name' => 'Seed 1',
            'email' => 'seed1@example.com',
            'employment_type' => 'organik',
            'status' => 'aktif',
            'join_date' => now()->toDateString(),
            'organization_unit_id' => $unit->id,
            'nra' => $g1['nra'],
            'join_year' => $year,
            'sequence_number' => $g1['sequence'],
        ]);

        $g2 = NraGenerator::generate($unit->id, $year);

        $this->assertNotEquals($g1['nra'], $g2['nra']);
        $this->assertEquals($g1['sequence'] + 1, $g2['sequence']);

        Member::create([
            'full_name' => 'Test 1',
            'email' => 't1@example.com',
            'employment_type' => 'organik',
            'status' => 'aktif',
            'join_date' => now()->toDateString(),
            'organization_unit_id' => $unit->id,
            'nra' => $g2['nra'],
            'join_year' => $year,
            'sequence_number' => $g2['sequence'],
        ]);

        $g3 = NraGenerator::generate($unit->id, $year);

        Member::create([
            'full_name' => 'Test 2',
            'email' => 't2@example.com',
            'employment_type' => 'organik',
            'status' => 'aktif',
            'join_date' => now()->toDateString(),
            'organization_unit_id' => $unit->id,
            'nra' => $g3['nra'],
            'join_year' => $year,
            'sequence_number' => $g3['sequence'],
        ]);

        $this->assertEquals($g2['sequence'] + 1, $g3['sequence']);
    }
}
