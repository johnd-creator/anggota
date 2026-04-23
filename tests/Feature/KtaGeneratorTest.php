<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Services\KtaGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KtaGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_kta_uses_unit_code_not_database_id(): void
    {
        OrganizationUnit::factory()->create(['code' => '099']);
        $unit = OrganizationUnit::factory()->create(['code' => '011']);

        $generated = KtaGenerator::generate($unit->id, 2024);

        $this->assertSame('011-SPPIPS-24001', $generated['kta']);
        $this->assertSame(1, $generated['sequence']);
    }

    public function test_kta_sequence_uses_all_members_in_same_unit_without_year_reset(): void
    {
        $unit = OrganizationUnit::factory()->create(['code' => '011']);

        Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'join_year' => 2024,
            'sequence_number' => 7,
            'kta_number' => '011-SPPIPS-24007',
        ]);

        $generated = KtaGenerator::generate($unit->id, 2025);

        $this->assertSame('011-SPPIPS-25008', $generated['kta']);
        $this->assertSame(8, $generated['sequence']);
    }
}
