<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterCategory;
use App\Models\LetterRead;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LetterReadTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected OrganizationUnit $unit;
    protected User $creator;
    protected User $recipient;
    protected Member $recipientMember;
    protected Letter $letter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\LetterCategorySeeder::class);

        $this->unit = OrganizationUnit::factory()->create();

        // Create letter creator
        $this->creator = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        // Create recipient member
        $this->recipientMember = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'status' => 'aktif',
        ]);

        // Create recipient user
        $this->recipient = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'member_id' => $this->recipientMember->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        // Create a sent letter to member
        $this->letter = Letter::create([
            'letter_category_id' => LetterCategory::first()->id,
            'subject' => 'Test Letter',
            'body' => 'Test body',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'to_type' => 'member',
            'to_member_id' => $this->recipientMember->id,
            'signer_type' => 'ketua',
            'from_unit_id' => $this->unit->id,
            'creator_user_id' => $this->creator->id,
            'status' => 'approved', // Must be approved for PDF access
            'approved_at' => now(),
            'letter_number' => 'TEST/001/2025',
        ]);
    }

    /**
     * Test accessing show as recipient creates letter_reads.
     */
    public function test_show_as_recipient_creates_letter_read(): void
    {
        $this->assertDatabaseMissing('letter_reads', [
            'letter_id' => $this->letter->id,
            'user_id' => $this->recipient->id,
        ]);

        $response = $this->actingAs($this->recipient)
            ->get("/letters/{$this->letter->id}");

        $response->assertStatus(200);

        $this->assertDatabaseHas('letter_reads', [
            'letter_id' => $this->letter->id,
            'user_id' => $this->recipient->id,
        ]);
    }

    /**
     * Test accessing PDF as recipient creates letter_reads.
     */
    public function test_pdf_as_recipient_creates_letter_read(): void
    {
        $this->assertDatabaseMissing('letter_reads', [
            'letter_id' => $this->letter->id,
            'user_id' => $this->recipient->id,
        ]);

        $response = $this->actingAs($this->recipient)
            ->get("/letters/{$this->letter->id}/pdf");

        // PDF should return OK (or PDF content-type)
        $response->assertStatus(200);

        $this->assertDatabaseHas('letter_reads', [
            'letter_id' => $this->letter->id,
            'user_id' => $this->recipient->id,
        ]);
    }

    /**
     * Test show as creator includes reads prop.
     */
    public function test_show_as_creator_includes_reads(): void
    {
        // First, recipient reads the letter
        LetterRead::create([
            'letter_id' => $this->letter->id,
            'user_id' => $this->recipient->id,
            'read_at' => now(),
        ]);

        $response = $this->actingAs($this->creator)
            ->get("/letters/{$this->letter->id}");

        $response->assertStatus(200);

        $response->assertInertia(function (Assert $page) {
            $page->has('reads')
                ->has('canViewReads')
                ->where('canViewReads', true);
        });
    }

    /**
     * Test show as recipient does NOT include reads in props.
     */
    public function test_show_as_recipient_does_not_include_reads(): void
    {
        // Another user reads the letter
        $otherRecipient = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'member_id' => $this->recipientMember->id,
        ]);

        LetterRead::create([
            'letter_id' => $this->letter->id,
            'user_id' => $otherRecipient->id,
            'read_at' => now(),
        ]);

        $response = $this->actingAs($this->recipient)
            ->get("/letters/{$this->letter->id}");

        $response->assertStatus(200);

        $response->assertInertia(function (Assert $page) {
            $page->where('canViewReads', false)
                ->where('reads', []);
        });
    }

    /**
     * Test global admin can see read receipts.
     */
    public function test_super_admin_can_see_read_receipts(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        // Recipient reads the letter
        LetterRead::create([
            'letter_id' => $this->letter->id,
            'user_id' => $this->recipient->id,
            'read_at' => now(),
        ]);

        $response = $this->actingAs($superAdmin)
            ->get("/letters/{$this->letter->id}");

        $response->assertStatus(200);

        $response->assertInertia(function (Assert $page) {
            $page->where('canViewReads', true)
                ->has('reads', 1);
        });
    }

    /**
     * Test creator viewing does not create letter_read (only recipients).
     */
    public function test_creator_viewing_does_not_create_letter_read(): void
    {
        $response = $this->actingAs($this->creator)
            ->get("/letters/{$this->letter->id}");

        $response->assertStatus(200);

        // Creator should not have a read record (they're not a recipient)
        $this->assertDatabaseMissing('letter_reads', [
            'letter_id' => $this->letter->id,
            'user_id' => $this->creator->id,
        ]);
    }
}
