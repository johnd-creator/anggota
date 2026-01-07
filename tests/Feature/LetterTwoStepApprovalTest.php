<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\UnionPosition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test two-step approval flow (signer_type_secondary = bendahara).
 * Per L3 Prompt: Verifies primary approve stays submitted, secondary approve finalizes.
 */
class LetterTwoStepApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected $ketua;
    protected $bendahara;
    protected $adminUnit;
    protected $unit;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdminUnit = Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);
        $roleAnggota = Role::create(['name' => 'anggota', 'label' => 'Anggota']);
        $roleBendahara = Role::create(['name' => 'bendahara', 'label' => 'Bendahara']);

        $positionKetua = UnionPosition::create(['name' => 'Ketua', 'code' => 'KTU']);
        $positionBendahara = UnionPosition::create(['name' => 'Bendahara', 'code' => 'BND']);

        $this->unit = OrganizationUnit::factory()->create(['code' => '010']);

        $this->adminUnit = User::factory()->create([
            'role_id' => $roleAdminUnit->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        // Create Ketua user
        $memberKetua = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'union_position_id' => $positionKetua->id,
        ]);
        $this->ketua = User::factory()->create([
            'role_id' => $roleAnggota->id,
            'member_id' => $memberKetua->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        // Create Bendahara user
        $memberBendahara = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'union_position_id' => $positionBendahara->id,
        ]);
        $this->bendahara = User::factory()->create([
            'role_id' => $roleBendahara->id,
            'member_id' => $memberBendahara->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        $this->category = LetterCategory::create([
            'name' => 'Undangan',
            'code' => 'UND',
            'is_active' => true,
        ]);
    }

    protected function createTwoStepLetter(): Letter
    {
        return Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'signer_type_secondary' => 'bendahara', // Two-step approval
            'to_type' => 'admin_pusat',
            'subject' => 'Two Step Approval Test',
            'body' => 'Test body content',
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    
    public function test_primary_approval_does_not_finalize_when_secondary_required()
    {
        $letter = $this->createTwoStepLetter();

        // Primary approver (Ketua) approves
        $response = $this->actingAs($this->ketua)
            ->post(route('letters.approve', $letter->id));

        $response->assertRedirect(route('letters.approvals'));

        $letter->refresh();

        // Status should STILL be submitted
        $this->assertEquals('submitted', $letter->status);

        // Primary approval fields should be set
        $this->assertEquals($this->ketua->id, $letter->approved_by_user_id);
        $this->assertNotNull($letter->approved_primary_at);

        // Final approval fields should NOT be set yet
        $this->assertNull($letter->approved_at);
        $this->assertNull($letter->letter_number);

        // Secondary fields should still be null
        $this->assertNull($letter->approved_secondary_by_user_id);
        $this->assertNull($letter->approved_secondary_at);
    }

    
    public function test_secondary_approval_finalizes_and_generates_number()
    {
        $letter = $this->createTwoStepLetter();

        // Step 1: Primary approval
        $this->actingAs($this->ketua)
            ->post(route('letters.approve', $letter->id));

        $letter->refresh();
        $this->assertEquals('submitted', $letter->status);
        $this->assertTrue($letter->isPrimaryApproved());

        // Step 2: Secondary approval (Bendahara)
        $response = $this->actingAs($this->bendahara)
            ->post(route('letters.approve', $letter->id));

        $response->assertRedirect(route('letters.approvals'));

        $letter->refresh();

        // NOW should be approved
        $this->assertEquals('approved', $letter->status);

        // Secondary fields should be set
        $this->assertEquals($this->bendahara->id, $letter->approved_secondary_by_user_id);
        $this->assertNotNull($letter->approved_secondary_at);

        // Final approval fields should be set
        $this->assertNotNull($letter->approved_at);
        $this->assertNotNull($letter->letter_number);
        $this->assertStringStartsWith('001/', $letter->letter_number);

        // Both flags should be true
        $this->assertTrue($letter->isPrimaryApproved());
        $this->assertTrue($letter->isSecondaryApproved());
    }

    
    public function test_bendahara_cannot_approve_before_primary_is_done()
    {
        $letter = $this->createTwoStepLetter();

        // Bendahara tries to approve first (before Ketua)
        $response = $this->actingAs($this->bendahara)
            ->post(route('letters.approve', $letter->id));

        // Should be forbidden - primary slot is for Ketua
        $response->assertStatus(403);

        // DB should not change
        $letter->refresh();
        $this->assertEquals('submitted', $letter->status);
        $this->assertNull($letter->approved_by_user_id);
        $this->assertNull($letter->approved_secondary_by_user_id);
        $this->assertFalse($letter->isPrimaryApproved());
    }

    
    public function test_primary_cannot_approve_twice_or_approve_secondary_slot()
    {
        $letter = $this->createTwoStepLetter();

        // Step 1: Ketua approves primary
        $this->actingAs($this->ketua)
            ->post(route('letters.approve', $letter->id));

        $letter->refresh();
        $this->assertTrue($letter->isPrimaryApproved());
        $this->assertEquals('submitted', $letter->status);

        // Step 2: Ketua tries to approve again (should fail - secondary is for Bendahara)
        $response = $this->actingAs($this->ketua)
            ->post(route('letters.approve', $letter->id));

        // Ketua cannot approve secondary slot
        $response->assertStatus(403);

        // Letter should remain in same state
        $letter->refresh();
        $this->assertEquals('submitted', $letter->status);
        $this->assertFalse($letter->isSecondaryApproved());
        $this->assertNull($letter->approved_secondary_by_user_id);
    }

    
    public function test_two_step_letter_returns_true_for_requires_secondary_approval()
    {
        $letter = $this->createTwoStepLetter();

        $this->assertTrue($letter->requiresSecondaryApproval());
    }

    
    public function test_helper_methods_reflect_approval_state_transitions()
    {
        $letter = $this->createTwoStepLetter();

        // Initial state
        $this->assertTrue($letter->requiresSecondaryApproval());
        $this->assertFalse($letter->isPrimaryApproved());
        $this->assertFalse($letter->isSecondaryApproved());

        // After primary approval
        $this->actingAs($this->ketua)->post(route('letters.approve', $letter->id));
        $letter->refresh();
        $this->assertTrue($letter->isPrimaryApproved());
        $this->assertFalse($letter->isSecondaryApproved());
        $this->assertEquals('submitted', $letter->status);

        // After secondary approval
        $this->actingAs($this->bendahara)->post(route('letters.approve', $letter->id));
        $letter->refresh();
        $this->assertTrue($letter->isPrimaryApproved());
        $this->assertTrue($letter->isSecondaryApproved());
        $this->assertEquals('approved', $letter->status);
    }

    
    public function test_fully_approved_two_step_letter_cannot_be_approved_again()
    {
        $letter = $this->createTwoStepLetter();

        // Complete both approvals
        $this->actingAs($this->ketua)->post(route('letters.approve', $letter->id));
        $this->actingAs($this->bendahara)->post(route('letters.approve', $letter->id));

        $letter->refresh();
        $this->assertEquals('approved', $letter->status);
        $originalLetterNumber = $letter->letter_number;

        // Try to approve again (by Ketua)
        $response = $this->actingAs($this->ketua)
            ->post(route('letters.approve', $letter->id));

        $response->assertStatus(403);

        // Try to approve again (by Bendahara)
        $response = $this->actingAs($this->bendahara)
            ->post(route('letters.approve', $letter->id));

        $response->assertStatus(403);

        // Letter number should not change
        $letter->refresh();
        $this->assertEquals($originalLetterNumber, $letter->letter_number);
    }
}
