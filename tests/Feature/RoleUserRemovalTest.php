<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleUserRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $roleSuperAdmin;
    protected $roleBendahara;
    protected $roleReguler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roleSuperAdmin = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $this->roleBendahara = Role::create(['name' => 'bendahara', 'label' => 'Bendahara']);
        $this->roleReguler = Role::create(['name' => 'reguler', 'label' => 'Reguler']);

        $this->superAdmin = User::factory()->create([
            'role_id' => $this->roleSuperAdmin->id,
        ]);
    }

    public function test_super_admin_can_remove_user_from_role()
    {
        $unit = \App\Models\OrganizationUnit::factory()->create();

        $user = User::factory()->create([
            'role_id' => $this->roleBendahara->id,
            'organization_unit_id' => $unit->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('admin.roles.remove_user', [
                'role' => $this->roleBendahara->id,
                'user' => $user->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $user->refresh();
        $this->assertEquals($this->roleReguler->id, $user->role_id);
        $this->assertNull($user->organization_unit_id);
    }


    public function test_non_super_admin_cannot_remove_user_from_role()
    {
        $bendahara = User::factory()->create([
            'role_id' => $this->roleBendahara->id,
        ]);

        $otherUser = User::factory()->create([
            'role_id' => $this->roleBendahara->id,
        ]);

        $response = $this->actingAs($bendahara)
            ->delete(route('admin.roles.remove_user', [
                'role' => $this->roleBendahara->id,
                'user' => $otherUser->id,
            ]));

        $response->assertForbidden();
    }

    public function test_cannot_remove_user_who_does_not_have_role()
    {
        $user = User::factory()->create([
            'role_id' => $this->roleReguler->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->delete(route('admin.roles.remove_user', [
                'role' => $this->roleBendahara->id,
                'user' => $user->id,
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_role_show_includes_organization_in_user_table_data()
    {
        $unit = \App\Models\OrganizationUnit::factory()->create([
            'name' => 'DPK Test',
            'code' => '011',
        ]);

        User::factory()->create([
            'name' => 'Budi Test',
            'email' => 'budi.role@example.com',
            'role_id' => $this->roleBendahara->id,
            'organization_unit_id' => $unit->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->get(route('admin.roles.show', $this->roleBendahara->id));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Show')
            ->where('users.data.0.organization.name', 'DPK Test')
            ->where('users.data.0.organization.code', '011')
        );
    }
}
