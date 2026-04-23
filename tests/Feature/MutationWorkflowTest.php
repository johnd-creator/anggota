<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Member;
use App\Models\MutationRequest;
use App\Models\OrganizationUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MutationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_mutations_with_selected_member(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $unit = OrganizationUnit::create(['code' => '001', 'name' => 'Unit A', 'address' => 'Alamat Unit A']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $member = Member::create([
            'full_name' => 'Anggota A',
            'email' => 'a@example.com',
            'status' => 'aktif',
            'organization_unit_id' => $unit->id,
            'nra' => '001-2025-0001',
            'join_date' => now(),
            'join_year' => now()->year,
            'sequence_number' => 1,
        ]);

        $response = $this->actingAs($user)->get(route('admin.mutations.index', ['member_id' => $member->id]));
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_mutations_index_and_members_list(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $unit = OrganizationUnit::create(['code' => '002', 'name' => 'Unit B', 'address' => 'Alamat Unit B']);
        $user = User::factory()->create(['role_id' => $role->id]);
        Member::create(['full_name' => 'Anggota B', 'email' => 'b@example.com', 'status' => 'aktif', 'organization_unit_id' => $unit->id, 'nra' => '002-2025-0002', 'join_date' => now(), 'join_year' => now()->year, 'sequence_number' => 2]);
        Member::create(['full_name' => 'Anggota C', 'email' => 'c@example.com', 'status' => 'aktif', 'organization_unit_id' => $unit->id, 'nra' => '002-2025-0003', 'join_date' => now(), 'join_year' => now()->year, 'sequence_number' => 3]);

        $response = $this->actingAs($user)->get(route('admin.mutations.index'));
        $response->assertStatus(200);
    }

    public function test_approved_mutation_generates_kta_sequence_from_destination_unit_without_year_reset(): void
    {
        $role = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $fromUnit = OrganizationUnit::create(['code' => '010', 'name' => 'Unit A', 'address' => 'Alamat Unit A']);
        $toUnit = OrganizationUnit::create(['code' => '011', 'name' => 'Unit B', 'address' => 'Alamat Unit B']);
        $user = User::factory()->create(['role_id' => $role->id]);

        Member::create([
            'full_name' => 'Existing Destination',
            'email' => 'existing-destination@example.com',
            'status' => 'aktif',
            'organization_unit_id' => $toUnit->id,
            'nra' => '011-2024-001',
            'join_date' => '2024-01-01',
            'join_year' => 2024,
            'sequence_number' => 7,
            'kta_number' => '011-SPPIPS-24007',
        ]);

        $member = Member::create([
            'full_name' => 'Moving Member',
            'email' => 'moving@example.com',
            'status' => 'aktif',
            'organization_unit_id' => $fromUnit->id,
            'nra' => '010-2024-001',
            'join_date' => '2024-02-01',
            'join_year' => 2024,
            'sequence_number' => 1,
            'kta_number' => '010-SPPIPS-24001',
        ]);

        $mutation = MutationRequest::create([
            'member_id' => $member->id,
            'from_unit_id' => $fromUnit->id,
            'to_unit_id' => $toUnit->id,
            'status' => 'pending',
            'submitted_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('admin.mutations.approve', $mutation));

        $response->assertRedirect();
        $member->refresh();
        $this->assertSame($toUnit->id, $member->organization_unit_id);
        $this->assertSame(8, $member->sequence_number);
        $this->assertSame('011-SPPIPS-24008', $member->kta_number);
    }
}
