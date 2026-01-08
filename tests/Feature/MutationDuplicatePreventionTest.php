<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\MutationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MutationDuplicatePreventionTest extends TestCase
{
    use RefreshDatabase;

    private function createSetup(): array
    {
        $role = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin']);
        $unitA = OrganizationUnit::create(['code' => '001', 'name' => 'Unit A', 'address' => 'Alamat A']);
        $unitB = OrganizationUnit::create(['code' => '002', 'name' => 'Unit B', 'address' => 'Alamat B']);
        $user = User::factory()->create(['role_id' => $role->id]);
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

        return compact('role', 'unitA', 'unitB', 'user', 'member');
    }

    public function test_cannot_create_mutation_when_pending_exists_for_same_member(): void
    {
        $setup = $this->createSetup();

        // Create a pending mutation for the member
        MutationRequest::create([
            'member_id' => $setup['member']->id,
            'from_unit_id' => $setup['unitA']->id,
            'to_unit_id' => $setup['unitB']->id,
            'status' => 'pending',
            'submitted_by' => $setup['user']->id,
        ]);

        // Try to create another mutation for the same member
        $response = $this->actingAs($setup['user'])->post(route('mutations.store'), [
            'member_id' => $setup['member']->id,
            'to_unit_id' => $setup['unitB']->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('masih memiliki pengajuan mutasi', session('error'));

        // Should still have only 1 mutation
        $this->assertEquals(1, MutationRequest::where('member_id', $setup['member']->id)->count());
    }

    public function test_can_create_mutation_after_cancelling_pending(): void
    {
        $setup = $this->createSetup();

        // Create and cancel a mutation
        $mutation = MutationRequest::create([
            'member_id' => $setup['member']->id,
            'from_unit_id' => $setup['unitA']->id,
            'to_unit_id' => $setup['unitB']->id,
            'status' => 'cancelled',
            'submitted_by' => $setup['user']->id,
        ]);

        // Now create a new mutation
        $response = $this->actingAs($setup['user'])->post(route('mutations.store'), [
            'member_id' => $setup['member']->id,
            'to_unit_id' => $setup['unitB']->id,
        ]);

        $response->assertRedirect(route('mutations.index'));
        $response->assertSessionHas('success');

        // Should have 2 mutations now
        $this->assertEquals(2, MutationRequest::where('member_id', $setup['member']->id)->count());
    }

    public function test_can_create_mutation_when_previous_was_approved(): void
    {
        $setup = $this->createSetup();

        // Create an approved mutation
        MutationRequest::create([
            'member_id' => $setup['member']->id,
            'from_unit_id' => $setup['unitA']->id,
            'to_unit_id' => $setup['unitB']->id,
            'status' => 'approved',
            'submitted_by' => $setup['user']->id,
        ]);

        // Update member's unit to match approved mutation
        $setup['member']->update(['organization_unit_id' => $setup['unitB']->id]);

        // Create another unit for new mutation
        $unitC = OrganizationUnit::create(['code' => '003', 'name' => 'Unit C', 'address' => 'Alamat C']);

        // Create a new mutation
        $response = $this->actingAs($setup['user'])->post(route('mutations.store'), [
            'member_id' => $setup['member']->id,
            'to_unit_id' => $unitC->id,
        ]);

        $response->assertRedirect(route('mutations.index'));
        $response->assertSessionHas('success');
    }

    public function test_can_create_mutation_when_previous_was_rejected(): void
    {
        $setup = $this->createSetup();

        // Create a rejected mutation
        MutationRequest::create([
            'member_id' => $setup['member']->id,
            'from_unit_id' => $setup['unitA']->id,
            'to_unit_id' => $setup['unitB']->id,
            'status' => 'rejected',
            'submitted_by' => $setup['user']->id,
        ]);

        // Create a new mutation
        $response = $this->actingAs($setup['user'])->post(route('mutations.store'), [
            'member_id' => $setup['member']->id,
            'to_unit_id' => $setup['unitB']->id,
        ]);

        $response->assertRedirect(route('mutations.index'));
        $response->assertSessionHas('success');
    }
}
