<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MutationRequest;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyUnitScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    // ========================================
    // Policy tests (user->can)
    // ========================================

    public function test_admin_unit_cannot_view_member_from_other_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $memberInUnitB = Member::factory()->create([
            'organization_unit_id' => $unitB->id,
        ]);

        $this->assertFalse($adminUnit->can('view', $memberInUnitB));
    }

    public function test_admin_unit_can_view_member_in_their_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);

        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $memberInUnitA = Member::factory()->create([
            'organization_unit_id' => $unitA->id,
        ]);

        $this->assertTrue($adminUnit->can('view', $memberInUnitA));
    }

    public function test_super_admin_can_view_member_from_any_unit(): void
    {
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $memberInUnitB = Member::factory()->create([
            'organization_unit_id' => $unitB->id,
        ]);

        $this->assertTrue($superAdmin->can('view', $memberInUnitB));
    }

    public function test_admin_unit_can_view_mutation_involving_their_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $member = Member::factory()->create(['organization_unit_id' => $unitA->id]);

        $mutation = MutationRequest::create([
            'member_id' => $member->id,
            'from_unit_id' => $unitA->id,
            'to_unit_id' => $unitB->id,
            'status' => 'pending',
            'submitted_by' => $adminUnit->id,
        ]);

        $this->assertTrue($adminUnit->can('view', $mutation));
    }

    public function test_admin_unit_cannot_view_mutation_not_involving_their_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);
        $unitC = OrganizationUnit::factory()->create(['name' => 'Unit C']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $member = Member::factory()->create(['organization_unit_id' => $unitB->id]);

        $mutation = MutationRequest::create([
            'member_id' => $member->id,
            'from_unit_id' => $unitB->id,
            'to_unit_id' => $unitC->id,
            'status' => 'pending',
            'submitted_by' => 1,
        ]);

        $this->assertFalse($adminUnitA->can('view', $mutation));
    }

    public function test_only_global_access_can_approve_mutations(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $member = Member::factory()->create(['organization_unit_id' => $unitA->id]);

        $mutation = MutationRequest::create([
            'member_id' => $member->id,
            'from_unit_id' => $unitA->id,
            'to_unit_id' => $unitB->id,
            'status' => 'pending',
            'submitted_by' => $adminUnit->id,
        ]);

        $this->assertTrue($superAdmin->can('approve', $mutation));
        $this->assertFalse($adminUnit->can('approve', $mutation));
    }

    public function test_admin_unit_cannot_update_member_from_other_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $memberInUnitB = Member::factory()->create([
            'organization_unit_id' => $unitB->id,
        ]);

        $this->assertFalse($adminUnit->can('update', $memberInUnitB));
    }

    // ========================================
    // Endpoint tests (HTTP requests)
    // ========================================

    public function test_admin_unit_gets_403_when_viewing_mutation_from_other_units(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);
        $unitC = OrganizationUnit::factory()->create(['name' => 'Unit C']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $member = Member::factory()->create(['organization_unit_id' => $unitB->id]);

        // Mutation between B and C (not involving A)
        $mutation = MutationRequest::create([
            'member_id' => $member->id,
            'from_unit_id' => $unitB->id,
            'to_unit_id' => $unitC->id,
            'status' => 'pending',
            'submitted_by' => 1,
        ]);

        $response = $this->actingAs($adminUnitA)->get("/admin/mutations/{$mutation->id}");

        $response->assertStatus(403);
    }

    public function test_admin_unit_gets_403_when_approving_mutation(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $member = Member::factory()->create(['organization_unit_id' => $unitA->id]);

        $mutation = MutationRequest::create([
            'member_id' => $member->id,
            'from_unit_id' => $unitA->id,
            'to_unit_id' => $unitB->id,
            'status' => 'pending',
            'submitted_by' => $adminUnitA->id,
        ]);

        // admin_unit cannot approve mutations (only global roles can)
        $response = $this->actingAs($adminUnitA)->post("/admin/mutations/{$mutation->id}/approve");

        $response->assertStatus(403);
    }

    public function test_super_admin_can_view_any_mutation(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $member = Member::factory()->create(['organization_unit_id' => $unitA->id]);

        $mutation = MutationRequest::create([
            'member_id' => $member->id,
            'from_unit_id' => $unitA->id,
            'to_unit_id' => $unitB->id,
            'status' => 'pending',
            'submitted_by' => 1,
        ]);

        $response = $this->actingAs($superAdmin)->get("/admin/mutations/{$mutation->id}");

        $response->assertStatus(200);
    }

    // ========================================
    // currentUnitId helper tests
    // ========================================

    public function test_user_current_unit_id_returns_organization_unit_id(): void
    {
        $unit = OrganizationUnit::factory()->create();

        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $this->assertEquals($unit->id, $user->currentUnitId());
    }

    public function test_user_current_unit_id_returns_null_for_global_user_without_unit(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
            'organization_unit_id' => null,
            'member_id' => null,
        ]);

        $this->assertNull($superAdmin->currentUnitId());
    }
}
