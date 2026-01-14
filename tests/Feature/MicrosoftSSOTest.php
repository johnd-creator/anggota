<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class MicrosoftSSOTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_microsoft_sso_redirects_reguler_to_onboarding()
    {
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('mid-001');
        $abstractUser->shouldReceive('getEmail')->andReturn('reguler@plnipservices.co.id');
        $abstractUser->shouldReceive('getName')->andReturn('Reguler User');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);

        $response = $this->get('/auth/microsoft/callback');
        $response->assertStatus(302);
        $response->assertRedirect(route('itworks'));

        $user = User::where('email', 'reguler@plnipservices.co.id')->first();
        $this->assertNotNull($user);
        $this->assertEquals(Role::where('name', 'reguler')->first()->id, $user->role_id);
    }

    public function test_microsoft_sso_links_to_existing_member_instead_of_onboarding()
    {
        $regulerRole = Role::where('name', 'reguler')->first();
        $anggotaRole = Role::where('name', 'anggota')->first();

        $unit = \App\Models\OrganizationUnit::factory()->create();
        $gen = \App\Services\NraGenerator::generate($unit->id, 2024);

        $existingMember = Member::factory()->create([
            'full_name' => 'Dewi Lestari',
            'email' => 'dewi@plnipservices.co.id',
            'nra' => $gen['nra'],
            'join_year' => 2024,
            'sequence_number' => $gen['sequence'],
            'organization_unit_id' => $unit->id,
            'status' => 'aktif',
        ]);

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('mid-002');
        $abstractUser->shouldReceive('getEmail')->andReturn('dewi@plnipservices.co.id');
        $abstractUser->shouldReceive('getName')->andReturn('Dewi Microsoft');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);

        $response = $this->get('/auth/microsoft/callback');
        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'dewi@plnipservices.co.id')->first();
        $this->assertNotNull($user);
        $this->assertEquals($existingMember->id, $user->member_id);
        $this->assertEquals($anggotaRole->id, $user->role_id);

        $existingMember->refresh();
        $this->assertEquals($user->id, $existingMember->user_id);
        $this->assertEquals('Dewi Lestari', $existingMember->full_name);

        $pendingMember = \App\Models\PendingMember::where('user_id', $user->id)->first();
        $this->assertNull($pendingMember);
    }

    public function test_microsoft_sso_creates_onboarding_for_new_user()
    {
        $regulerRole = Role::where('name', 'reguler')->first();

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('mid-003');
        $abstractUser->shouldReceive('getEmail')->andReturn('new@plnipservices.co.id');
        $abstractUser->shouldReceive('getName')->andReturn('New User');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);

        $response = $this->get('/auth/microsoft/callback');
        $response->assertStatus(302);
        $response->assertRedirect(route('itworks'));

        $user = User::where('email', 'new@plnipservices.co.id')->first();
        $this->assertNotNull($user);
        $this->assertEquals($regulerRole->id, $user->role_id);
        $this->assertNull($user->member_id);

        $pendingMember = \App\Models\PendingMember::where('user_id', $user->id)->first();
        $this->assertNotNull($pendingMember);
        $this->assertEquals('pending', $pendingMember->status);
    }

    public function test_microsoft_sso_enforces_domain_restriction()
    {
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $abstractUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $abstractUser->shouldReceive('getId')->andReturn('mid-004');
        $abstractUser->shouldReceive('getEmail')->andReturn('invalid@gmail.com');
        $abstractUser->shouldReceive('getName')->andReturn('Invalid Domain');
        $provider->shouldReceive('user')->andReturn($abstractUser);
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);

        $response = $this->get('/auth/microsoft/callback');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);

        $user = User::where('email', 'invalid@gmail.com')->first();
        $this->assertNull($user);
    }
}
