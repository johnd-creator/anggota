<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\OrganizationUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FixMemberKtaPrefixesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dry_run_reports_without_updating_kta(): void
    {
        $unit = OrganizationUnit::factory()->create(['code' => '011']);
        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'join_year' => 2024,
            'sequence_number' => 1,
            'kta_number' => '012-SPPIPS-24001',
        ]);

        Artisan::call('members:fix-kta-prefix', ['--member-id' => [$member->id]]);

        $this->assertStringContainsString('WOULD FIX', Artisan::output());
        $this->assertSame('012-SPPIPS-24001', $member->fresh()->kta_number);
    }

    public function test_command_apply_updates_mismatched_kta_prefix(): void
    {
        $unit = OrganizationUnit::factory()->create(['code' => '011']);
        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'join_year' => 2024,
            'sequence_number' => 1,
            'kta_number' => '012-SPPIPS-24001',
        ]);

        Artisan::call('members:fix-kta-prefix', [
            '--apply' => true,
            '--member-id' => [$member->id],
        ]);

        $this->assertSame('011-SPPIPS-24001', $member->fresh()->kta_number);
    }

    public function test_command_skips_when_target_kta_already_exists(): void
    {
        $unit = OrganizationUnit::factory()->create(['code' => '011']);
        Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'join_year' => 2024,
            'sequence_number' => 1,
            'kta_number' => '011-SPPIPS-24001',
        ]);
        $conflicting = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'join_year' => 2024,
            'sequence_number' => 1,
            'kta_number' => '012-SPPIPS-24001',
        ]);

        Artisan::call('members:fix-kta-prefix', [
            '--apply' => true,
            '--member-id' => [$conflicting->id],
        ]);

        $this->assertStringContainsString('sudah dipakai', Artisan::output());
        $this->assertSame('012-SPPIPS-24001', $conflicting->fresh()->kta_number);
    }

    public function test_command_can_resequence_members_per_organization_without_year_reset(): void
    {
        $unit = OrganizationUnit::factory()->create(['code' => '011']);
        $first = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'join_year' => 2024,
            'sequence_number' => 1,
            'kta_number' => '011-SPPIPS-24001',
        ]);
        $second = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'join_year' => 2025,
            'sequence_number' => 1,
            'kta_number' => '011-SPPIPS-25001',
        ]);

        Artisan::call('members:fix-kta-prefix', [
            '--apply' => true,
            '--resequence' => true,
        ]);

        $this->assertSame(1, $first->fresh()->sequence_number);
        $this->assertSame('011-SPPIPS-24001', $first->fresh()->kta_number);
        $this->assertSame(2, $second->fresh()->sequence_number);
        $this->assertSame('011-SPPIPS-25002', $second->fresh()->kta_number);
    }
}
