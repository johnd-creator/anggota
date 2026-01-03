<?php

namespace Tests\Feature;

use App\Models\FinanceLedger;
use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Comprehensive RBAC + Unit Scope Regression Tests
 * 
 * Ensures admin_unit cannot access cross-unit resources and
 * global roles (super_admin) maintain proper access.
 */
class RbacUnitScopeRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUnitA;
    protected User $superAdmin;
    protected OrganizationUnit $unitA;
    protected OrganizationUnit $unitB;
    protected Member $memberA;
    protected Member $memberB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $this->unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $this->adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $this->unitA->id,
        ]);

        $this->superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $this->memberA = Member::factory()->create([
            'organization_unit_id' => $this->unitA->id,
            'status' => 'aktif',
        ]);

        $this->memberB = Member::factory()->create([
            'organization_unit_id' => $this->unitB->id,
            'status' => 'aktif',
        ]);
    }

    // ========================================
    // MEMBER ACCESS TESTS
    // ========================================

    public function test_admin_unit_cannot_view_member_from_other_unit(): void
    {
        $response = $this->actingAs($this->adminUnitA)
            ->get("/admin/members/{$this->memberB->id}");

        $response->assertStatus(403);
    }

    public function test_admin_unit_cannot_edit_member_from_other_unit(): void
    {
        $response = $this->actingAs($this->adminUnitA)
            ->get("/admin/members/{$this->memberB->id}/edit");

        $response->assertStatus(403);
    }

    public function test_super_admin_can_view_any_member(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get("/admin/members/{$this->memberB->id}");

        $response->assertStatus(200);
    }

    // ========================================
    // LETTER ACCESS TESTS
    // ========================================

    public function test_admin_unit_cannot_view_letter_from_other_unit(): void
    {
        $this->seed(\Database\Seeders\LetterCategorySeeder::class);

        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Secret Letter',
            'body' => 'Content',
            'from_unit_id' => $this->unitB->id,
            'to_type' => 'admin_pusat',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'status' => 'sent',
            'signer_type' => 'ketua',
            'creator_user_id' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->adminUnitA)
            ->get("/letters/{$letter->id}");

        $response->assertStatus(403);
    }

    public function test_super_admin_can_view_any_letter(): void
    {
        $this->seed(\Database\Seeders\LetterCategorySeeder::class);

        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Any Letter',
            'body' => 'Content',
            'from_unit_id' => $this->unitB->id,
            'to_type' => 'admin_pusat',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'status' => 'sent',
            'signer_type' => 'ketua',
            'creator_user_id' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get("/letters/{$letter->id}");

        $response->assertStatus(200);
    }

    // ========================================
    // REPORT/EXPORT SCOPE INJECTION TESTS
    // ========================================

    public function test_admin_unit_cannot_inject_unit_id_in_reports(): void
    {
        // Create members in both units
        Member::factory()->count(2)->create(['organization_unit_id' => $this->unitA->id]);
        Member::factory()->count(5)->create(['organization_unit_id' => $this->unitB->id]);

        // Admin A tries to access growth report with unit_id=B
        $response = $this->actingAs($this->adminUnitA)
            ->get('/reports/growth?unit_id=' . $this->unitB->id);

        $response->assertStatus(200);

        // Check that filters.unit_id is forced to unit A (not B)
        $response->assertInertia(
            fn(Assert $page) => $page
                ->where('filters.unit_id', $this->unitA->id)
        );
    }

    public function test_admin_unit_cannot_inject_unit_id_in_member_search(): void
    {
        // Member in unit A
        Member::factory()->create([
            'organization_unit_id' => $this->unitA->id,
            'full_name' => 'Test Search A',
            'nra' => 'NRA-A001',
            'status' => 'aktif',
        ]);

        // Member in unit B with same search pattern
        Member::factory()->create([
            'organization_unit_id' => $this->unitB->id,
            'full_name' => 'Test Search B',
            'nra' => 'NRA-B001',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($this->adminUnitA)
            ->getJson('/api/members/search?q=Test+Search');

        $response->assertStatus(200);

        $data = $response->json();
        $names = collect($data)->pluck('label')->implode(' ');

        $this->assertStringContainsString('Test Search A', $names);
        $this->assertStringNotContainsString('Test Search B', $names);
    }

    // ========================================
    // ADMIN ENDPOINTS ACCESS TESTS
    // ========================================

    public function test_admin_unit_cannot_access_activity_logs(): void
    {
        $response = $this->actingAs($this->adminUnitA)
            ->get('/admin/activity-logs');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_activity_logs(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/activity-logs');

        $response->assertStatus(200);
    }

    public function test_admin_unit_cannot_access_aspiration_categories(): void
    {
        $response = $this->actingAs($this->adminUnitA)
            ->get('/admin/aspiration-categories');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_aspiration_categories(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/admin/aspiration-categories');

        $response->assertStatus(200);
    }

    // ========================================
    // FINANCE ACCESS TESTS
    // ========================================

    public function test_admin_unit_cannot_view_ledger_from_other_unit(): void
    {
        $category = \App\Models\FinanceCategory::create([
            'name' => 'Test Category',
            'type' => 'income',
            'organization_unit_id' => $this->unitB->id,
            'created_by' => $this->superAdmin->id,
        ]);

        $ledger = FinanceLedger::create([
            'organization_unit_id' => $this->unitB->id,
            'finance_category_id' => $category->id,
            'date' => now(),
            'type' => 'income',
            'amount' => 100000,
            'description' => 'Test',
            'status' => 'approved',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->adminUnitA)
            ->get("/finance/ledgers/{$ledger->id}/edit");

        $response->assertStatus(403);
    }

    public function test_super_admin_can_view_any_ledger(): void
    {
        $category = \App\Models\FinanceCategory::create([
            'name' => 'Test Category 2',
            'type' => 'income',
            'organization_unit_id' => $this->unitB->id,
            'created_by' => $this->superAdmin->id,
        ]);

        $ledger = FinanceLedger::create([
            'organization_unit_id' => $this->unitB->id,
            'finance_category_id' => $category->id,
            'date' => now(),
            'type' => 'income',
            'amount' => 100000,
            'description' => 'Test',
            'status' => 'approved',
            'created_by' => $this->superAdmin->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get("/finance/ledgers/{$ledger->id}/edit");

        $response->assertStatus(200);
    }

    // ========================================
    // DASHBOARD SCOPE TESTS
    // ========================================

    public function test_admin_unit_dashboard_shows_only_own_unit_data(): void
    {
        $response = $this->actingAs($this->adminUnitA)
            ->get('/dashboard');

        $response->assertStatus(200);

        $response->assertInertia(
            fn(Assert $page) => $page
                ->where('counters.is_global', false)
                ->where('counters.units_total', 1)
        );
    }

    public function test_super_admin_dashboard_shows_global_data(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get('/dashboard');

        $response->assertStatus(200);

        $response->assertInertia(
            fn(Assert $page) => $page
                ->where('counters.is_global', true)
        );
    }
}
