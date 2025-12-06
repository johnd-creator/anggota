<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\OrganizationUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationUnitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_super_admin_can_view_units()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id]);
        OrganizationUnit::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.units.index'));
        $response->assertStatus(200);
    }

    public function test_admin_unit_can_view_units()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id]);
        OrganizationUnit::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.units.index'));
        $response->assertStatus(200);
    }

    public function test_reguler_cannot_view_units()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'reguler')->first()->id]);

        $response = $this->actingAs($user)->get(route('admin.units.index'));
        // Should be redirected to itworks by middleware or 403 by policy if middleware passed (but middleware catches first)
        $response->assertRedirect(route('itworks'));
    }

    public function test_super_admin_can_create_unit()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id]);

        $response = $this->actingAs($user)->post(route('admin.units.store'), [
            'code' => '123',
            'name' => 'Test Unit',
            'address' => 'Test Address 123',
        ]);

        $response->assertRedirect(route('admin.units.index'));
        $this->assertDatabaseHas('organization_units', ['code' => '123']);
    }

    public function test_admin_unit_cannot_create_unit()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id]);

        $response = $this->actingAs($user)->post(route('admin.units.store'), [
            'code' => '123',
            'name' => 'Test Unit',
            'address' => 'Test Address 123',
        ]);

        $response->assertStatus(403);
    }
}
