<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\UnionPosition;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class LetterSlaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\LetterCategorySeeder::class);
    }

    /**
     * Test that submitted letter gets correct SLA due date for biasa urgency.
     */
    public function test_submitted_letter_gets_sla_due_date_for_biasa(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $member = Member::factory()->create(['organization_unit_id' => $unit->id]);

        $user = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
            'member_id' => $member->id,
        ]);

        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Test Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua',
            'from_unit_id' => $unit->id,
            'creator_user_id' => $user->id,
            'status' => 'draft',
        ]);

        // Simulate submit
        Carbon::setTestNow('2025-01-01 10:00:00');

        $letter->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'sla_due_at' => now()->addHours(Letter::getSlaHours('biasa')),
            'sla_status' => 'ok',
        ]);

        $this->assertEquals('2025-01-04 10:00:00', $letter->fresh()->sla_due_at->format('Y-m-d H:i:s')); // 72 hours later
        $this->assertEquals('ok', $letter->fresh()->sla_status);

        Carbon::setTestNow(); // Reset
    }

    /**
     * Test that submitted letter gets correct SLA due date for kilat urgency (4 hours).
     */
    public function test_submitted_letter_gets_sla_due_date_for_kilat(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Urgent Letter',
            'body' => 'Test body',
            'urgency' => 'kilat',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua',
            'from_unit_id' => $unit->id,
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id])->id,
            'status' => 'draft',
        ]);

        Carbon::setTestNow('2025-01-01 10:00:00');

        $letter->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'sla_due_at' => now()->addHours(Letter::getSlaHours('kilat')),
            'sla_status' => 'ok',
        ]);

        $this->assertEquals('2025-01-01 14:00:00', $letter->fresh()->sla_due_at->format('Y-m-d H:i:s')); // 4 hours later
        $this->assertEquals('ok', $letter->fresh()->sla_status);

        Carbon::setTestNow();
    }

    /**
     * Test is_overdue accessor returns true when past SLA.
     */
    public function test_is_overdue_returns_true_when_past_sla(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Overdue Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua',
            'from_unit_id' => $unit->id,
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id])->id,
            'status' => 'submitted',
            'submitted_at' => now()->subDays(5),
            'sla_due_at' => now()->subDays(2), // Already past
            'sla_status' => 'ok',
        ]);

        $this->assertTrue($letter->is_overdue);
    }

    /**
     * Test is_overdue returns false when within SLA.
     */
    public function test_is_overdue_returns_false_when_within_sla(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'On-time Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua',
            'from_unit_id' => $unit->id,
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id])->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'sla_due_at' => now()->addDays(2), // Still in future
            'sla_status' => 'ok',
        ]);

        $this->assertFalse($letter->is_overdue);
    }

    /**
     * Test SLA mark command marks overdue letters as breach.
     */
    public function test_sla_mark_command_marks_overdue_letters(): void
    {
        $unit = OrganizationUnit::factory()->create();

        // Create overdue letter
        $overdueLetter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Overdue Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua',
            'from_unit_id' => $unit->id,
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id])->id,
            'status' => 'submitted',
            'submitted_at' => now()->subDays(5),
            'sla_due_at' => now()->subDays(2),
            'sla_status' => 'ok',
        ]);

        // Create on-time letter
        $onTimeLetter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'On-time Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua',
            'from_unit_id' => $unit->id,
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id])->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'sla_due_at' => now()->addDays(2),
            'sla_status' => 'ok',
        ]);

        // Run command
        Artisan::call('letters:sla-mark');

        // Overdue should be breached
        $this->assertEquals('breach', $overdueLetter->fresh()->sla_status);
        $this->assertNotNull($overdueLetter->fresh()->sla_marked_at);

        // On-time should still be ok
        $this->assertEquals('ok', $onTimeLetter->fresh()->sla_status);
        $this->assertNull($onTimeLetter->fresh()->sla_marked_at);
    }

    /**
     * Test age_hours accessor returns correct value.
     */
    public function test_age_hours_returns_correct_hours_since_submission(): void
    {
        Carbon::setTestNow('2025-01-10 12:00:00');

        $unit = OrganizationUnit::factory()->create();
        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Aged Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua',
            'from_unit_id' => $unit->id,
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id])->id,
            'status' => 'submitted',
            'submitted_at' => Carbon::parse('2025-01-08 12:00:00'), // 48 hours ago
        ]);

        $this->assertEquals(48, $letter->age_hours);

        Carbon::setTestNow();
    }

    /**
     * Test SLA hours configuration is correct.
     */
    public function test_sla_hours_config_returns_correct_values(): void
    {
        $this->assertEquals(72, Letter::getSlaHours('biasa'));
        $this->assertEquals(24, Letter::getSlaHours('segera'));
        $this->assertEquals(4, Letter::getSlaHours('kilat'));
        $this->assertEquals(72, Letter::getSlaHours('unknown')); // Default fallback
    }
}
