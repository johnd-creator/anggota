<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnnouncementDismissalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin_pusat', 'admin_unit', 'anggota', 'bendahara', 'super_admin'] as $r) {
            if (!Role::where('name', $r)->exists()) {
                Role::create(['name' => $r, 'label' => ucfirst($r)]);
            }
        }
    }

    public function test_user_can_dismiss_pinned_announcement_on_dashboard()
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);

        $userA = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $announcement = Announcement::create([
            'title' => 'Pinned Global',
            'body' => 'Test',
            'scope_type' => 'global_all',
            'is_active' => true,
            'pin_to_dashboard' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        $this->actingAs($userA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Dashboard')
                ->has('announcements_pinned', 1)
                ->where('announcements_pinned.0.id', $announcement->id)
            );

        $this->actingAs($userA)
            ->post(route('announcements.dismiss', $announcement))
            ->assertStatus(302);

        $this->actingAs($userA)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Dashboard')
                ->has('announcements_pinned', 0)
            );
    }

    public function test_dismissal_is_per_user()
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);

        $userA = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);
        $userB = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $announcement = Announcement::create([
            'title' => 'Pinned Global',
            'body' => 'Test',
            'scope_type' => 'global_all',
            'is_active' => true,
            'pin_to_dashboard' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        $this->actingAs($userA)
            ->post(route('announcements.dismiss', $announcement))
            ->assertStatus(302);

        $this->actingAs($userB)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn(Assert $page) => $page
                ->component('Dashboard')
                ->has('announcements_pinned', 1)
                ->where('announcements_pinned.0.id', $announcement->id)
            );
    }
}

