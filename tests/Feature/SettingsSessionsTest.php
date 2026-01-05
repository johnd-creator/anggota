<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SettingsSessionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public function test_user_can_view_sessions()
    {
        $user = User::factory()->create();

        // Populate sessions table manually since it's database driver
        DB::table('sessions')->insert([
            ['id' => 'session1', 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Test Agent 1', 'payload' => 'data', 'last_activity' => time()],
            ['id' => 'session2', 'user_id' => $user->id, 'ip_address' => '127.0.0.2', 'user_agent' => 'Test Agent 2', 'payload' => 'data', 'last_activity' => time() - 100],
        ]);

        $response = $this->actingAs($user)->withSession(['session1'])->getJson('/settings/sessions');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'sessions'); // Returns 2 sessions

        // Verify structure
        $response->assertJsonStructure([
            'sessions' => [
                '*' => ['id', 'ip_address', 'user_agent', 'is_current_device', 'last_activity']
            ]
        ]);
    }

    public function test_user_can_revoke_other_sessions()
    {
        $user = User::factory()->create();
        $currentSessionId = 'current_session_id';

        // Insert multiple sessions
        DB::table('sessions')->insert([
            ['id' => $currentSessionId, 'user_id' => $user->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Current', 'payload' => 'data', 'last_activity' => time()],
            ['id' => 'other_session_1', 'user_id' => $user->id, 'ip_address' => '1.1.1.1', 'user_agent' => 'Other 1', 'payload' => 'data', 'last_activity' => time() - 3600],
            ['id' => 'other_session_2', 'user_id' => $user->id, 'ip_address' => '2.2.2.2', 'user_agent' => 'Other 2', 'payload' => 'data', 'last_activity' => time() - 7200],
        ]);

        // Manually set session ID for the request to mimic being on 'current_session_id'
        // In Laravel tests, usually actingAs sets the session. We need to ensure the request session ID matches 'current_session_id'.
        // We can simulate this by mocking the session ID or ensuring the driver uses the one we want.
        // However, standard actingAs creates a session. Let's try to align them.

        session()->setId($currentSessionId);
        // Note: interacting with session driver in tests can be tricky if using 'array' driver. 
        // This test assumes 'database' driver is active or we are mocking DB. 
        // If config session.driver is array, DB queries won't affect session persistence same way.
        // But the controller uses DB::table('sessions'), so we must ensure test uses database session driver or we mock DB.

        $response = $this->actingAs($user)->postJson('/settings/sessions/revoke-others');

        $response->assertStatus(200);

        // Check DB: Current session should exist, others deleted
        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId]);
        $this->assertDatabaseMissing('sessions', ['id' => 'other_session_1']);
        $this->assertDatabaseMissing('sessions', ['id' => 'other_session_2']);
    }
}
