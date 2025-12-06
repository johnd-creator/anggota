<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
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
}
