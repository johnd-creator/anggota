<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\NotificationPreference;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\UnionPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class LetterNotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\LetterCategorySeeder::class);
    }

    /**
     * Test user with channels.letters=false does NOT receive notification.
     */
    public function test_user_with_letters_disabled_does_not_receive_notification(): void
    {
        $unit = OrganizationUnit::factory()->create();

        // Create Ketua position
        $ketuaPosition = UnionPosition::firstOrCreate(
            ['name' => 'Ketua'],
            ['level' => 1]
        );

        // Create member with Ketua position
        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'union_position_id' => $ketuaPosition->id,
            'status' => 'aktif',
        ]);

        // Create approver (Ketua) with letters disabled
        $approver = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
            'member_id' => $member->id,
        ]);

        // Disable letters channel
        NotificationPreference::create([
            'user_id' => $approver->id,
            'channels' => ['letters' => false],
            'digest_daily' => false,
        ]);

        // Create letter creator
        $creator = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        // Create and submit letter
        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Test Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua',
            'from_unit_id' => $unit->id,
            'creator_user_id' => $creator->id,
            'status' => 'draft',
        ]);

        // Submit the letter (this triggers notifyApprover)
        $response = $this->actingAs($creator)->post("/letters/{$letter->id}/submit");

        $response->assertRedirect();

        // Approver should NOT have received notification (letters disabled)
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $approver->id,
            'type' => \App\Notifications\LetterSubmittedNotification::class,
        ]);
    }

    /**
     * Test user with letters enabled (default) receives notification.
     */
    public function test_user_with_letters_enabled_receives_notification(): void
    {
        $unit = OrganizationUnit::factory()->create();

        // Create Ketua position
        $ketuaPosition = UnionPosition::firstOrCreate(
            ['name' => 'Ketua'],
            ['level' => 1]
        );

        // Create member with Ketua position
        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'union_position_id' => $ketuaPosition->id,
            'status' => 'aktif',
        ]);

        // Create approver (Ketua) with letters enabled (default - no preference record)
        $approver = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
            'member_id' => $member->id,
        ]);

        // Create letter creator
        $creator = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        // Create and submit letter
        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Test Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua',
            'from_unit_id' => $unit->id,
            'creator_user_id' => $creator->id,
            'status' => 'draft',
        ]);

        // Submit the letter (this triggers notifyApprover)
        $response = $this->actingAs($creator)->post("/letters/{$letter->id}/submit");

        $response->assertRedirect();

        // Approver SHOULD receive notification (default enabled)
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $approver->id,
            'type' => \App\Notifications\LetterSubmittedNotification::class,
        ]);
    }

    /**
     * Test isChannelEnabled static helper with no preference record.
     */
    public function test_is_channel_enabled_returns_true_when_no_preference(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
        ]);

        // No preference record exists
        $this->assertTrue(NotificationPreference::isChannelEnabled($user->id, 'letters'));
    }

    /**
     * Test isChannelEnabled with explicit false.
     */
    public function test_is_channel_enabled_returns_false_when_disabled(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
        ]);

        NotificationPreference::create([
            'user_id' => $user->id,
            'channels' => ['letters' => false],
        ]);

        $this->assertFalse(NotificationPreference::isChannelEnabled($user->id, 'letters'));
    }

    /**
     * Test other channels remain unaffected when letters is disabled.
     */
    public function test_other_channels_unaffected_when_letters_disabled(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
        ]);

        NotificationPreference::create([
            'user_id' => $user->id,
            'channels' => ['letters' => false, 'mutations' => true],
        ]);

        $this->assertFalse(NotificationPreference::isChannelEnabled($user->id, 'letters'));
        $this->assertTrue(NotificationPreference::isChannelEnabled($user->id, 'mutations'));
    }
}
