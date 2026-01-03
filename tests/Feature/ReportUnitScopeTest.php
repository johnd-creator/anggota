<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportUnitScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_unit_sees_only_own_unit_in_growth_report(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        Member::factory()->count(3)->create(['organization_unit_id' => $unitA->id, 'join_date' => now()]);
        Member::factory()->count(5)->create(['organization_unit_id' => $unitB->id, 'join_date' => now()]);

        $response = $this->actingAs($adminUnitA)->get('/reports/growth?unit_id=' . $unitB->id);

        $response->assertStatus(200);

        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Reports/Growth')
                ->where('kpi.total', 3) // Should only count Unit A members (3)
                ->where('filters.unit_id', $unitA->id) // Filter should be rewritten to Unit A
        );
    }

    public function test_admin_unit_sees_only_own_unit_in_documents_report(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Create members with photos (complete)
        Member::factory()->create(['organization_unit_id' => $unitA->id, 'photo_path' => 'path/to/photo.jpg']);
        Member::factory()->create(['organization_unit_id' => $unitB->id, 'photo_path' => 'path/to/photo.jpg']);

        $response = $this->actingAs($adminUnitA)->get('/reports/documents?unit_id=' . $unitB->id);

        $response->assertStatus(200);

        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Reports/Documents')
                ->where('kpi.complete', 1) // Should only count Unit A complete member
                ->where('filters.unit_id', $unitA->id)
                ->has('items.data', 1) // Should only list 1 item from Unit A
                ->where('items.data.0.organization_unit_id', $unitA->id)
        );
    }

    public function test_super_admin_can_view_report_for_specific_unit(): void
    {
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        Member::factory()->count(5)->create(['organization_unit_id' => $unitB->id, 'join_date' => now()]);

        $response = $this->actingAs($superAdmin)->get('/reports/growth?unit_id=' . $unitB->id);

        $response->assertStatus(200);

        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Reports/Growth')
                ->where('kpi.total', 5)
                ->where('filters.unit_id', $unitB->id)
        );
    }
}
