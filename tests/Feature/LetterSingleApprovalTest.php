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
 * Test single approval flow (signer_type_secondary = null).
 * Per L3 Prompt: Verifies 1x approve → status=approved, letter_number generated.
 */
class LetterSingleApprovalTest extends TestCase
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

        // Create Ketua user with union position
        $memberKetua = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'union_position_id' => $positionKetua->id,
        ]);
        $this->ketua = User::factory()->create([
            'role_id' => $roleAnggota->id,
            'member_id' => $memberKetua->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        // Create Bendahara user with union position
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

    protected function createSingleApprovalLetter(): Letter
    {
        return Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'signer_type_secondary' => null, // Single approval
            'to_type' => 'admin_pusat',
            'subject' => 'Single Approval Test',
            'body' => 'Test body content',
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    
    public function test_ketua_can_approve_single_approval_letter_and_finalize()
    {
        $letter = $this->createSingleApprovalLetter();

        $response = $this->actingAs($this->ketua)
            ->post(route('letters.approve', $letter->id));

        $response->assertRedirect(route('letters.approvals'));

        $letter->refresh();

        // Status should be approved immediately
        $this->assertEquals('approved', $letter->status);

        // Approval fields should be set
        $this->assertEquals($this->ketua->id, $letter->approved_by_user_id);
        $this->assertNotNull($letter->approved_at);

        // Letter number should be generated
        $this->assertNotNull($letter->letter_number);
        $this->assertStringStartsWith('001/', $letter->letter_number);

        // Secondary fields should remain null (single approval)
        $this->assertNull($letter->approved_secondary_by_user_id);
        $this->assertNull($letter->approved_secondary_at);
    }

    
    public function test_bendahara_cannot_approve_single_approval_letter_when_not_matching_signer_type()
    {
        $letter = $this->createSingleApprovalLetter();

        // Bendahara tries to approve a letter with signer_type = ketua
        $response = $this->actingAs($this->bendahara)
            ->post(route('letters.approve', $letter->id));

        $response->assertStatus(403);

        // Letter should not change
        $letter->refresh();
        $this->assertEquals('submitted', $letter->status);
        $this->assertNull($letter->approved_by_user_id);
        $this->assertNull($letter->letter_number);
    }

    
    public function test_single_approval_letter_returns_false_for_requires_secondary_approval()
    {
        $letter = $this->createSingleApprovalLetter();

        $this->assertFalse($letter->requiresSecondaryApproval());
        $this->assertFalse($letter->isPrimaryApproved());
        $this->assertFalse($letter->isSecondaryApproved());
    }

    
    public function test_single_approval_sets_approved_at_on_first_approve()
    {
        $letter = $this->createSingleApprovalLetter();

        $this->actingAs($this->ketua)
            ->post(route('letters.approve', $letter->id));

        $letter->refresh();

        // Both approved_at and approved_by_user_id should be set
        $this->assertNotNull($letter->approved_at);
        $this->assertEquals($this->ketua->id, $letter->approved_by_user_id);

        // approved_primary_at should be null (single approval uses approved_at directly)
        $this->assertNull($letter->approved_primary_at);
    }

    
    public function test_approved_letter_cannot_be_approved_again()
    {
        $letter = $this->createSingleApprovalLetter();

        // First approval
        $this->actingAs($this->ketua)
            ->post(route('letters.approve', $letter->id));

        $letter->refresh();
        $this->assertEquals('approved', $letter->status);
        $originalLetterNumber = $letter->letter_number;

        // Try to approve again
        $response = $this->actingAs($this->ketua)
            ->post(route('letters.approve', $letter->id));

        // Should fail (not submitted status)
        $response->assertStatus(403);

        // Letter should remain unchanged
        $letter->refresh();
        $this->assertEquals($originalLetterNumber, $letter->letter_number);
    }
}
