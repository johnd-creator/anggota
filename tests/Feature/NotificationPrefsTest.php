<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\NotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPrefsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_notification_preferences_with_new_categories()
    {
        $user = User::factory()->create();

        $channels = [
            'mutations' => ['email' => true, 'inapp' => true],
            'announcements' => ['email' => true, 'inapp' => true],
            'dues' => ['email' => false, 'inapp' => true],
            'reports' => ['email' => true, 'inapp' => false],
            'finance' => ['email' => true, 'inapp' => true],
        ];

        $response = $this->actingAs($user)->patchJson('/settings/notifications', [
            'channels' => $channels,
            'digest_daily' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'digest_daily' => true,
        ]);

        $pref = NotificationPreference::where('user_id', $user->id)->first();
        $this->assertEquals($channels['announcements'], $pref->channels['announcements']);
        $this->assertEquals($channels['finance'], $pref->channels['finance']);
    }

    public function test_default_channels_helper()
    {
        // This unit test is inside feature test for convenience
        // Create a user with admin_unit role
        // For testing service logic, we might need to mock or trigger the real service logic
        // Since NotificationService::send calls getDefaultChannels internally, we can verify the side effect (ActivityLog or Mail)

        // However, standard unit testing the method if it was public would be easier. Use reflection or test indirect.
        // Let's test indirect: Send a notification for 'finance' category to a bendahara user who has NO prefs set.
        // It should default to email=true.

        $user = User::factory()->create();
        $role = \App\Models\Role::firstOrCreate(['name' => 'bendahara'], ['label' => 'Bendahara']);
        $user->role_id = $role->id;
        $user->save();

        // Verify default logic via reflection since method is private
        $method = new \ReflectionMethod(\App\Services\NotificationService::class, 'getDefaultChannels');
        $method->setAccessible(true);

        $defaults = $method->invoke(null, $user, 'finance');
        $this->assertTrue($defaults['email']); // Bendahara should have email enabled for finance

        $defaultsOther = $method->invoke(null, $user, 'random');
        $this->assertFalse($defaultsOther['email']); // Default base is email false
    }
}
