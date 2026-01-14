<?php

namespace Tests\Feature;

use App\Models\PendingMember;
use App\Models\Member;
use App\Models\User;
use App\Models\OrganizationUnit;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class OnboardingAutoDeleteRejectedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_google_sso_auto_deletes_rejected_pending_member()
    {
        $regulerRole = Role::where('name', 'reguler')->first();

        $user = User::factory()->create([
            'role_id' => $regulerRole->id,
        ]);

        // Create rejected pending member
        $rejectedPending = PendingMember::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'status' => 'rejected',
            'notes' => 'Test failed',
        ]);

        $this->assertDatabaseHas('pending_members', [
            'id' => $rejectedPending->id,
            'status' => 'rejected',
        ]);

        // Simulate SSO login
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('gid-001');
        $abstractUser->shouldReceive('getEmail')->andReturn($user->email);
        $abstractUser->shouldReceive('getName')->andReturn($user->name);
        $abstractUser->shouldReceive('getAvatar')->andReturn('http://example.com/avatar.jpg');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->actingAs($user)->get('/auth/google/callback');

        $response->assertStatus(302);
        $response->assertRedirect(route('itworks'));

        // Verify rejected pending was deleted
        $this->assertDatabaseMissing('pending_members', [
            'id' => $rejectedPending->id,
            'status' => 'rejected',
        ]);

        // Verify new pending was created
        $newPending = PendingMember::where('user_id', $user->id)->first();
        $this->assertNotNull($newPending);
        $this->assertEquals('pending', $newPending->status);

        // Verify activity log
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auto_delete_rejected_pending_member',
            'subject_id' => $rejectedPending->id,
        ]);
    }

    public function test_microsoft_sso_auto_deletes_rejected_pending_member()
    {
        $regulerRole = Role::where('name', 'reguler')->first();

        $user = User::factory()->create([
            'role_id' => $regulerRole->id,
            'email' => 'test@plnipservices.co.id',
        ]);

        // Create rejected pending member
        $rejectedPending = PendingMember::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'status' => 'rejected',
            'notes' => 'Invalid data',
        ]);

        $this->assertDatabaseHas('pending_members', [
            'id' => $rejectedPending->id,
            'status' => 'rejected',
        ]);

        // Simulate Microsoft SSO login
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('mid-001');
        $abstractUser->shouldReceive('getEmail')->andReturn($user->email);
        $abstractUser->shouldReceive('getName')->andReturn($user->name);
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);

        $response = $this->actingAs($user)->get('/auth/microsoft/callback');

        $response->assertStatus(302);
        $response->assertRedirect(route('itworks'));

        // Verify rejected pending was deleted
        $this->assertDatabaseMissing('pending_members', [
            'id' => $rejectedPending->id,
            'status' => 'rejected',
        ]);

        // Verify new pending was created
        $newPending = PendingMember::where('user_id', $user->id)->first();
        $this->assertNotNull($newPending);
        $this->assertEquals('pending', $newPending->status);

        // Verify activity log
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auto_delete_rejected_pending_member',
            'subject_id' => $rejectedPending->id,
        ]);
    }

    public function test_auto_delete_creates_new_pending_after_member_imported()
    {
        $regulerRole = Role::where('name', 'reguler')->first();
        $anggotaRole = Role::where('name', 'anggota')->first();

        $unit = \App\Models\OrganizationUnit::factory()->create();
        $gen = \App\Services\NraGenerator::generate($unit->id, 2024);

        // Create member (import scenario)
        $member = Member::factory()->create([
            'email' => 'existing@plnipservices.co.id',
            'nra' => $gen['nra'],
            'organization_unit_id' => $unit->id,
        ]);

        // Create rejected pending member
        $user = User::factory()->create([
            'role_id' => $regulerRole->id,
            'email' => 'existing@plnipservices.co.id',
        ]);

        $rejectedPending = PendingMember::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'status' => 'rejected',
            'notes' => 'Import failed',
        ]);

        // Simulate Microsoft SSO login
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('mid-002');
        $abstractUser->shouldReceive('getEmail')->andReturn($user->email);
        $abstractUser->shouldReceive('getName')->andReturn($user->name);
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);

        $response = $this->actingAs($user)->get('/auth/microsoft/callback');

        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));

        // Verify rejected pending was deleted
        $this->assertDatabaseMissing('pending_members', [
            'id' => $rejectedPending->id,
        ]);

        // Verify user was linked to existing member
        $user->refresh();
        $this->assertEquals($member->id, $user->member_id);
        $this->assertEquals($anggotaRole->id, $user->role_id);

        // Verify activity log for linkage
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user_linked_to_existing_member',
            'subject_id' => $member->id,
        ]);

        // Verify activity log for auto-delete
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auto_delete_rejected_pending_member',
        ]);
    }

    public function test_auto_delete_skips_when_no_rejected_pending()
    {
        $regulerRole = Role::where('name', 'reguler')->first();

        $user = User::factory()->create([
            'role_id' => $regulerRole->id,
        ]);

        // Create pending member (not rejected)
        $existingPending = PendingMember::factory()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'status' => 'pending',
        ]);

        // Simulate Google SSO login
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('gid-003');
        $abstractUser->shouldReceive('getEmail')->andReturn($user->email);
        $abstractUser->shouldReceive('getName')->andReturn($user->name);
        $abstractUser->shouldReceive('getAvatar')->andReturn('http://example.com/avatar.jpg');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->actingAs($user)->get('/auth/google/callback');

        $response->assertStatus(302);
        $response->assertRedirect(route('itworks'));

        // Verify existing pending still exists (not deleted)
        $this->assertDatabaseHas('pending_members', [
            'id' => $existingPending->id,
            'status' => 'pending',
        ]);

        // No new pending created (uses existing)
        $pendings = PendingMember::where('user_id', $user->id)->get();
        $this->assertCount(1, $pendings);
    }

    public function test_auto_delete_skips_when_member_exists()
    {
        $regulerRole = Role::where('name', 'reguler')->first();
        $anggotaRole = Role::where('name', 'anggota')->first();

        $unit = \App\Models\OrganizationUnit::factory()->create();
        $gen = \App\Services\NraGenerator::generate($unit->id, 2024);

        // Create member
        $member = Member::factory()->create([
            'email' => 'withmember@gmail.com',
            'nra' => $gen['nra'],
            'organization_unit_id' => $unit->id,
        ]);

        // Create user linked to member
        $user = User::factory()->create([
            'role_id' => $regulerRole->id,
            'email' => 'withmember@gmail.com',
            'member_id' => $member->id,
        ]);

        // Simulate Google SSO login
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('gid-004');
        $abstractUser->shouldReceive('getEmail')->andReturn($user->email);
        $abstractUser->shouldReceive('getName')->andReturn($user->name);
        $abstractUser->shouldReceive('getAvatar')->andReturn('http://example.com/avatar.jpg');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->actingAs($user)->get('/auth/google/callback');

        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));

        // Verify no new pending created
        $pendings = PendingMember::where('user_id', $user->id)->get();
        $this->assertCount(0, $pendings);
    }

    public function test_onboarding_index_can_filter_by_rejected_status()
    {
        $adminUnitRole = Role::where('name', 'admin_unit')->first();

        $unit = OrganizationUnit::factory()->create();

        $pending = PendingMember::factory()->create([
            'email' => 'user@gmail.com',
            'status' => 'rejected',
            'notes' => 'Test rejection',
        ]);

        $adminUnitUser = User::factory()->create([
            'role_id' => $adminUnitRole->id,
            'organization_unit_id' => $unit->id,
        ]);

        $this->actingAs($adminUnitUser);

        $response = $this->get('/admin/onboarding?status=rejected');

        $response->assertStatus(200);

        $items = $response->viewData('page')['props']['items'];
        $this->assertCount(1, $items['data']);
        $this->assertEquals('rejected', $items['data'][0]['status']);
        $this->assertEquals('Test rejection', $items['data'][0]['notes']);
    }

    public function test_onboarding_index_defaults_to_pending_status()
    {
        $adminUnitRole = Role::where('name', 'admin_unit')->first();

        $unit = OrganizationUnit::factory()->create();

        PendingMember::factory()->create([
            'email' => 'pending1@gmail.com',
            'status' => 'pending',
        ]);

        PendingMember::factory()->create([
            'email' => 'pending2@gmail.com',
            'status' => 'pending',
        ]);

        PendingMember::factory()->create([
            'email' => 'rejected@gmail.com',
            'status' => 'rejected',
            'notes' => 'Invalid',
        ]);

        $adminUnitUser = User::factory()->create([
            'role_id' => $adminUnitRole->id,
            'organization_unit_id' => $unit->id,
        ]);

        $this->actingAs($adminUnitUser);

        $response = $this->get('/admin/onboarding');

        $response->assertStatus(200);

        $items = $response->viewData('page')['props']['items'];
        $this->assertCount(2, $items['data']); // Only pending, not rejected
        foreach ($items['data'] as $item) {
            $this->assertEquals('pending', $item['status']);
        }
    }
}
