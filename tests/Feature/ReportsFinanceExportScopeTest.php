<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DuesPayment;
use App\Models\FinanceCategory;
use App\Models\FinanceLedger;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsFinanceExportScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_bendahara_cannot_export_other_unit_dues(): void
    {
        config(['features.finance' => true]);

        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $bendahara = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        Member::factory()->create(['organization_unit_id' => $unitA->id, 'full_name' => 'Member A']);
        Member::factory()->create(['organization_unit_id' => $unitB->id, 'full_name' => 'Member B']);

        // Bendahara tries to request Unit B
        $response = $this->actingAs($bendahara)->get('/reports/export?type=dues_per_period&unit_id=' . $unitB->id);

        $response->assertStatus(200);

        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($content)), fn($line) => !empty(trim($line)));

        // Header + Member A = 2 lines. Member B should NOT be there.
        $this->assertCount(2, $lines, "Should only contain own unit member");
        $this->assertStringContainsString('Member A', $content);
        $this->assertStringNotContainsString('Member B', $content);
    }

    public function test_admin_pusat_can_export_any_unit_dues_summary(): void
    {
        config(['features.finance' => true]);

        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminPusat = User::factory()->create([
            'role_id' => Role::where('name', 'admin_pusat')->first()->id,
        ]);

        Member::factory()->count(2)->create(['organization_unit_id' => $unitA->id]);
        Member::factory()->count(3)->create(['organization_unit_id' => $unitB->id]);

        // Request dues summary for Unit B
        $response = $this->actingAs($adminPusat)->get('/reports/export?type=dues_summary&unit_id=' . $unitB->id);

        $response->assertStatus(200);
        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($content)), fn($line) => !empty(trim($line)));

        // Header + Unit B row = 2 lines
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('Unit B', $content);
        $this->assertStringNotContainsString('Unit A', $content);
    }

    public function test_finance_feature_disabled_returns_503(): void
    {
        config(['features.finance' => false]);

        $admin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $response = $this->actingAs($admin)->get('/reports/export?type=dues_summary');

        $response->assertStatus(503);
    }

    public function test_dues_per_period_csv_structure_correct(): void
    {
        config(['features.finance' => true]);

        $unit = OrganizationUnit::factory()->create(['name' => 'Unit Test']);
        $member = Member::factory()->create(['organization_unit_id' => $unit->id, 'full_name' => 'John Doe']);
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id, 'organization_unit_id' => $unit->id]);

        // Create paid dues
        DuesPayment::create([
            'member_id' => $member->id,
            'organization_unit_id' => $unit->id,
            'period' => '2025-01',
            'status' => 'paid',
            'amount' => 50000,
            'paid_at' => now(),
            'recorded_by' => $admin->id
        ]);

        $response = $this->actingAs($admin)->get('/reports/export?type=dues_per_period&period=2025-01&include_notes=1');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // Expected headers: Member ID, Full Name, KTA, Unit, Period, Status, Amount, Paid At, Recorded By, Notes
        $this->assertStringContainsString('John Doe', $content);
        $this->assertStringContainsString('50000', $content);
        $this->assertStringContainsString('paid', $content);
        $this->assertStringContainsString($admin->name, $content);
    }

    public function test_audit_log_created_for_finance_export(): void
    {
        config(['features.finance' => true]);

        $unit = OrganizationUnit::factory()->create();
        $admin = User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id, 'organization_unit_id' => $unit->id]);

        $this->actingAs($admin)->get('/reports/export?type=finance_ledgers');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'event' => 'export.reports.finance_ledgers',
        ]);

        $log = AuditLog::where('event', 'export.reports.finance_ledgers')->first();
        $this->assertEquals($unit->id, $log->payload['unit_id']);
    }

    protected function createFinanceCategory(?int $createdBy = null): FinanceCategory
    {
        return FinanceCategory::create([
            'name' => 'Test Category',
            'type' => 'income',
            'description' => 'Test',
            'is_recurring' => false,
            'default_amount' => 0,
            'is_system' => false,
            'created_by' => $createdBy ?? User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id])->id,
        ]);
    }

    protected function createFinanceLedger(array $attributes): FinanceLedger
    {
        // Ensure we have a created_by user
        if (!isset($attributes['created_by'])) {
            $attributes['created_by'] = User::factory()->create([
                'role_id' => Role::where('name', 'super_admin')->first()->id
            ])->id;
        }

        return FinanceLedger::create(array_merge([
            'description' => 'Test Ledger',
            'status' => 'approved',
            'amount' => 1000,
            'date' => now(),
            'type' => 'income',
        ], $attributes));
    }

    public function test_finance_ledgers_scope_enforced(): void
    {
        config(['features.finance' => true]);

        $unitA = OrganizationUnit::factory()->create();
        $unitB = OrganizationUnit::factory()->create();
        $category = $this->createFinanceCategory();

        $adminA = User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id, 'organization_unit_id' => $unitA->id]);

        $this->createFinanceLedger(['organization_unit_id' => $unitA->id, 'finance_category_id' => $category->id]);
        $this->createFinanceLedger(['organization_unit_id' => $unitB->id, 'finance_category_id' => $category->id]);

        // Admin A requests export, tries to inject Unit B ID
        $response = $this->actingAs($adminA)->get('/reports/export?type=finance_ledgers&unit_id=' . $unitB->id);

        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($content)), fn($line) => !empty(trim($line)));

        // Header + 1 row (Unit A only)
        $this->assertCount(2, $lines);
    }

    public function test_finance_monthly_summary_calculation(): void
    {
        config(['features.finance' => true]);

        $unit = OrganizationUnit::factory()->create(['name' => 'Unit Calc']);
        $admin = User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id]);
        $category = $this->createFinanceCategory();

        // Income 1000 in Jan
        $this->createFinanceLedger([
            'organization_unit_id' => $unit->id,
            'finance_category_id' => $category->id,
            'type' => 'income',
            'amount' => 1000,
            'date' => '2025-01-15',
            'status' => 'approved'
        ]);

        // Expense 200 in Jan
        $this->createFinanceLedger([
            'organization_unit_id' => $unit->id,
            'finance_category_id' => $category->id,
            'type' => 'expense',
            'amount' => 200,
            'date' => '2025-01-20',
            'status' => 'approved'
        ]);

        $response = $this->actingAs($admin)->get('/reports/export?type=finance_monthly_summary&year=2025');

        $content = $response->streamedContent();
        // Check for 1000, 200, and net 800 in the output lines
        // CSV: Unit ID, Unit Name, Month, Income, Expense, Net
        // We look for a line containing: Unit Calc,1,1000,200,800
        $this->assertStringContainsString('Unit Calc', $content);
        $this->assertStringContainsString('1000', $content);
        $this->assertStringContainsString('200', $content);
        $this->assertStringContainsString('800', $content);
    }
}
