<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\MutationRequest;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MutationCancelFlowTest extends TestCase
{
    use RefreshDatabase;

    private function createSetup(): array
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin']);
        $adminPusatRole = Role::firstOrCreate(['name' => 'admin_pusat'], ['label' => 'Admin Pusat']);
        $adminUnitRole = Role::firstOrCreate(['name' => 'admin_unit'], ['label' => 'Admin Unit']);

        $unitA = OrganizationUnit::create(['code' => '001', 'name' => 'Unit A', 'address' => 'Alamat A']);
        $unitB = OrganizationUnit::create(['code' => '002', 'name' => 'Unit B', 'address' => 'Alamat B']);

        $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
        $adminPusat = User::factory()->create(['role_id' => $adminPusatRole->id]);
        $adminUnitA = User::factory()->create(['role_id' => $adminUnitRole->id, 'organization_unit_id' => $unitA->id]);
        $adminUnitB = User::factory()->create(['role_id' => $adminUnitRole->id, 'organization_unit_id' => $unitB->id]);

        $member = Member::create([
            'full_name' => 'Test Member',
            'email' => 'test@example.com',
            'status' => 'aktif',
            'organization_unit_id' => $unitA->id,
            'nra' => '001-2025-0001',
            'join_date' => now(),
            'join_year' => now()->year,
            'sequence_number' => 1,
        ]);

        $mutation = MutationRequest::create([
            'member_id' => $member->id,
            'from_unit_id' => $unitA->id,
            'to_unit_id' => $unitB->id,
            'status' => 'pending',
            'submitted_by' => $superAdmin->id,
        ]);

        return compact('superAdmin', 'adminPusat', 'adminUnitA', 'adminUnitB', 'unitA', 'unitB', 'member', 'mutation');
    }

    public function test_super_admin_can_cancel_pending_mutation(): void
    {
        $setup = $this->createSetup();

        $response = $this->actingAs($setup['superAdmin'])
            ->post(route('mutations.cancel', $setup['mutation']));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setup['mutation']->refresh();
        $this->assertEquals('cancelled', $setup['mutation']->status);
        $this->assertNotNull($setup['mutation']->cancelled_at);
        $this->assertEquals($setup['superAdmin']->id, $setup['mutation']->cancelled_by_user_id);
    }

    public function test_admin_pusat_can_cancel_pending_mutation(): void
    {
        $setup = $this->createSetup();

        $response = $this->actingAs($setup['adminPusat'])
            ->post(route('mutations.cancel', $setup['mutation']));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setup['mutation']->refresh();
        $this->assertEquals('cancelled', $setup['mutation']->status);
    }

    public function test_admin_unit_can_cancel_mutation_in_their_unit(): void
    {
        $setup = $this->createSetup();

        // Admin unit A can cancel because the mutation is FROM their unit
        $response = $this->actingAs($setup['adminUnitA'])
            ->post(route('mutations.cancel', $setup['mutation']));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setup['mutation']->refresh();
        $this->assertEquals('cancelled', $setup['mutation']->status);
    }

    public function test_admin_unit_cannot_cancel_mutation_in_other_unit(): void
    {
        $setup = $this->createSetup();

        // Create a mutation from unit B (not involving admin unit A)
        $memberB = Member::create([
            'full_name' => 'Member B',
            'email' => 'memberb@example.com',
            'status' => 'aktif',
            'organization_unit_id' => $setup['unitB']->id,
            'nra' => '002-2025-0001',
            'join_date' => now(),
            'join_year' => now()->year,
            'sequence_number' => 1,
        ]);

        $unitC = OrganizationUnit::create(['code' => '003', 'name' => 'Unit C', 'address' => 'Alamat C']);

        $mutationOther = MutationRequest::create([
            'member_id' => $memberB->id,
            'from_unit_id' => $setup['unitB']->id,
            'to_unit_id' => $unitC->id,
            'status' => 'pending',
            'submitted_by' => $setup['superAdmin']->id,
        ]);

        // Admin unit A should NOT be able to cancel this (not their unit)
        $response = $this->actingAs($setup['adminUnitA'])
            ->post(route('mutations.cancel', $mutationOther));

        $response->assertForbidden();
    }

    public function test_cannot_cancel_approved_mutation(): void
    {
        $setup = $this->createSetup();

        // Set mutation to approved
        $setup['mutation']->update(['status' => 'approved']);

        $response = $this->actingAs($setup['superAdmin'])
            ->post(route('mutations.cancel', $setup['mutation']));

        // Should fail with policy check
        $response->assertForbidden();
    }

    public function test_cannot_cancel_rejected_mutation(): void
    {
        $setup = $this->createSetup();

        // Set mutation to rejected
        $setup['mutation']->update(['status' => 'rejected']);

        $response = $this->actingAs($setup['superAdmin'])
            ->post(route('mutations.cancel', $setup['mutation']));

        // Should fail with policy check
        $response->assertForbidden();
    }

    public function test_cancel_creates_activity_log(): void
    {
        $setup = $this->createSetup();

        $this->actingAs($setup['superAdmin'])
            ->post(route('mutations.cancel', $setup['mutation']));

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $setup['superAdmin']->id,
            'action' => 'mutation_cancelled',
            'subject_type' => MutationRequest::class,
            'subject_id' => $setup['mutation']->id,
        ]);
    }
}
