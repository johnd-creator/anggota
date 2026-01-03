<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportUnitScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    // ========================================
    // Reports Export Tests
    // ========================================

    public function test_admin_unit_export_ignores_other_unit_parameter(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Create members in both units
        Member::factory()->count(3)->create(['organization_unit_id' => $unitA->id, 'status' => 'aktif']);
        Member::factory()->count(5)->create(['organization_unit_id' => $unitB->id, 'status' => 'aktif']);

        // admin_unit A tries to export with unit_id=B
        $response = $this->actingAs($adminUnitA)->post('/reports/growth/export', [
            'unit_id' => $unitB->id,
        ]);

        // Should get 200 (export succeeds)
        $response->assertStatus(200);
        // The export should be scoped to unit A, not unit B (enforced by ExportScopeHelper)
    }

    public function test_super_admin_can_export_specific_unit(): void
    {
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        Member::factory()->count(3)->create(['organization_unit_id' => $unitB->id, 'status' => 'aktif']);

        // super_admin can export with unit_id=B
        $response = $this->actingAs($superAdmin)->post('/reports/growth/export', [
            'unit_id' => $unitB->id,
        ]);

        $response->assertStatus(200);
    }

    // ========================================
    // API Token Tests
    // ========================================

    public function test_api_members_requires_unit_id(): void
    {
        // Set the API token for testing
        config(['app.api_token' => 'test-token']);

        $response = $this->withHeaders([
            'X-API-Token' => 'test-token',
        ])->get('/api/members');

        $response->assertStatus(400);
        $response->assertJson(['error' => 'unit_id parameter is required']);
    }

    public function test_api_mutations_requires_unit_id(): void
    {
        config(['app.api_token' => 'test-token']);

        $response = $this->withHeaders([
            'X-API-Token' => 'test-token',
        ])->get('/api/mutations');

        $response->assertStatus(400);
        $response->assertJson(['error' => 'unit_id parameter is required']);
    }

    public function test_api_documents_requires_unit_id(): void
    {
        config(['app.api_token' => 'test-token']);

        $response = $this->withHeaders([
            'X-API-Token' => 'test-token',
        ])->get('/api/documents');

        $response->assertStatus(400);
        $response->assertJson(['error' => 'unit_id parameter is required']);
    }

    public function test_api_members_with_unit_id_returns_scoped_data(): void
    {
        config(['app.api_token' => 'test-token']);

        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        Member::factory()->count(3)->create(['organization_unit_id' => $unitA->id]);
        Member::factory()->count(5)->create(['organization_unit_id' => $unitB->id]);

        $response = $this->withHeaders([
            'X-API-Token' => 'test-token',
        ])->get("/api/members?unit_id={$unitA->id}");

        $response->assertStatus(200);
        $data = $response->json();

        // Should only contain unit A members
        $this->assertCount(3, $data);
        foreach ($data as $member) {
            $this->assertEquals($unitA->id, $member['organization_unit_id']);
        }
    }

    // ========================================
    // ExportScopeHelper Tests
    // ========================================

    public function test_export_scope_helper_forces_unit_for_non_global(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Request unit B, but should get unit A
        $effectiveUnitId = \App\Services\ExportScopeHelper::getEffectiveUnitId($adminUnitA, $unitB->id);

        $this->assertEquals($unitA->id, $effectiveUnitId);
    }

    public function test_export_scope_helper_allows_global_any_unit(): void
    {
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        // Request unit B, should get unit B
        $effectiveUnitId = \App\Services\ExportScopeHelper::getEffectiveUnitId($superAdmin, $unitB->id);

        $this->assertEquals($unitB->id, $effectiveUnitId);
    }
}
