<?php

namespace Tests\Feature;

use App\Models\FinanceCategory;
use App\Models\FinanceLedger;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceUnitScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Helper to create a ledger for a unit.
     */
    protected function createLedger(int $unitId, int $createdBy, array $attributes = []): FinanceLedger
    {
        $category = FinanceCategory::firstOrCreate(
            ['name' => 'Test Category', 'organization_unit_id' => $unitId],
            ['type' => 'income', 'is_active' => true, 'sort_order' => 1, 'created_by' => $createdBy]
        );

        return FinanceLedger::create(array_merge([
            'organization_unit_id' => $unitId,
            'finance_category_id' => $category->id,
            'type' => 'income',
            'amount' => 100000,
            'date' => now()->toDateString(),
            'description' => 'Test ledger',
            'status' => 'submitted',
            'created_by' => $createdBy,
        ], $attributes));
    }

    // ========================================
    // Policy tests
    // ========================================

    public function test_bendahara_cannot_view_ledger_from_other_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $bendaharaA = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $bendaharaB = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $ledgerB = $this->createLedger($unitB->id, $bendaharaB->id);

        $this->assertFalse($bendaharaA->can('view', $ledgerB));
    }

    public function test_bendahara_can_view_ledger_in_their_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);

        $bendaharaA = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $ledgerA = $this->createLedger($unitA->id, $bendaharaA->id);

        $this->assertTrue($bendaharaA->can('view', $ledgerA));
    }

    public function test_admin_unit_cannot_approve_ledger_from_other_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $bendaharaB = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $ledgerB = $this->createLedger($unitB->id, $bendaharaB->id);

        $this->assertFalse($adminUnitA->can('approve', $ledgerB));
    }

    public function test_super_admin_can_view_any_ledger(): void
    {
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $bendaharaB = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $ledgerB = $this->createLedger($unitB->id, $bendaharaB->id);

        $this->assertTrue($superAdmin->can('view', $ledgerB));
    }

    // ========================================
    // Endpoint tests (HTTP 403)
    // ========================================

    public function test_bendahara_gets_403_when_editing_ledger_from_other_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $bendaharaA = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $bendaharaB = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $ledgerB = $this->createLedger($unitB->id, $bendaharaB->id, ['status' => 'draft']);

        $response = $this->actingAs($bendaharaA)->get("/finance/ledgers/{$ledgerB->id}/edit");

        $response->assertStatus(403);
    }

    public function test_admin_unit_gets_403_when_approving_ledger_from_other_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $bendaharaB = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $ledgerB = $this->createLedger($unitB->id, $bendaharaB->id);

        $response = $this->actingAs($adminUnitA)->post("/finance/ledgers/{$ledgerB->id}/approve");

        $response->assertStatus(403);
    }

    public function test_admin_unit_gets_403_when_rejecting_ledger_from_other_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $bendaharaB = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $ledgerB = $this->createLedger($unitB->id, $bendaharaB->id);

        $response = $this->actingAs($adminUnitA)->post("/finance/ledgers/{$ledgerB->id}/reject", [
            'rejected_reason' => 'Test rejection'
        ]);

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_ledger_endpoint(): void
    {
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $bendaharaB = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $ledgerB = $this->createLedger($unitB->id, $bendaharaB->id, ['status' => 'draft']);

        $response = $this->actingAs($superAdmin)->get("/finance/ledgers/{$ledgerB->id}/edit");

        $response->assertStatus(200);
    }

    // ========================================
    // Dues update tests
    // ========================================

    public function test_dues_policy_bendahara_cannot_update_dues_for_member_in_other_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $bendaharaA = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $memberB = Member::factory()->create([
            'organization_unit_id' => $unitB->id,
        ]);

        // Use DuesPaymentPolicy::updateForMember
        $this->assertFalse($bendaharaA->can('updateForMember', [\App\Models\DuesPayment::class, $memberB]));
    }

    public function test_super_admin_can_update_dues_for_any_member(): void
    {
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $memberB = Member::factory()->create([
            'organization_unit_id' => $unitB->id,
        ]);

        $this->assertTrue($superAdmin->can('updateForMember', [\App\Models\DuesPayment::class, $memberB]));
    }
}
