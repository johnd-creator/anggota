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
 * Test approvals queue visibility for two-step approval flow.
 * Per L3 Prompt: Verifies correct queue visibility per approval stage and unit scope.
 */
class LetterApprovalsQueueTest extends TestCase
{
    use RefreshDatabase;

    protected $ketuaUnitA;
    protected $bendaharaUnitA;
    protected $ketuaUnitB;
    protected $adminUnitA;
    protected $unitA;
    protected $unitB;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        $roleAdminUnit = Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);
        $roleAnggota = Role::create(['name' => 'anggota', 'label' => 'Anggota']);
        $roleBendahara = Role::create(['name' => 'bendahara', 'label' => 'Bendahara']);

        $positionKetua = UnionPosition::create(['name' => 'Ketua', 'code' => 'KTU']);
        $positionBendahara = UnionPosition::create(['name' => 'Bendahara', 'code' => 'BND']);

        // Create two units
        $this->unitA = OrganizationUnit::factory()->create(['code' => '010', 'name' => 'Unit A']);
        $this->unitB = OrganizationUnit::factory()->create(['code' => '020', 'name' => 'Unit B']);

        // Unit A admin
        $this->adminUnitA = User::factory()->create([
            'role_id' => $roleAdminUnit->id,
            'organization_unit_id' => $this->unitA->id,
        ]);

        // Ketua Unit A
        $memberKetuaA = Member::factory()->create([
            'organization_unit_id' => $this->unitA->id,
            'union_position_id' => $positionKetua->id,
        ]);
        $this->ketuaUnitA = User::factory()->create([
            'role_id' => $roleAnggota->id,
            'member_id' => $memberKetuaA->id,
            'organization_unit_id' => $this->unitA->id,
        ]);

        // Bendahara Unit A
        $memberBendaharaA = Member::factory()->create([
            'organization_unit_id' => $this->unitA->id,
            'union_position_id' => $positionBendahara->id,
        ]);
        $this->bendaharaUnitA = User::factory()->create([
            'role_id' => $roleBendahara->id,
            'member_id' => $memberBendaharaA->id,
            'organization_unit_id' => $this->unitA->id,
        ]);

        // Ketua Unit B
        $memberKetuaB = Member::factory()->create([
            'organization_unit_id' => $this->unitB->id,
            'union_position_id' => $positionKetua->id,
        ]);
        $this->ketuaUnitB = User::factory()->create([
            'role_id' => $roleAnggota->id,
            'member_id' => $memberKetuaB->id,
            'organization_unit_id' => $this->unitB->id,
        ]);

        $this->category = LetterCategory::create([
            'name' => 'Undangan',
            'code' => 'UND',
            'is_active' => true,
        ]);
    }

    protected function createTwoStepLetterForUnitA(): Letter
    {
        return Letter::create([
            'creator_user_id' => $this->adminUnitA->id,
            'from_unit_id' => $this->unitA->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'signer_type_secondary' => 'bendahara',
            'to_type' => 'admin_pusat',
            'subject' => 'Two Step Queue Test',
            'body' => 'Test body',
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    
    public function test_ketua_sees_two_step_letter_when_primary_pending()
    {
        $letter = $this->createTwoStepLetterForUnitA();

        $response = $this->actingAs($this->ketuaUnitA)
            ->get(route('letters.approvals'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Approvals')
                ->has('letters.data', 1)
                ->where('letters.data.0.id', $letter->id)
        );
    }

    
    public function test_bendahara_does_not_see_two_step_letter_when_primary_pending()
    {
        $letter = $this->createTwoStepLetterForUnitA();

        // Primary is not yet approved, so Bendahara should not see it
        $response = $this->actingAs($this->bendaharaUnitA)
            ->get(route('letters.approvals'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Approvals')
                ->has('letters.data', 0)
        );
    }

    
    public function test_after_primary_approval_ketua_no_longer_sees_letter()
    {
        $letter = $this->createTwoStepLetterForUnitA();

        // Primary approval by Ketua
        $this->actingAs($this->ketuaUnitA)
            ->post(route('letters.approve', $letter->id));

        // Now Ketua should NOT see it (waiting for secondary)
        $response = $this->actingAs($this->ketuaUnitA)
            ->get(route('letters.approvals'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Approvals')
                ->has('letters.data', 0)
        );
    }

    
    public function test_after_primary_approval_bendahara_sees_letter()
    {
        $letter = $this->createTwoStepLetterForUnitA();

        // Primary approval by Ketua
        $this->actingAs($this->ketuaUnitA)
            ->post(route('letters.approve', $letter->id));

        // Now Bendahara SHOULD see it
        $response = $this->actingAs($this->bendaharaUnitA)
            ->get(route('letters.approvals'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Approvals')
                ->has('letters.data', 1)
                ->where('letters.data.0.id', $letter->id)
        );
    }

    
    public function test_unit_b_ketua_cannot_see_unit_a_letters()
    {
        $letter = $this->createTwoStepLetterForUnitA();

        // Ketua from Unit B should NOT see letters from Unit A
        $response = $this->actingAs($this->ketuaUnitB)
            ->get(route('letters.approvals'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Approvals')
                ->has('letters.data', 0)
        );
    }

    
    public function test_fully_approved_letter_not_shown_in_queue()
    {
        $letter = $this->createTwoStepLetterForUnitA();

        // Complete both approvals
        $this->actingAs($this->ketuaUnitA)
            ->post(route('letters.approve', $letter->id));
        $this->actingAs($this->bendaharaUnitA)
            ->post(route('letters.approve', $letter->id));

        $letter->refresh();
        $this->assertEquals('approved', $letter->status);

        // Neither Ketua nor Bendahara should see it
        $responseKetua = $this->actingAs($this->ketuaUnitA)
            ->get(route('letters.approvals'));
        $responseKetua->assertInertia(
            fn($page) => $page->has('letters.data', 0)
        );

        $responseBendahara = $this->actingAs($this->bendaharaUnitA)
            ->get(route('letters.approvals'));
        $responseBendahara->assertInertia(
            fn($page) => $page->has('letters.data', 0)
        );
    }
}
