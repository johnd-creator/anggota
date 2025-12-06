<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Member;
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
}
