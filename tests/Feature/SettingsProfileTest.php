<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_name()
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)->patchJson('/settings/profile', [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function test_user_updating_profile_updates_member_full_name()
    {
        $user = User::factory()->create(['name' => 'Old Name']);
        $member = Member::factory()->create([
            'user_id' => $user->id,
            'full_name' => 'Old Name',
        ]);
        // Link member to user properly
        $user->member_id = $member->id;
        $user->save();

        $response = $this->actingAs($user)->patchJson('/settings/profile', [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'full_name' => 'Updated Name',
        ]);
    }

    public function test_validation_requires_name()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patchJson('/settings/profile', [
            'name' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
