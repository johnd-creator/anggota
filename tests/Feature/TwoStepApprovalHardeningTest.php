<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\LetterApprover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoStepApprovalHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationUnit $unit;
    protected LetterCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = OrganizationUnit::factory()->create();
        $this->category = LetterCategory::firstOrCreate(
            ['code' => 'UND'],
            ['name' => 'Undangan', 'is_active' => true]
        );
    }

    protected function createUser(string $roleName, string $roleLabel): User
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            ['label' => $roleLabel]
        );
        return User::factory()->create([
            'role_id' => $role->id,
            'organization_unit_id' => $this->unit->id,
        ]);
    }

    public function test_stage1_approval_does_not_change_status_to_approved(): void
    {
        $creator = $this->createUser('admin_unit', 'Admin Unit');
        $ketua = $this->createUser('ketua', 'Ketua');

        // Register ketua as approver
        LetterApprover::create([
            'organization_unit_id' => $this->unit->id,
            'signer_type' => 'ketua',
            'user_id' => $ketua->id,
            'is_active' => true,
        ]);

        // Create letter with two-step approval
        $letter = Letter::factory()->create([
            'creator_user_id' => $creator->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'signer_type' => 'ketua',
            'signer_type_secondary' => 'bendahara',
        ]);

        // Stage 1 approval
        $response = $this->actingAs($ketua)->post("/letters/{$letter->id}/approve");

        // Should redirect on success, 403 on authorization failure
        $response->assertRedirect();

        $letter->refresh();

        // Status should still be 'submitted', not 'approved'
        $this->assertEquals('submitted', $letter->status);
        $this->assertNotNull($letter->approved_by_user_id);
        $this->assertNotNull($letter->approved_primary_at);
        $this->assertNull($letter->letter_number);
    }

    public function test_superadmin_cannot_send_letter_before_final_approval(): void
    {
        $creator = $this->createUser('admin_unit', 'Admin Unit');
        $superadmin = $this->createUser('super_admin', 'Super Admin');
        $ketua = $this->createUser('ketua', 'Ketua');

        // Register ketua as approver
        LetterApprover::create([
            'organization_unit_id' => $this->unit->id,
            'signer_type' => 'ketua',
            'user_id' => $ketua->id,
            'is_active' => true,
        ]);

        // Create letter with two-step approval, with stage 1 done
        $letter = Letter::factory()->create([
            'creator_user_id' => $creator->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'status' => 'submitted', // Still submitted, not approved
            'submitted_at' => now(),
            'signer_type' => 'ketua',
            'signer_type_secondary' => 'bendahara',
            'approved_by_user_id' => $ketua->id,
            'approved_primary_at' => now(),
        ]);

        // Superadmin should NOT be able to send (status != approved)
        $response = $this->actingAs($superadmin)->post("/letters/{$letter->id}/send");

        $response->assertForbidden();

        $letter->refresh();
        $this->assertEquals('submitted', $letter->status);
    }

    public function test_superadmin_cannot_archive_letter_before_final_approval(): void
    {
        $creator = $this->createUser('admin_unit', 'Admin Unit');
        $superadmin = $this->createUser('super_admin', 'Super Admin');

        // Create letter still in submitted status
        $letter = Letter::factory()->create([
            'creator_user_id' => $creator->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        // Superadmin should NOT be able to archive (status != approved/sent)
        $response = $this->actingAs($superadmin)->post("/letters/{$letter->id}/archive");

        $response->assertForbidden();

        $letter->refresh();
        $this->assertEquals('submitted', $letter->status);
    }

    public function test_stage2_approval_changes_status_to_approved(): void
    {
        $creator = $this->createUser('admin_unit', 'Admin Unit');
        $ketua = $this->createUser('ketua', 'Ketua');
        $bendahara = $this->createUser('bendahara', 'Bendahara');

        // Register approvers
        LetterApprover::create([
            'organization_unit_id' => $this->unit->id,
            'signer_type' => 'ketua',
            'user_id' => $ketua->id,
            'is_active' => true,
        ]);

        LetterApprover::create([
            'organization_unit_id' => $this->unit->id,
            'signer_type' => 'bendahara',
            'user_id' => $bendahara->id,
            'is_active' => true,
        ]);

        // Create letter with primary already approved
        $letter = Letter::factory()->create([
            'creator_user_id' => $creator->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'signer_type' => 'ketua',
            'signer_type_secondary' => 'bendahara',
            'approved_by_user_id' => $ketua->id,
            'approved_primary_at' => now(),
        ]);

        // Stage 2 approval by bendahara
        $this->actingAs($bendahara)->post("/letters/{$letter->id}/approve");

        $letter->refresh();

        // Now status should be 'approved'
        $this->assertEquals('approved', $letter->status);
        $this->assertNotNull($letter->approved_secondary_by_user_id);
        $this->assertNotNull($letter->approved_secondary_at);
        $this->assertNotNull($letter->letter_number);
    }
}
