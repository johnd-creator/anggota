<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterApprover;
use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\UnionPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterApproverDelegationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\LetterCategorySeeder::class);
    }

    /**
     * Test that user in letter_approvers can approve even without union position.
     */
    public function test_delegated_user_without_union_position_can_approve(): void
    {
        $unit = OrganizationUnit::factory()->create();

        // Create a regular user with no union position
        $delegateUser = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
            'member_id' => null, // No member = no union position
        ]);

        // Add user as delegated approver for ketua
        LetterApprover::create([
            'organization_unit_id' => $unit->id,
            'signer_type' => 'ketua',
            'user_id' => $delegateUser->id,
            'is_active' => true,
        ]);

        // Create a submitted letter
        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Test Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua',
            'from_unit_id' => $unit->id,
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id, 'organization_unit_id' => $unit->id])->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Delegated user should be able to approve
        $response = $this->actingAs($delegateUser)->post("/letters/{$letter->id}/approve");

        // Should succeed (redirect to approvals)
        $response->assertRedirect();
        $this->assertEquals('approved', $letter->fresh()->status);
    }

    /**
     * Test that user with union position ketua can still approve (fallback).
     */
    public function test_union_position_ketua_can_still_approve_without_delegation(): void
    {
        $unit = OrganizationUnit::factory()->create();

        // Create union position Ketua
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

        $ketuaUser = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
            'member_id' => $member->id,
        ]);

        // No entry in letter_approvers - should still work via union position

        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Test Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua',
            'from_unit_id' => $unit->id,
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id, 'organization_unit_id' => $unit->id])->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($ketuaUser)->post("/letters/{$letter->id}/approve");

        $response->assertRedirect();
        $this->assertEquals('approved', $letter->fresh()->status);
    }

    /**
     * Test that inactive delegation does not allow approval.
     */
    public function test_inactive_delegation_does_not_allow_approval(): void
    {
        $unit = OrganizationUnit::factory()->create();

        $delegateUser = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
            'member_id' => null,
        ]);

        // Add user as INACTIVE approver
        LetterApprover::create([
            'organization_unit_id' => $unit->id,
            'signer_type' => 'ketua',
            'user_id' => $delegateUser->id,
            'is_active' => false,
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
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id, 'organization_unit_id' => $unit->id])->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($delegateUser)->post("/letters/{$letter->id}/approve");

        $response->assertStatus(403);
        $this->assertEquals('submitted', $letter->fresh()->status);
    }

    /**
     * Test cross-unit approval still blocked (403).
     */
    public function test_cross_unit_approval_still_blocked(): void
    {
        $unitA = OrganizationUnit::factory()->create();
        $unitB = OrganizationUnit::factory()->create();

        // Create delegated approver for unit A
        $delegateUser = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        LetterApprover::create([
            'organization_unit_id' => $unitA->id,
            'signer_type' => 'ketua',
            'user_id' => $delegateUser->id,
            'is_active' => true,
        ]);

        // Create letter from unit B
        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Unit B Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua',
            'from_unit_id' => $unitB->id, // Different unit!
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id, 'organization_unit_id' => $unitB->id])->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Unit A approver should NOT be able to approve unit B letter
        $response = $this->actingAs($delegateUser)->post("/letters/{$letter->id}/approve");

        $response->assertStatus(403);
    }

    /**
     * Test user with wrong signer_type delegation cannot approve.
     */
    public function test_wrong_signer_type_delegation_cannot_approve(): void
    {
        $unit = OrganizationUnit::factory()->create();

        $delegateUser = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
            'member_id' => null,
        ]);

        // Delegate as sekretaris
        LetterApprover::create([
            'organization_unit_id' => $unit->id,
            'signer_type' => 'sekretaris',
            'user_id' => $delegateUser->id,
            'is_active' => true,
        ]);

        // Letter requires ketua approval
        $letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Test Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'admin_pusat',
            'signer_type' => 'ketua', // Needs ketua, not sekretaris
            'from_unit_id' => $unit->id,
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id, 'organization_unit_id' => $unit->id])->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($delegateUser)->post("/letters/{$letter->id}/approve");

        $response->assertStatus(403);
    }

    /**
     * Test global admin (super_admin) can still approve any letter.
     */
    public function test_super_admin_can_approve_any_letter(): void
    {
        $unit = OrganizationUnit::factory()->create();

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
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
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'admin_unit')->first()->id, 'organization_unit_id' => $unit->id])->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($superAdmin)->post("/letters/{$letter->id}/approve");

        $response->assertRedirect();
        $this->assertEquals('approved', $letter->fresh()->status);
    }
}
