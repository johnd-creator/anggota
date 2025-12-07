<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class AdminSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_middleware_tracks_user_session()
    {
        $role = Role::create(['name' => 'anggota', 'label' => 'Anggota']);
        $user = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($user)->get('/dashboard');

        $this->assertDatabaseHas('user_sessions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_admin_can_list_sessions()
    {
        $adminRole = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $userRole = Role::create(['name' => 'anggota', 'label' => 'Anggota']);
        $user = User::factory()->create(['role_id' => $userRole->id]);

        UserSession::create([
            'user_id' => $user->id,
            'session_id' => 'session_123',
            'ip' => '127.0.0.1',
            'user_agent' => 'TestAgent',
            'last_activity' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sessions.index'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Sessions/Index')
                ->has('sessions.data', 1)
                ->where('sessions.data.0.user_id', $user->id)
            );
    }

    public function test_admin_can_filter_sessions()
    {
        $adminRole = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $userRole = Role::create(['name' => 'anggota', 'label' => 'Anggota']);
        $user1 = User::factory()->create(['name' => 'Alice', 'role_id' => $userRole->id]);
        $user2 = User::factory()->create(['name' => 'Bob', 'role_id' => $userRole->id]);

        UserSession::create([
            'user_id' => $user1->id,
            'session_id' => 'session_1',
            'last_activity' => now(),
        ]);

        UserSession::create([
            'user_id' => $user2->id,
            'session_id' => 'session_2',
            'last_activity' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.sessions.index', ['search' => 'Alice']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('sessions.data', 1)
                ->where('sessions.data.0.user.name', 'Alice')
            );
    }

    public function test_admin_can_terminate_session()
    {
        $adminRole = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $userRole = Role::create(['name' => 'anggota', 'label' => 'Anggota']);
        $user = User::factory()->create(['role_id' => $userRole->id]);

        $session = UserSession::create([
            'user_id' => $user->id,
            'session_id' => 'session_to_kill',
            'last_activity' => now(),
        ]);

        // Simulate Laravel session
        if (config('session.driver') === 'database') {
            DB::table('sessions')->insert([
                'id' => 'session_to_kill',
                'user_id' => $user->id,
                'payload' => 'test',
                'last_activity' => time(),
            ]);
        }

        $this->actingAs($admin)
            ->delete(route('admin.sessions.destroy', $session->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('user_sessions', ['id' => $session->id]);
        
        if (config('session.driver') === 'database') {
            $this->assertDatabaseMissing('sessions', ['id' => 'session_to_kill']);
        }
    }

    public function test_admin_can_terminate_all_user_sessions()
    {
        $adminRole = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $userRole = Role::create(['name' => 'anggota', 'label' => 'Anggota']);
        $user = User::factory()->create(['role_id' => $userRole->id]);

        UserSession::create(['user_id' => $user->id, 'session_id' => 's1', 'last_activity' => now()]);
        UserSession::create(['user_id' => $user->id, 'session_id' => 's2', 'last_activity' => now()]);

        $this->actingAs($admin)
            ->delete(route('admin.sessions.destroy_user', $user->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('user_sessions', ['user_id' => $user->id]);
    }
}
