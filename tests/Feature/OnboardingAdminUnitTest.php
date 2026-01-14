<?php

namespace Tests\Feature;

use App\Models\PendingMember;
use App\Models\User;
use App\Models\OrganizationUnit;
use App\Models\UnionPosition;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as AssertableInertiaPage;
use Tests\TestCase;

class OnboardingAdminUnitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_unit_can_view_all_pending_members()
    {
        $adminUnitRole = Role::where('name', 'admin_unit')->first();
        $superAdminRole = Role::where('name', 'super_admin')->first();

        $unit1 = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unit2 = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $pending1 = PendingMember::factory()->create([
            'email' => 'user1@gmail.com',
            'name' => 'User 1',
            'status' => 'pending',
            'organization_unit_id' => $unit1->id,
        ]);

        $pending2 = PendingMember::factory()->create([
            'email' => 'user2@gmail.com',
            'name' => 'User 2',
            'status' => 'pending',
            'organization_unit_id' => $unit2->id,
        ]);

        $pending3 = PendingMember::factory()->create([
            'email' => 'user3@gmail.com',
            'name' => 'User 3',
            'status' => 'pending',
            'organization_unit_id' => null, // SSO created without unit
        ]);

        $adminUnitUser = User::factory()->create([
            'role_id' => $adminUnitRole->id,
            'organization_unit_id' => $unit1->id,
        ]);

        $this->actingAs($adminUnitUser);

        $response = $this->get('/admin/onboarding');

        $response->assertStatus(200);

        $items = $response->viewData('page')['props']['items'];
        $this->assertCount(3, $items['data']);
        $emails = collect($items['data'])->pluck('email')->toArray();
        $this->assertContains('user1@gmail.com', $emails);
        $this->assertContains('user2@gmail.com', $emails);
        $this->assertContains('user3@gmail.com', $emails);
    }

    public function test_admin_unit_can_approve_pending_member_without_unit()
    {
        $adminUnitRole = Role::where('name', 'admin_unit')->first();

        $unit = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $position = UnionPosition::factory()->create();

        $pending = PendingMember::factory()->create([
            'email' => 'user@gmail.com',
            'name' => 'User Name',
            'status' => 'pending',
            'organization_unit_id' => null, // SSO created without unit
        ]);

        $adminUnitUser = User::factory()->create([
            'role_id' => $adminUnitRole->id,
            'organization_unit_id' => $unit->id,
        ]);

        $this->actingAs($adminUnitUser);

        $response = $this->post("/admin/onboarding/{$pending->id}/approve", [
            'full_name' => 'User Name',
            'email' => 'user@gmail.com',
            'nip' => '1234567890',
            'union_position_id' => $position->id,
            'join_date' => '2024-01-01',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('members', [
            'email' => 'user@gmail.com',
            'organization_unit_id' => $unit->id, // Should be admin_unit's unit
            'full_name' => 'User Name',
        ]);

        $this->assertDatabaseHas('pending_members', [
            'id' => $pending->id,
            'status' => 'approved',
        ]);
    }

    public function test_admin_unit_can_approve_pending_member_with_different_unit()
    {
        $adminUnitRole = Role::where('name', 'admin_unit')->first();

        $unit1 = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unit2 = OrganizationUnit::factory()->create(['name' => 'Unit B']);
        $position = UnionPosition::factory()->create();

        $pending = PendingMember::factory()->create([
            'email' => 'user@gmail.com',
            'name' => 'User Name',
            'status' => 'pending',
            'organization_unit_id' => $unit2->id, // Different unit
        ]);

        $adminUnitUser = User::factory()->create([
            'role_id' => $adminUnitRole->id,
            'organization_unit_id' => $unit1->id,
        ]);

        $this->actingAs($adminUnitUser);

        $response = $this->post("/admin/onboarding/{$pending->id}/approve", [
            'full_name' => 'User Name',
            'email' => 'user@gmail.com',
            'nip' => '1234567890',
            'union_position_id' => $position->id,
            'join_date' => '2024-01-01',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('members', [
            'email' => 'user@gmail.com',
            'organization_unit_id' => $unit1->id, // Should be admin_unit's unit, not pending's unit
            'full_name' => 'User Name',
        ]);
    }

    public function test_super_admin_must_provide_organization_unit_id()
    {
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $position = UnionPosition::factory()->create();

        $pending = PendingMember::factory()->create([
            'email' => 'user@gmail.com',
            'name' => 'User Name',
            'status' => 'pending',
            'organization_unit_id' => null,
        ]);

        $superAdminUser = User::factory()->create([
            'role_id' => $superAdminRole->id,
        ]);

        $this->actingAs($superAdminUser);

        $response = $this->post("/admin/onboarding/{$pending->id}/approve", [
            'full_name' => 'User Name',
            'email' => 'user@gmail.com',
            'nip' => '1234567890',
            'union_position_id' => $position->id,
            'join_date' => '2024-01-01',
            'organization_unit_id' => null, // Missing - should fail
        ]);

        $response->assertSessionHasErrors(['organization_unit_id']);
    }

    public function test_admin_unit_approval_policy_returns_true_for_any_pending()
    {
        $adminUnitRole = Role::where('name', 'admin_unit')->first();

        $unit = OrganizationUnit::factory()->create();

        $pendingWithUnit = PendingMember::factory()->create([
            'organization_unit_id' => $unit->id,
        ]);

        $pendingWithoutUnit = PendingMember::factory()->create([
            'organization_unit_id' => null,
        ]);

        $adminUnitUser = User::factory()->create([
            'role_id' => $adminUnitRole->id,
            'organization_unit_id' => $unit->id,
        ]);

        $this->actingAs($adminUnitUser);

        $policy = new \App\Policies\PendingMemberPolicy();

        $this->assertTrue($policy->approve($adminUnitUser, $pendingWithUnit));
        $this->assertTrue($policy->approve($adminUnitUser, $pendingWithoutUnit));
        $this->assertTrue($policy->view($adminUnitUser, $pendingWithUnit));
        $this->assertTrue($policy->view($adminUnitUser, $pendingWithoutUnit));
    }

    public function test_admin_unit_without_unit_sees_no_pending_members()
    {
        $adminUnitRole = Role::where('name', 'admin_unit')->first();

        $pending = PendingMember::factory()->create([
            'email' => 'user@gmail.com',
            'status' => 'pending',
        ]);

        $adminUnitUserWithoutUnit = User::factory()->create([
            'role_id' => $adminUnitRole->id,
            'organization_unit_id' => null,
        ]);

        $this->actingAs($adminUnitUserWithoutUnit);

        $response = $this->get('/admin/onboarding');

        $response->assertStatus(200);

        $items = $response->viewData('page')['props']['items'];
        $this->assertCount(0, $items['data']);
    }
}
