<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\UnionPosition;
use App\Models\User;
use App\Services\LetterNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class LetterApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $adminUnit;
    protected $ketua;
    protected $sekretaris;
    protected $anggota;
    protected $unit;
    protected $category;
    protected $categoryB;

    protected function setUp(): void
    {
        parent::setUp();

        $roleSuperAdmin = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $roleAdminUnit = Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);
        $roleAnggota = Role::create(['name' => 'anggota', 'label' => 'Anggota']);

        $positionKetua = UnionPosition::create(['name' => 'Ketua', 'code' => 'KTU']);
        $positionSekretaris = UnionPosition::create(['name' => 'Sekretaris', 'code' => 'SEK']);

        $this->unit = OrganizationUnit::factory()->create(['code' => '010']);

        $this->superAdmin = User::factory()->create([
            'role_id' => $roleSuperAdmin->id,
        ]);

        $this->adminUnit = User::factory()->create([
            'role_id' => $roleAdminUnit->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        // Create Ketua member
        $memberKetua = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'union_position_id' => $positionKetua->id,
        ]);
        $this->ketua = User::factory()->create([
            'role_id' => $roleAnggota->id,
            'member_id' => $memberKetua->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        // Create Sekretaris member
        $memberSekretaris = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'union_position_id' => $positionSekretaris->id,
        ]);
        $this->sekretaris = User::factory()->create([
            'role_id' => $roleAnggota->id,
            'member_id' => $memberSekretaris->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        // Regular anggota (no position)
        $memberAnggota = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
        ]);
        $this->anggota = User::factory()->create([
            'role_id' => $roleAnggota->id,
            'member_id' => $memberAnggota->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        $this->category = LetterCategory::create([
            'name' => 'Undangan',
            'code' => 'UND',
            'is_active' => true,
        ]);

        $this->categoryB = LetterCategory::create([
            'name' => 'Surat Keputusan',
            'code' => 'SK',
            'is_active' => true,
        ]);
    }

    protected function createSubmittedLetter(string $signerType = 'ketua'): Letter
    {
        return Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => $signerType,
            'to_type' => 'unit',
            'to_unit_id' => $this->unit->id,
            'subject' => 'Test Letter',
            'body' => 'Test body',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    protected function createSubmittedLetterToMember(string $signerType = 'ketua'): Letter
    {
        return Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => $signerType,
            'to_type' => 'member',
            'to_member_id' => $this->anggota->member_id,
            'subject' => 'Test Letter Member',
            'body' => 'Test body',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function test_numbering_first_letter_gets_sequence_001()
    {
        $letter = $this->createSubmittedLetter();
        $service = new LetterNumberService();

        $service->assignNumber($letter);

        $this->assertEquals(1, $letter->sequence);
        $this->assertStringStartsWith('001/UND/010/SP-PIPS/', $letter->letter_number);
    }

    public function test_numbering_second_letter_gets_sequence_002()
    {
        // First letter
        $letter1 = $this->createSubmittedLetter();
        $service = new LetterNumberService();
        $service->assignNumber($letter1);

        // Second letter
        $letter2 = $this->createSubmittedLetter();
        $service->assignNumber($letter2);

        $this->assertEquals(2, $letter2->sequence);
        $this->assertStringStartsWith('002/UND/010/SP-PIPS/', $letter2->letter_number);
    }

    public function test_numbering_different_category_starts_at_001()
    {
        // First letter with category UND
        $letter1 = $this->createSubmittedLetter();
        $service = new LetterNumberService();
        $service->assignNumber($letter1);
        $this->assertEquals(1, $letter1->sequence);

        // Second letter with category SK
        $letter2 = Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->categoryB->id,
            'signer_type' => 'ketua',
            'to_type' => 'unit',
            'to_unit_id' => $this->unit->id,
            'subject' => 'SK Test',
            'body' => 'Test',
            'status' => 'submitted',
        ]);
        $service->assignNumber($letter2);

        $this->assertEquals(1, $letter2->sequence);
        $this->assertStringStartsWith('001/SK/010/SP-PIPS/', $letter2->letter_number);
    }

    public function test_numbering_resets_per_year()
    {
        // Letter in 2025
        $letter1 = $this->createSubmittedLetter();
        $service = new LetterNumberService();
        $service->assignNumber($letter1, now()->setYear(2025));

        // Letter in 2026
        $letter2 = $this->createSubmittedLetter();
        $service->assignNumber($letter2, now()->setYear(2026));

        $this->assertEquals(1, $letter2->sequence);
        $this->assertEquals(2026, $letter2->year);
    }

    public function test_ketua_can_approve_signer_type_ketua()
    {
        $letter = $this->createSubmittedLetter('ketua');

        $response = $this->actingAs($this->ketua)
            ->post(route('letters.approve', $letter->id));

        $response->assertRedirect(route('letters.approvals'));
        $letter->refresh();
        $this->assertEquals('approved', $letter->status);
        $this->assertNotNull($letter->letter_number);
    }

    public function test_sekretaris_cannot_approve_signer_type_ketua()
    {
        $letter = $this->createSubmittedLetter('ketua');

        $response = $this->actingAs($this->sekretaris)
            ->post(route('letters.approve', $letter->id));

        $response->assertStatus(403);
    }

    public function test_sekretaris_can_approve_signer_type_sekretaris()
    {
        $letter = $this->createSubmittedLetter('sekretaris');

        $response = $this->actingAs($this->sekretaris)
            ->post(route('letters.approve', $letter->id));

        $response->assertRedirect(route('letters.approvals'));
        $letter->refresh();
        $this->assertEquals('approved', $letter->status);
    }

    public function test_approve_notifies_recipient_member()
    {
        $letter = $this->createSubmittedLetterToMember('ketua');

        $this->actingAs($this->ketua)->post(route('letters.approve', $letter->id))
            ->assertRedirect(route('letters.approvals'));

        $this->assertTrue(
            DatabaseNotification::where('notifiable_type', User::class)
                ->where('notifiable_id', $this->anggota->id)
                ->where('data->letter_id', $letter->id)
                ->where('data->action', 'sent')
                ->exists()
        );
    }

    public function test_user_without_position_cannot_approve()
    {
        $letter = $this->createSubmittedLetter('ketua');

        $response = $this->actingAs($this->anggota)
            ->post(route('letters.approve', $letter->id));

        $response->assertStatus(403);
    }

    public function test_super_admin_can_approve_any_letter()
    {
        $letter = $this->createSubmittedLetter('ketua');

        $response = $this->actingAs($this->superAdmin)
            ->post(route('letters.approve', $letter->id));

        $response->assertRedirect(route('letters.approvals'));
        $letter->refresh();
        $this->assertEquals('approved', $letter->status);
    }

    public function test_revise_creates_revision_record()
    {
        $letter = $this->createSubmittedLetter('ketua');

        $response = $this->actingAs($this->ketua)
            ->post(route('letters.revise', $letter->id), [
                'note' => 'Please fix the format',
            ]);

        $response->assertRedirect(route('letters.approvals'));
        $letter->refresh();
        $this->assertEquals('revision', $letter->status);
        $this->assertEquals('Please fix the format', $letter->revision_note);
        $this->assertDatabaseHas('letter_revisions', [
            'letter_id' => $letter->id,
            'note' => 'Please fix the format',
        ]);
    }

    public function test_reject_sets_status_and_rejected_by()
    {
        $letter = $this->createSubmittedLetter('ketua');

        $response = $this->actingAs($this->ketua)
            ->post(route('letters.reject', $letter->id), [
                'note' => 'Not acceptable',
            ]);

        $response->assertRedirect(route('letters.approvals'));
        $letter->refresh();
        $this->assertEquals('rejected', $letter->status);
        $this->assertEquals($this->ketua->id, $letter->rejected_by_user_id);
        $this->assertNotNull($letter->rejected_at);
    }

    public function test_approvals_page_shows_only_matching_signer_type()
    {
        // Create letter for ketua
        $letterKetua = $this->createSubmittedLetter('ketua');

        // Create letter for sekretaris
        $letterSekretaris = $this->createSubmittedLetter('sekretaris');

        // Ketua should only see ketua letters
        $response = $this->actingAs($this->ketua)
            ->get(route('letters.approvals'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Approvals')
                ->has('letters.data', 1)
        );
    }

    public function test_user_without_position_cannot_access_approvals_page()
    {
        $this->createSubmittedLetter('ketua');

        $response = $this->actingAs($this->anggota)
            ->get(route('letters.approvals'));

        $response->assertStatus(403);
    }
}
