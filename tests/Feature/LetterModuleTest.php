<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterModuleTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $adminUnit;
    protected $anggota;
    protected $unit;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        $roleSuperAdmin = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $roleAdminUnit = Role::create(['name' => 'admin_unit', 'label' => 'Admin Unit']);
        $roleAnggota = Role::create(['name' => 'anggota', 'label' => 'Anggota']);

        $this->unit = OrganizationUnit::factory()->create();

        $this->superAdmin = User::factory()->create([
            'role_id' => $roleSuperAdmin->id,
        ]);

        $this->adminUnit = User::factory()->create([
            'role_id' => $roleAdminUnit->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        // Create member and anggota user
        $member = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
        ]);

        $this->anggota = User::factory()->create([
            'role_id' => $roleAnggota->id,
            'member_id' => $member->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        $this->category = LetterCategory::create([
            'name' => 'Undangan',
            'code' => 'UND',
            'is_active' => true,
        ]);
    }

    public function test_anggota_cannot_access_letters_create()
    {
        $response = $this->actingAs($this->anggota)
            ->get(route('letters.create'));

        $response->assertStatus(403);
    }

    public function test_admin_unit_can_access_letters_create()
    {
        $response = $this->actingAs($this->adminUnit)
            ->get(route('letters.create'));

        $response->assertStatus(200);
    }

    public function test_admin_unit_can_create_draft()
    {
        $response = $this->actingAs($this->adminUnit)
            ->post(route('letters.store'), [
                'letter_category_id' => $this->category->id,
                'signer_type' => 'ketua',
                'to_type' => 'unit',
                'to_unit_id' => $this->unit->id,
                'subject' => 'Test Letter Subject',
                'body' => 'Test letter body content.',
                'confidentiality' => 'biasa',
                'urgency' => 'biasa',
            ]);

        $response->assertRedirect(route('letters.outbox'));

        $this->assertDatabaseHas('letters', [
            'subject' => 'Test Letter Subject',
            'status' => 'draft',
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
        ]);
    }

    public function test_letter_to_member_shows_in_member_inbox()
    {
        // Create letter to the member
        $letter = Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'member',
            'to_member_id' => $this->anggota->member_id,
            'subject' => 'Letter to Member',
            'body' => 'Test body',
            'status' => 'submitted', // Must be submitted to appear in inbox
        ]);

        $response = $this->actingAs($this->anggota)
            ->get(route('letters.inbox'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Inbox')
                ->has('letters.data', 1)
                ->where('letters.data.0.subject', 'Letter to Member')
        );
    }

    public function test_submit_changes_status_to_submitted()
    {
        $letter = Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'unit',
            'to_unit_id' => $this->unit->id,
            'subject' => 'Draft Letter',
            'body' => 'Test body',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->adminUnit)
            ->post(route('letters.submit', $letter->id));

        $response->assertRedirect(route('letters.outbox'));

        $letter->refresh();
        $this->assertEquals('submitted', $letter->status);
    }

    public function test_cannot_edit_submitted_letter()
    {
        $letter = Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'unit',
            'to_unit_id' => $this->unit->id,
            'subject' => 'Submitted Letter',
            'body' => 'Test body',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->adminUnit)
            ->get(route('letters.edit', $letter->id));

        $response->assertStatus(403);
    }

    public function test_cannot_delete_submitted_letter()
    {
        $letter = Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'unit',
            'to_unit_id' => $this->unit->id,
            'subject' => 'Submitted Letter',
            'body' => 'Test body',
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->adminUnit)
            ->delete(route('letters.destroy', $letter->id));

        $response->assertStatus(403);

        $this->assertDatabaseHas('letters', ['id' => $letter->id]);
    }

    public function test_letter_shows_in_outbox()
    {
        $letter = Letter::create([
            'creator_user_id' => $this->adminUnit->id,
            'from_unit_id' => $this->unit->id,
            'letter_category_id' => $this->category->id,
            'signer_type' => 'ketua',
            'to_type' => 'unit',
            'to_unit_id' => $this->unit->id,
            'subject' => 'My Outbox Letter',
            'body' => 'Test body',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->adminUnit)
            ->get(route('letters.outbox'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Letters/Outbox')
                ->has('letters.data', 1)
                ->where('letters.data.0.subject', 'My Outbox Letter')
        );
    }

    public function test_anggota_can_access_inbox()
    {
        $response = $this->actingAs($this->anggota)
            ->get(route('letters.inbox'));

        $response->assertStatus(200);
    }

    public function test_anggota_cannot_access_outbox()
    {
        $response = $this->actingAs($this->anggota)
            ->get(route('letters.outbox'));

        $response->assertStatus(403);
    }
}
