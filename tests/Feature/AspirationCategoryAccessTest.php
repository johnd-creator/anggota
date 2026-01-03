<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AspirationCategoryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_unit_cannot_access_aspiration_categories_index(): void
    {
        $unit = OrganizationUnit::factory()->create();

        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $response = $this->actingAs($adminUnit)->get('/admin/aspiration-categories');

        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_aspiration_categories_index(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $response = $this->actingAs($superAdmin)->get('/admin/aspiration-categories');

        $response->assertStatus(200);
    }

    public function test_admin_unit_cannot_create_aspiration_category(): void
    {
        $unit = OrganizationUnit::factory()->create();

        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $response = $this->actingAs($adminUnit)->post('/admin/aspiration-categories', [
            'name' => 'Test Category',
        ]);

        $response->assertStatus(403);
    }

    public function test_bendahara_cannot_access_aspiration_categories(): void
    {
        $unit = OrganizationUnit::factory()->create();

        $bendahara = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $response = $this->actingAs($bendahara)->get('/admin/aspiration-categories');

        $response->assertStatus(403);
    }
}
