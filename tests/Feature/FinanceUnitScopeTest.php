<?php

namespace Tests\Feature;

use App\Models\FinanceCategory;
use App\Models\FinanceLedger;
use App\Models\DuesPayment;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
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

    // ========================================
    // Bendahara + Pusat unit visibility tests
    // ========================================

    public function test_bendahara_can_view_pusat_unit_ledger(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
        $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);

        $bendaharaA = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $bendaharaPusat = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara_pusat')->first()->id,
            'organization_unit_id' => $pusat->id,
        ]);

        $ledgerPusat = $this->createLedger($pusat->id, $bendaharaPusat->id);

        $this->assertTrue($bendaharaA->can('view', $ledgerPusat));
    }

    public function test_bendahara_cannot_view_other_non_pusat_unit_ledger(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B', 'is_pusat' => false]);
        $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);

        $bendaharaA = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $bendaharaB = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $ledgerB = $this->createLedger($unitB->id, $bendaharaB->id);

        // Bendahara A should NOT see Unit B ledger (Unit B is NOT pusat)
        $this->assertFalse($bendaharaA->can('view', $ledgerB));
    }

    public function test_accessible_finance_unit_ids_returns_own_and_pusat_for_bendahara(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
        $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B', 'is_pusat' => false]);

        $bendaharaA = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $ids = $bendaharaA->accessibleFinanceUnitIds();

        $this->assertCount(2, $ids);
        $this->assertContains($unitA->id, $ids);
        $this->assertContains($pusat->id, $ids);
        $this->assertNotContains($unitB->id, $ids);
    }

    public function test_accessible_finance_unit_ids_returns_all_for_global_roles(): void
    {
        $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);

        $bendaharaPusat = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara_pusat')->first()->id,
            'organization_unit_id' => $pusat->id,
        ]);

        $ids = $bendaharaPusat->accessibleFinanceUnitIds();

        $this->assertEmpty($ids);
    }

    public function test_accessible_finance_unit_ids_returns_only_own_for_admin_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
        $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $ids = $adminUnitA->accessibleFinanceUnitIds();

        $this->assertCount(1, $ids);
        $this->assertContains($unitA->id, $ids);
        $this->assertNotContains($pusat->id, $ids);
    }

    public function test_can_access_finance_unit_for_bendahara(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
        $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B', 'is_pusat' => false]);

        $bendaharaA = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $this->assertTrue($bendaharaA->canAccessFinanceUnit($unitA->id));
        $this->assertTrue($bendaharaA->canAccessFinanceUnit($pusat->id));
        $this->assertFalse($bendaharaA->canAccessFinanceUnit($unitB->id));
        $this->assertFalse($bendaharaA->canAccessFinanceUnit(null));
    }

    public function test_bendahara_index_returns_only_own_and_pusat_ledgers(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B', 'is_pusat' => false]);
        $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);

        $bendaharaA = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $bendaharaB = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $bendaharaPusat = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara_pusat')->first()->id,
            'organization_unit_id' => $pusat->id,
        ]);

        $this->createLedger($unitA->id, $bendaharaA->id, ['description' => 'Ledger Unit A']);
        $this->createLedger($unitB->id, $bendaharaB->id, ['description' => 'Ledger Unit B']);
        $this->createLedger($pusat->id, $bendaharaPusat->id, ['description' => 'Ledger Pusat']);

        $response = $this->actingAs($bendaharaA)->get('/finance/ledgers');

        $response->assertStatus(200);
        $ledgersData = $response->viewData('page')['props']['ledgers']['data'];
        $this->assertCount(2, $ledgersData);
        $descriptions = array_column($ledgersData, 'description');
        $this->assertContains('Ledger Unit A', $descriptions);
        $this->assertContains('Ledger Pusat', $descriptions);
        $this->assertNotContains('Ledger Unit B', $descriptions);
    }

    public function test_bendahara_gets_403_when_accessing_other_unit_directly(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B', 'is_pusat' => false]);
        $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);

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

    public function test_bendahara_can_access_pusat_ledger_directly(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
        $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);

        $bendaharaA = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $bendaharaPusat = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara_pusat')->first()->id,
            'organization_unit_id' => $pusat->id,
        ]);

        $ledgerPusat = $this->createLedger($pusat->id, $bendaharaPusat->id, ['status' => 'draft']);

        $this->assertTrue($bendaharaA->can('view', $ledgerPusat));
    }

    public function test_bendahara_pusat_can_view_all_units(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B', 'is_pusat' => false]);
        $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);

        $bendaharaPusat = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara_pusat')->first()->id,
            'organization_unit_id' => $pusat->id,
        ]);

        $bendaharaA = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $ledgerA = $this->createLedger($unitA->id, $bendaharaA->id);
        $ledgerB = $this->createLedger($unitB->id, $bendaharaA->id);
        $ledgerPusat = $this->createLedger($pusat->id, $bendaharaPusat->id);

        $this->assertTrue($bendaharaPusat->can('view', $ledgerA));
        $this->assertTrue($bendaharaPusat->can('view', $ledgerB));
        $this->assertTrue($bendaharaPusat->can('view', $ledgerPusat));
    }

    public function test_bendahara_pusat_finance_access_is_read_only(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
        $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);

        $bendaharaPusat = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara_pusat')->first()->id,
            'organization_unit_id' => $pusat->id,
        ]);

        $ledger = $this->createLedger($unitA->id, $bendaharaPusat->id, ['status' => 'draft']);
        $category = FinanceCategory::firstOrCreate(
            ['name' => 'Pusat Category', 'organization_unit_id' => $pusat->id],
            ['type' => 'income', 'is_active' => true, 'sort_order' => 1, 'created_by' => $bendaharaPusat->id]
        );
        $dues = DuesPayment::create([
            'member_id' => Member::factory()->create(['organization_unit_id' => $pusat->id])->id,
            'organization_unit_id' => $pusat->id,
            'period' => now()->format('Y-m'),
            'status' => 'paid',
            'amount' => 100000,
            'paid_at' => now(),
            'recorded_by' => $bendaharaPusat->id,
        ]);

        $this->assertTrue($bendaharaPusat->can('view', $ledger));
        $this->assertFalse($bendaharaPusat->can('create', FinanceLedger::class));
        $this->assertFalse($bendaharaPusat->can('update', $ledger));
        $this->assertFalse($bendaharaPusat->can('delete', $ledger));
        $this->assertFalse($bendaharaPusat->can('approve', $ledger));
        $this->assertFalse($bendaharaPusat->can('create', FinanceCategory::class));
        $this->assertFalse($bendaharaPusat->can('update', $category));
        $this->assertFalse($bendaharaPusat->can('update', $dues));
    }

    public function test_bendahara_can_read_but_not_update_pusat_finance(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A', 'is_pusat' => false]);
        $pusat = OrganizationUnit::factory()->create(['name' => 'DPP Pusat', 'is_pusat' => true]);

        $bendaharaA = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $ledgerPusat = $this->createLedger($pusat->id, $bendaharaA->id, ['status' => 'draft']);
        $duesPusat = DuesPayment::create([
            'member_id' => Member::factory()->create(['organization_unit_id' => $pusat->id])->id,
            'organization_unit_id' => $pusat->id,
            'period' => now()->format('Y-m'),
            'status' => 'paid',
            'amount' => 100000,
            'paid_at' => now(),
            'recorded_by' => $bendaharaA->id,
        ]);

        $this->assertTrue($bendaharaA->can('view', $ledgerPusat));
        $this->assertFalse($bendaharaA->can('update', $ledgerPusat));
        $this->assertFalse($bendaharaA->can('delete', $ledgerPusat));
        $this->assertTrue($bendaharaA->can('view', $duesPusat));
        $this->assertFalse($bendaharaA->can('update', $duesPusat));
    }
}
