<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleSSOTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_google_sso_redirects_reguler_to_itworks()
    {
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('gid-001');
        $abstractUser->shouldReceive('getEmail')->andReturn('jane@gmail.com');
        $abstractUser->shouldReceive('getName')->andReturn('Jane Doe');
        $abstractUser->shouldReceive('getAvatar')->andReturn('http://example.com/avatar.jpg');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');
        $response->assertStatus(302);
        $response->assertRedirect(route('itworks'));

        $user = User::where('email', 'jane@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals(Role::where('name', 'reguler')->first()->id, $user->role_id);
    }

    public function test_google_sso_redirects_super_admin_to_dashboard()
    {
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('gid-002');
        $abstractUser->shouldReceive('getEmail')->andReturn('john@superadmin.com');
        $abstractUser->shouldReceive('getName')->andReturn('John Admin');
        $abstractUser->shouldReceive('getAvatar')->andReturn('http://example.com/avatar2.jpg');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');
        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'john@superadmin.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals(Role::where('name', 'super_admin')->first()->id, $user->role_id);
    }

    public function test_google_sso_links_to_existing_member_instead_of_onboarding()
    {
        $regulerRole = Role::where('name', 'reguler')->first();
        $anggotaRole = Role::where('name', 'anggota')->first();

        $unit = \App\Models\OrganizationUnit::factory()->create();
        $gen = \App\Services\NraGenerator::generate($unit->id, 2024);

        $existingMember = Member::factory()->create([
            'full_name' => 'Budi Santoso',
            'email' => 'budi@gmail.com',
            'nra' => $gen['nra'],
            'join_year' => 2024,
            'sequence_number' => $gen['sequence'],
            'organization_unit_id' => $unit->id,
            'status' => 'aktif',
        ]);

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('gid-003');
        $abstractUser->shouldReceive('getEmail')->andReturn('budi@gmail.com');
        $abstractUser->shouldReceive('getName')->andReturn('Budi Google');
        $abstractUser->shouldReceive('getAvatar')->andReturn('http://example.com/avatar3.jpg');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');
        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'budi@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($existingMember->id, $user->member_id);
        $this->assertEquals($anggotaRole->id, $user->role_id);

        $existingMember->refresh();
        $this->assertEquals($user->id, $existingMember->user_id);
        $this->assertEquals('Budi Santoso', $existingMember->full_name);

        $pendingMember = \App\Models\PendingMember::where('user_id', $user->id)->first();
        $this->assertNull($pendingMember);
    }

    public function test_google_sso_creates_onboarding_for_new_user()
    {
        $regulerRole = Role::where('name', 'reguler')->first();

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('gid-004');
        $abstractUser->shouldReceive('getEmail')->andReturn('newuser@gmail.com');
        $abstractUser->shouldReceive('getName')->andReturn('New User');
        $abstractUser->shouldReceive('getAvatar')->andReturn('http://example.com/avatar4.jpg');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/callback');
        $response->assertStatus(302);
        $response->assertRedirect(route('itworks'));

        $user = User::where('email', 'newuser@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals($regulerRole->id, $user->role_id);
        $this->assertNull($user->member_id);

        $pendingMember = \App\Models\PendingMember::where('user_id', $user->id)->first();
        $this->assertNotNull($pendingMember);
        $this->assertEquals('pending', $pendingMember->status);
    }
}
