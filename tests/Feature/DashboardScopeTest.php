<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\MutationRequest;
use App\Models\OrganizationUnit;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class DashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_all_recent_activity_and_mutations()
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'super_admin'], ['label' => 'Super Admin']);
        $superAdmin->role_id = $role->id;
        $superAdmin->save();

        // Create mutations
        MutationRequest::factory()->create(['from_unit_id' => $unitA->id, 'status' => 'pending']);
        MutationRequest::factory()->create(['from_unit_id' => $unitB->id, 'status' => 'pending']);

        // Create audit logs
        AuditLog::factory()->create(['organization_unit_id' => $unitA->id, 'event' => 'test_event']);
        AuditLog::factory()->create(['organization_unit_id' => $unitB->id, 'event' => 'test_event']);

        $response = $this->actingAs($superAdmin)->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Dashboard')
                    ->has('dashboard.recent_mutations', 2)
                    ->has('dashboard.recent_activities', 2)
            );
    }

    public function test_admin_unit_sees_only_own_unit_data()
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create(['organization_unit_id' => $unitA->id]);
        $role = Role::firstOrCreate(['name' => 'admin_unit'], ['label' => 'Admin Unit']);
        $adminUnitA->role_id = $role->id;
        $adminUnitA->save();

        // Create mutations
        MutationRequest::factory()->create(['from_unit_id' => $unitA->id, 'status' => 'pending']);
        MutationRequest::factory()->create(['from_unit_id' => $unitB->id, 'status' => 'pending']); // Should not see this

        // Create audit logs
        AuditLog::factory()->create(['organization_unit_id' => $unitA->id, 'event' => 'test_event']);
        AuditLog::factory()->create(['organization_unit_id' => $unitB->id, 'event' => 'test_event']); // Should not see this

        $response = $this->actingAs($adminUnitA)->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(
                fn(Assert $page) => $page
                    ->component('Dashboard')
                    ->has('dashboard.recent_mutations', 1)
                    ->where('dashboard.recent_mutations.0.type', 'Mutasi')
                    ->has('dashboard.recent_activities', 1)
            );
    }
}
