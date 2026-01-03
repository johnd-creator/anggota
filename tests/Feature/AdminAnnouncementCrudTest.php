<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAnnouncementCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles if necessary or mock them. Assuming Roles seeder exists or we create on fly.
        // For testing purposes, we'll create roles strictly if they don't exist.
        if (!Role::where('name', 'super_admin')->exists())
            Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        if (!Role::where('name', 'admin_pusat')->exists())
            Role::create(['name' => 'admin_pusat', 'label' => 'Admin Pusat']);
        if (!Role::where('name', 'admin_unit')->exists())
            Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);
    }

    public function test_admin_pusat_can_create_global_announcement()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'admin_pusat')->first()->id]);

        $response = $this->actingAs($user)->post(route('admin.announcements.store'), [
            'title' => 'Global Info',
            'body' => 'Content',
            'scope_type' => 'global_all',
            'is_active' => true,
        ]);

        $response->assertRedirect(route('admin.announcements.index'));
        $this->assertDatabaseHas('announcements', [
            'title' => 'Global Info',
            'scope_type' => 'global_all',
            'created_by' => $user->id,
        ]);
    }

    public function test_admin_unit_cannot_create_global_announcement()
    {
        $unit = OrganizationUnit::factory()->create();
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id
        ]);

        // Attempt to create global
        $response = $this->actingAs($user)->post(route('admin.announcements.store'), [
            'title' => 'Hacked Global',
            'body' => 'Content',
            'scope_type' => 'global_all', // Should be forced to 'unit' by Request or rejected
            // Our Request logic forces it to 'unit'. Let's see if validation fails or it saves as unit.
            // The logic: if admin_unit, force scope='unit'.
        ]);

        // It should redirect success, BUT the data in DB should be scope='unit'
        $response->assertRedirect(route('admin.announcements.index'));

        $this->assertDatabaseMissing('announcements', [
            'title' => 'Hacked Global',
            'scope_type' => 'global_all',
        ]);

        $this->assertDatabaseHas('announcements', [
            'title' => 'Hacked Global',
            'scope_type' => 'unit',
            'organization_unit_id' => $unit->id, // Enforced
        ]);
    }

    public function test_admin_unit_can_only_view_own_unit_announcements()
    {
        $unit1 = OrganizationUnit::factory()->create();
        $unit2 = OrganizationUnit::factory()->create();

        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit1->id
        ]);

        $ann1 = Announcement::create([
            'title' => 'Unit 1 Info',
            'body' => 'Body',
            'scope_type' => 'unit',
            'organization_unit_id' => $unit1->id,
            'created_by' => $user->id,
        ]);

        $ann2 = Announcement::create([
            'title' => 'Unit 2 Info',
            'body' => 'Body',
            'scope_type' => 'unit',
            'organization_unit_id' => $unit2->id,
            'created_by' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.announcements.index'));

        $response->assertOk();
        $response->assertInertia(
            fn($page) => $page
                ->has('announcements.data', 1)
                ->where('announcements.data.0.id', $ann1->id)
        );
    }
}
