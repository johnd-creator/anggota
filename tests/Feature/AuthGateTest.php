<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_guest_is_redirected_to_login()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_dashboard()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_reguler_user_is_redirected_to_itworks()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'reguler')->first()->id]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertRedirect(route('itworks'));
    }

    public function test_root_redirects_to_login_for_guest()
    {
        $response = $this->get('/');
        $response->assertStatus(200); // Inertia page
        $response->assertInertia(fn($page) => $page->component('Auth/Login'));
    }

    public function test_root_redirects_to_dashboard_for_auth_user()
    {
        $user = User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id]);

        $response = $this->actingAs($user)->get('/');
        $response->assertRedirect(route('dashboard'));
    }
}
