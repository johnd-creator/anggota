<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnnouncementsVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure roles exist
        foreach (['admin_pusat', 'admin_unit', 'anggota', 'bendahara'] as $r) {
            if (!Role::where('name', $r)->exists())
                Role::create(['name' => $r, 'label' => ucfirst($r)]);
        }
    }

    public function test_dashboard_pinned_announcements_respect_scope()
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $userUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // 1. Global All Pinned (Should see)
        $globalAll = Announcement::create([
            'title' => 'Global All',
            'body' => 'Test',
            'scope_type' => 'global_all',
            'is_active' => true,
            'pin_to_dashboard' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        // 2. Global Officers Pinned (Should NOT see as anggota)
        $globalOfficers = Announcement::create([
            'title' => 'Global Officers',
            'body' => 'Test',
            'scope_type' => 'global_officers',
            'is_active' => true,
            'pin_to_dashboard' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        // 3. Unit A Pinned (Should see)
        $unitAPinned = Announcement::create([
            'title' => 'Unit A Info',
            'body' => 'Test',
            'scope_type' => 'unit',
            'organization_unit_id' => $unitA->id,
            'is_active' => true,
            'pin_to_dashboard' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        // 4. Unit B Pinned (Should NOT see)
        $unitBPinned = Announcement::create([
            'title' => 'Unit B Info',
            'body' => 'Test',
            'scope_type' => 'unit',
            'organization_unit_id' => $unitB->id,
            'is_active' => true,
            'pin_to_dashboard' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        // 5. Global All NOT Pinned (Should NOT see in dashboard props)
        $globalNotPinned = Announcement::create([
            'title' => 'Global Unpinned',
            'body' => 'Test',
            'scope_type' => 'global_all',
            'is_active' => true,
            'pin_to_dashboard' => false,
            'created_by' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($userUnitA)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Dashboard')
                ->has('announcements_pinned', 2) // Global All + Unit A
                ->where('announcements_pinned.0.id', $unitAPinned->id) // Latest first usually, or check contains
                ->where('announcements_pinned.1.id', $globalAll->id)
        );
    }

    public function test_public_index_list_respects_scope()
    {
        $unitA = OrganizationUnit::factory()->create();
        $userUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Visible
        Announcement::create([
            'title' => 'Visible 1',
            'scope_type' => 'global_all',
            'is_active' => true,
            'created_by' => 1
        ]);

        // Invisible (Inactive)
        Announcement::create([
            'title' => 'Inactive',
            'scope_type' => 'global_all',
            'is_active' => false,
            'created_by' => 1
        ]);

        $response = $this->actingAs($userUnitA)->get(route('announcements.index'));

        $response->assertOk();
        $response->assertInertia(
            fn(Assert $page) => $page
                ->component('Announcements/Index')
                ->has('announcements.data', 1)
                ->where('announcements.data.0.title', 'Visible 1')
        );
    }
}
