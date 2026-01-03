<?php

namespace Tests\Feature;

use App\Models\DuesPayment;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberDuesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'anggota', 'label' => 'Anggota']);
    }

    public function test_user_with_member_sees_dues_history(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'aktif',
        ]);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'member_id' => $member->id,
        ]);

        // Create some dues payments
        DuesPayment::create([
            'member_id' => $member->id,
            'organization_unit_id' => $unit->id,
            'period' => now()->format('Y-m'),
            'status' => 'paid',
            'amount' => 30000,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/member/dues');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Member/Dues')
                ->has('hasMember')
                ->where('hasMember', true)
                ->has('payments')
                ->has('summary')
        );
    }

    public function test_user_without_member_sees_empty_state(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'member_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/member/dues');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Member/Dues')
                ->where('hasMember', false)
        );
    }

    public function test_dashboard_contains_my_dues_for_member(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'aktif',
        ]);
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'member_id' => $member->id,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->has('my_dues')
                ->has('my_dues.current_period')
                ->has('my_dues.current_status')
                ->has('my_dues.unpaid_count')
        );
    }

    public function test_dashboard_my_dues_null_for_user_without_member(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'member_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->where('my_dues', null)
        );
    }
}
