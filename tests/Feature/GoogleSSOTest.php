<?php

namespace Tests\Feature;

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
}
