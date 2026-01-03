<?php

namespace Tests\Feature;

use App\Models\DuesPayment;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuesGenerateCommandTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'anggota', 'label' => 'Anggota']);
        $this->unit = OrganizationUnit::factory()->create();
    }

    protected function createActiveMembers(int $count): void
    {
        Member::factory()->count($count)->create([
            'status' => 'aktif',
            'organization_unit_id' => $this->unit->id,
        ]);
    }

    public function test_generates_dues_for_active_members(): void
    {
        $this->createActiveMembers(3);

        $this->artisan('dues:generate', ['--period' => '2026-01'])
            ->assertSuccessful();

        $this->assertDatabaseCount('dues_payments', 3);
        $this->assertDatabaseHas('dues_payments', [
            'period' => '2026-01',
            'status' => 'unpaid',
            'amount' => 30000,
        ]);
    }

    public function test_idempotent_no_duplicates(): void
    {
        $this->createActiveMembers(3);

        // Run twice
        $this->artisan('dues:generate', ['--period' => '2026-01'])->assertSuccessful();
        $this->artisan('dues:generate', ['--period' => '2026-01'])->assertSuccessful();

        // Still only 3 records
        $this->assertDatabaseCount('dues_payments', 3);
    }

    public function test_does_not_overwrite_paid_records(): void
    {
        $this->createActiveMembers(3);

        // Generate initial records
        $this->artisan('dues:generate', ['--period' => '2026-01'])->assertSuccessful();

        // Mark one as paid
        $payment = DuesPayment::first();
        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'amount' => 50000, // Different amount
        ]);

        // Run again
        $this->artisan('dues:generate', ['--period' => '2026-01'])->assertSuccessful();

        // Paid record should remain unchanged
        $payment->refresh();
        $this->assertEquals('paid', $payment->status);
        $this->assertEquals(50000, $payment->amount);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_dry_run_does_not_create_records(): void
    {
        $this->createActiveMembers(3);

        $this->artisan('dues:generate', ['--period' => '2026-01', '--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('dues_payments', 0);
    }

    public function test_backfill_creates_records_for_multiple_periods(): void
    {
        $this->createActiveMembers(2);

        $this->artisan('dues:generate', [
            '--period' => '2026-03',
            '--backfill' => '2026-01',
        ])->assertSuccessful();

        // 2 members × 3 periods = 6 records
        $this->assertDatabaseCount('dues_payments', 6);

        foreach (['2026-01', '2026-02', '2026-03'] as $period) {
            $this->assertEquals(2, DuesPayment::where('period', $period)->count());
        }
    }

    public function test_excludes_non_active_members(): void
    {
        $this->createActiveMembers(2);

        // Create non-active member
        Member::factory()->create([
            'status' => 'nonaktif',
            'organization_unit_id' => $this->unit->id,
        ]);

        $this->artisan('dues:generate', ['--period' => '2026-01'])->assertSuccessful();

        // Only 2 active members should have records
        $this->assertDatabaseCount('dues_payments', 2);
    }

    public function test_respects_join_date(): void
    {
        // Member joined before period -> should have dues
        Member::factory()->create([
            'status' => 'aktif',
            'organization_unit_id' => $this->unit->id,
            'join_date' => '2025-12-31',
        ]);

        // Member joined during period -> should have dues
        Member::factory()->create([
            'status' => 'aktif',
            'organization_unit_id' => $this->unit->id,
            'join_date' => '2026-01-15',
        ]);

        // Member joined after period -> should NOT have dues
        Member::factory()->create([
            'status' => 'aktif',
            'organization_unit_id' => $this->unit->id,
            'join_date' => '2026-02-01',
        ]);

        $this->artisan('dues:generate', ['--period' => '2026-01'])
            ->assertSuccessful();

        $this->assertDatabaseCount('dues_payments', 2);
    }

    public function test_invalid_period_format_fails(): void
    {
        $this->artisan('dues:generate', ['--period' => 'invalid'])
            ->assertFailed();

        $this->artisan('dues:generate', ['--period' => '2026-1']) // Missing leading zero
            ->assertFailed();
    }
}
