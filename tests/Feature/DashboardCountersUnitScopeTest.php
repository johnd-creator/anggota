<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberUpdateRequest;
use App\Models\MutationRequest;
use App\Models\OrganizationUnit;
use App\Models\PendingMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardCountersUnitScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_unit_sees_only_own_unit_counters(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Create members in both units
        Member::factory()->count(3)->create(['organization_unit_id' => $unitA->id]);
        Member::factory()->count(5)->create(['organization_unit_id' => $unitB->id]);

        // Create pending mutations involving different units
        $memberA = Member::factory()->create(['organization_unit_id' => $unitA->id]);
        $memberB = Member::factory()->create(['organization_unit_id' => $unitB->id]);

        MutationRequest::factory()->create([
            'member_id' => $memberA->id,
            'from_unit_id' => $unitA->id,
            'to_unit_id' => $unitB->id,
            'status' => 'pending',
        ]);
        MutationRequest::factory()->create([
            'member_id' => $memberB->id,
            'from_unit_id' => $unitB->id,
            'to_unit_id' => $unitA->id,
            'status' => 'pending',
        ]);
        // Mutation not involving unit A
        MutationRequest::factory()->create([
            'member_id' => $memberB->id,
            'from_unit_id' => $unitB->id,
            'to_unit_id' => $unitB->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($adminUnitA)->get('/dashboard');

        $response->assertStatus(200);

        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Dashboard')
                // members_total should be unit A count (3 + 1 memberA = 4)
                ->where('counters.members_total', 4)
                // units_total for non-global should be 1
                ->where('counters.units_total', 1)
                // mutations_pending: 2 involve unit A (from or to)
                ->where('counters.mutations_pending', 2)
                ->where('counters.is_global', false)
        );
    }

    public function test_super_admin_sees_global_counters(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        Member::factory()->count(3)->create(['organization_unit_id' => $unitA->id]);
        Member::factory()->count(5)->create(['organization_unit_id' => $unitB->id]);

        $response = $this->actingAs($superAdmin)->get('/dashboard');

        $response->assertStatus(200);

        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Dashboard')
                // members_total should be global count (8)
                ->where('counters.members_total', 8)
                // units_total should be 2
                ->where('counters.units_total', 2)
                ->where('counters.is_global', true)
        );
    }

    public function test_admin_unit_sees_only_own_unit_dashboard_alerts(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Create members with missing docs in both units
        Member::factory()->create(['organization_unit_id' => $unitA->id, 'photo_path' => null]);
        Member::factory()->create(['organization_unit_id' => $unitB->id, 'photo_path' => null]);
        Member::factory()->create(['organization_unit_id' => $unitB->id, 'photo_path' => null]);

        $response = $this->actingAs($adminUnitA)->get('/dashboard');

        $response->assertStatus(200);

        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Dashboard')
                // documents_missing should only count unit A (1)
                ->where('alerts.documents_missing', 1)
                // login_fail_same_ip should be 0 for non-global
                ->where('alerts.login_fail_same_ip', 0)
        );
    }

    public function test_admin_unit_dashboard_growth_stats_scoped(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Create members with join dates this month
        Member::factory()->count(2)->create([
            'organization_unit_id' => $unitA->id,
            'join_date' => now(),
        ]);
        Member::factory()->count(4)->create([
            'organization_unit_id' => $unitB->id,
            'join_date' => now(),
        ]);

        $response = $this->actingAs($adminUnitA)->get('/dashboard');

        $response->assertStatus(200);

        // The growth_last_12 series for current month should only have unit A count (2)
        $currentMonth = now()->format('Y-m');
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Dashboard')
                ->has('dashboard.growth_last_12', 12)
        );

        // Get the growth data and verify current month value
        $growthData = $response->original->getData()['page']['props']['dashboard']['growth_last_12'];
        $currentMonthData = collect($growthData)->firstWhere('label', $currentMonth);
        $this->assertEquals(2, $currentMonthData['value']);
    }

    public function test_super_admin_dashboard_members_by_unit_global(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        Member::factory()->count(3)->create(['organization_unit_id' => $unitA->id, 'status' => 'aktif']);
        Member::factory()->count(5)->create(['organization_unit_id' => $unitB->id, 'status' => 'aktif']);

        $response = $this->actingAs($superAdmin)->get('/dashboard');

        $response->assertStatus(200);

        // Should have both units in members_by_unit
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Dashboard')
                ->has('dashboard.members_by_unit', 2)
        );
    }
}
