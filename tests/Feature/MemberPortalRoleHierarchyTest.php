<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MemberUpdateRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberPortalRoleHierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_unit_can_request_member_update(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->value('id'),
        ]);

        $member = Member::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        $response = $this->actingAs($user)->post('/member/portal/request-update', [
            'address' => 'New Address',
        ]);

        $response->assertStatus(302);

        $this->assertSame(1, MemberUpdateRequest::where('member_id', $member->id)->count());
    }

    public function test_bendahara_can_request_member_update(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->value('id'),
        ]);

        $member = Member::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        $response = $this->actingAs($user)->post('/member/portal/request-update', [
            'address' => 'Another Address',
        ]);

        $response->assertStatus(302);

        $this->assertSame(1, MemberUpdateRequest::where('member_id', $member->id)->count());
    }
}
