<?php

namespace Tests\Feature;

use App\Models\FinanceCategory;
use App\Models\FinanceLedger;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FinanceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $bendahara;
    protected $unit;
    protected $roleSuperAdmin;
    protected $roleBendahara;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleSuperAdmin = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $this->roleBendahara = Role::create(['name' => 'bendahara', 'label' => 'Bendahara']);
        Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);

        $this->unit = OrganizationUnit::factory()->create();

        $this->superAdmin = User::factory()->create([
            'role_id' => $this->roleSuperAdmin->id,
        ]);

        $this->bendahara = User::factory()->create([
            'role_id' => $this->roleBendahara->id,
            'organization_unit_id' => $this->unit->id,
        ]);
    }

    public function test_super_admin_can_crud_categories()
    {
        // Create global category
        $response = $this->actingAs($this->superAdmin)
            ->post(route('finance.categories.store'), [
                'name' => 'Global Income',
                'type' => 'income',
                'organization_unit_id' => null,
            ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('finance_categories', [
            'name' => 'Global Income',
            'organization_unit_id' => null,
        ]);

        // Create unit category
        $response = $this->actingAs($this->superAdmin)
            ->post(route('finance.categories.store'), [
                'name' => 'Unit Expense',
                'type' => 'expense',
                'organization_unit_id' => $this->unit->id,
            ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('finance_categories', [
            'name' => 'Unit Expense',
            'organization_unit_id' => $this->unit->id,
        ]);
    }

    public function test_bendahara_can_only_create_unit_categories()
    {
        // Try to create global category (should fail or force unit_id depending on controller logic)
        // Controller logic for non-super_admin: $unitId = $user->organization_unit_id;
        // So even if they send null, it forces their unit id.
        
        $response = $this->actingAs($this->bendahara)
            ->post(route('finance.categories.store'), [
                'name' => 'My Unit Income',
                'type' => 'income',
                'organization_unit_id' => null, // Trying to be global
            ]);
        
        $response->assertRedirect();
        
        // Should be stored with their unit ID
        $this->assertDatabaseHas('finance_categories', [
            'name' => 'My Unit Income',
            'organization_unit_id' => $this->unit->id,
        ]);
    }

    public function test_category_name_unique_per_unit_and_type()
    {
        FinanceCategory::create([
            'name' => 'Operational',
            'type' => 'expense',
            'organization_unit_id' => $this->unit->id,
            'created_by' => $this->superAdmin->id,
        ]);

        // Try duplicate
        $response = $this->actingAs($this->bendahara)
            ->post(route('finance.categories.store'), [
                'name' => 'Operational',
                'type' => 'expense',
            ]);
        
        $response->assertSessionHasErrors('name');
    }

    public function test_ledger_creation_with_workflow_enabled()
    {
        // Mock env via config since the model uses env() directly which is hard to mock, 
        // BUT wait, model uses env() directly?
        // Code: return (bool) env('FINANCE_WORKFLOW_ENABLED', false);
        // env() calls are cached in Laravel config if cached, but typically read from $_ENV.
        // We can't easily mock env() helper in tests if it's not using config().
        // However, I updated the model to use env(). 
        // Best practice is to use config, and in test set config.
        // Let's rely on the default behavior (false) first, then try to override.
        // Actually, `putenv` works for `env()` in tests if not cached.
        
        putenv('FINANCE_WORKFLOW_ENABLED=true');
        
        $category = FinanceCategory::create([
            'name' => 'Test Cat',
            'type' => 'income',
            'organization_unit_id' => $this->unit->id,
            'created_by' => $this->superAdmin->id,
        ]);

        Storage::fake('public');
        $file = UploadedFile::fake()->image('receipt.jpg');

        $response = $this->actingAs($this->bendahara)
            ->post(route('finance.ledgers.store'), [
                'date' => '2025-01-01',
                'finance_category_id' => $category->id,
                'type' => 'income',
                'amount' => 100000,
                'attachment' => $file,
            ]);

        $response->assertRedirect();
        
        $ledger = FinanceLedger::first();
        $this->assertEquals('submitted', $ledger->status);
        $this->assertTrue(Storage::disk('public')->exists('finance/attachments/' . $file->hashName()));
        
        // Clean up env
        putenv('FINANCE_WORKFLOW_ENABLED=false');
    }

    public function test_ledger_creation_with_workflow_disabled()
    {
        putenv('FINANCE_WORKFLOW_ENABLED=false');
        
        $category = FinanceCategory::create([
            'name' => 'Test Cat 2',
            'type' => 'income',
            'organization_unit_id' => $this->unit->id,
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->bendahara)
            ->post(route('finance.ledgers.store'), [
                'date' => '2025-01-01',
                'finance_category_id' => $category->id,
                'type' => 'income',
                'amount' => 50000,
            ]);

        $response->assertRedirect();
        $this->assertEquals('approved', FinanceLedger::first()->status);
    }

    public function test_bendahara_sees_only_own_unit_ledgers()
    {
        $otherUnit = OrganizationUnit::factory()->create();
        
        $cat1 = FinanceCategory::create([
            'name' => 'Cat 1',
            'type' => 'income',
            'organization_unit_id' => $this->unit->id,
            'created_by' => $this->superAdmin->id,
        ]);
        
        $cat2 = FinanceCategory::create([
            'name' => 'Cat 2',
            'type' => 'income',
            'organization_unit_id' => $otherUnit->id,
            'created_by' => $this->superAdmin->id,
        ]);

        FinanceLedger::create([
            'organization_unit_id' => $this->unit->id,
            'finance_category_id' => $cat1->id,
            'type' => 'income',
            'amount' => 100,
            'date' => now(),
            'status' => 'approved',
            'created_by' => $this->bendahara->id,
        ]);

        FinanceLedger::create([
            'organization_unit_id' => $otherUnit->id,
            'finance_category_id' => $cat2->id,
            'type' => 'income',
            'amount' => 200,
            'date' => now(),
            'status' => 'approved',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->bendahara)
            ->get(route('finance.ledgers.index'));
        
        $response->assertOk();
        // Check Inertia prop 'ledgers'
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Finance/Ledgers/Index')
            ->has('ledgers.data', 1)
            ->where('ledgers.data.0.amount', '100.00')
        );
    }
}
