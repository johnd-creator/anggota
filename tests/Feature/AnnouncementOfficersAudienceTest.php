<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\UnionPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for global_officers audience based on union_position (not role).
 */
class AnnouncementOfficersAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required roles
        foreach (['super_admin', 'admin_pusat', 'admin_unit', 'anggota', 'bendahara'] as $r) {
            if (!Role::where('name', $r)->exists()) {
                Role::create(['name' => $r, 'label' => ucfirst(str_replace('_', ' ', $r))]);
            }
        }
    }

    /**
     * Test: User with union_position='Anggota' cannot see global_officers announcements.
     */
    public function test_user_with_anggota_position_cannot_see_global_officers(): void
    {
        // Create union positions
        $posAnggota = UnionPosition::create(['name' => 'Anggota', 'code' => 'ANG']);
        $unit = OrganizationUnit::factory()->create();

        // Create member with 'Anggota' position
        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'union_position_id' => $posAnggota->id,
        ]);

        // Create user linked to this member
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'organization_unit_id' => $unit->id,
            'member_id' => $member->id,
        ]);

        // Create global_officers announcement
        $announcement = Announcement::create([
            'title' => 'Officers Only Info',
            'body' => 'Test',
            'scope_type' => 'global_officers',
            'is_active' => true,
            'pin_to_dashboard' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        // Assert: User cannot see the announcement in public list
        $response = $this->actingAs($user)->get('/announcements');
        $response->assertOk();

        // Check via visibleTo scope
        $visible = Announcement::visibleTo($user)->where('id', $announcement->id)->exists();
        $this->assertFalse($visible, 'User with Anggota position should not see global_officers');

        // Assert: User cannot see in dashboard pinned
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertOk();
        $this->assertNotContains(
            $announcement->id,
            collect($response->original->getData()['page']['props']['announcements_pinned'] ?? [])->pluck('id')->all()
        );
    }

    /**
     * Test: User with union_position='Ketua' can see global_officers announcements.
     */
    public function test_user_with_ketua_position_can_see_global_officers(): void
    {
        // Create union positions
        $posKetua = UnionPosition::create(['name' => 'Ketua', 'code' => 'KET']);
        $unit = OrganizationUnit::factory()->create();

        // Create member with 'Ketua' position
        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'union_position_id' => $posKetua->id,
        ]);

        // Create user linked to this member
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id, // Role is anggota but position is Ketua
            'organization_unit_id' => $unit->id,
            'member_id' => $member->id,
        ]);

        // Create global_officers announcement
        $announcement = Announcement::create([
            'title' => 'Officers Only Info',
            'body' => 'Test',
            'scope_type' => 'global_officers',
            'is_active' => true,
            'pin_to_dashboard' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        // Assert: User CAN see the announcement
        $visible = Announcement::visibleTo($user)->where('id', $announcement->id)->exists();
        $this->assertTrue($visible, 'User with Ketua position should see global_officers');
    }

    /**
     * Test: User with no member/position cannot see global_officers.
     */
    public function test_user_without_member_cannot_see_global_officers(): void
    {
        // User with anggota role but no member linkage
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'member_id' => null,
        ]);

        // Create global_officers announcement
        $announcement = Announcement::create([
            'title' => 'Officers Only Info',
            'body' => 'Test',
            'scope_type' => 'global_officers',
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        // Assert: User cannot see the announcement
        $visible = Announcement::visibleTo($user)->where('id', $announcement->id)->exists();
        $this->assertFalse($visible, 'User without member should not see global_officers');
    }

    /**
     * Test: Super admin can always see global_officers.
     */
    public function test_super_admin_can_always_see_global_officers(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
            'member_id' => null, // No member linkage
        ]);

        $announcement = Announcement::create([
            'title' => 'Officers Only',
            'body' => 'Test',
            'scope_type' => 'global_officers',
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        $visible = Announcement::visibleTo($user)->where('id', $announcement->id)->exists();
        $this->assertTrue($visible, 'Super admin should always see global_officers');
    }

    /**
     * Test: Admin pusat can always see global_officers.
     */
    public function test_admin_pusat_can_always_see_global_officers(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_pusat')->first()->id,
            'member_id' => null,
        ]);

        $announcement = Announcement::create([
            'title' => 'Officers Only',
            'body' => 'Test',
            'scope_type' => 'global_officers',
            'is_active' => true,
            'created_by' => User::factory()->create()->id,
        ]);

        $visible = Announcement::visibleTo($user)->where('id', $announcement->id)->exists();
        $this->assertTrue($visible, 'Admin pusat should always see global_officers');
    }

    /**
     * Test: isOfficer returns correct values.
     */
    public function test_is_officer_method(): void
    {
        $posAnggota = UnionPosition::create(['name' => 'Anggota', 'code' => 'ANG']);
        $posKetua = UnionPosition::create(['name' => 'Ketua', 'code' => 'KET']);
        $unit = OrganizationUnit::factory()->create();

        // Member with Anggota position
        $memberAnggota = Member::factory()->create([
            'union_position_id' => $posAnggota->id,
        ]);
        $userAnggota = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'member_id' => $memberAnggota->id,
        ]);

        // Member with Ketua position
        $memberKetua = Member::factory()->create([
            'union_position_id' => $posKetua->id,
        ]);
        $userKetua = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'member_id' => $memberKetua->id,
        ]);

        // Super admin without member
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
            'member_id' => null,
        ]);

        $this->assertFalse($userAnggota->isOfficer(), 'Anggota position should not be officer');
        $this->assertTrue($userKetua->isOfficer(), 'Ketua position should be officer');
        $this->assertTrue($superAdmin->isOfficer(), 'Super admin should always be officer');
    }
}
