<?php

namespace Tests\Feature;

use App\Models\Letter;
use App\Models\LetterAttachment;
use App\Models\LetterCategory;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterUnitScopeTest extends TestCase
{
    use RefreshDatabase;

    protected ?LetterCategory $category = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Create a letter category for tests
        $this->category = LetterCategory::create([
            'name' => 'Test Category',
            'code' => 'TEST',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * Helper to create a letter from a unit.
     */
    protected function createLetter(int $fromUnitId, int $creatorId, array $attributes = []): Letter
    {
        return Letter::create(array_merge([
            'creator_user_id' => $creatorId,
            'from_unit_id' => $fromUnitId,
            'letter_category_id' => $this->category->id,
            'to_type' => 'admin_pusat',
            'subject' => 'Test Letter',
            'body' => 'Test body',
            'signer_type' => 'ketua',
            'status' => 'approved',
            'confidentiality' => 'biasa',
            'urgency' => 'biasa',
            'verification_token' => \Illuminate\Support\Str::uuid(),
        ], $attributes));
    }

    // ========================================
    // Policy tests
    // ========================================

    public function test_admin_unit_cannot_view_letter_from_other_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $adminUnitB = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        // Letter from unit B to admin_pusat (not to unit A)
        $letter = $this->createLetter($unitB->id, $adminUnitB->id);

        // admin_unit A cannot view letter from unit B
        $this->assertFalse($adminUnitA->can('view', $letter));
    }

    public function test_admin_unit_can_view_letter_to_their_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $adminUnitB = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        // Letter from unit B TO unit A
        $letter = $this->createLetter($unitB->id, $adminUnitB->id, [
            'to_type' => 'unit',
            'to_unit_id' => $unitA->id,
        ]);

        // admin_unit A CAN view letter addressed to unit A
        $this->assertTrue($adminUnitA->can('view', $letter));
    }

    public function test_super_admin_can_view_any_letter(): void
    {
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $creator = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $letter = $this->createLetter($unitB->id, $creator->id);

        $this->assertTrue($superAdmin->can('view', $letter));
    }

    // ========================================
    // Endpoint tests (HTTP 403)
    // ========================================

    public function test_admin_unit_gets_403_when_viewing_letter_from_other_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $adminUnitB = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        // Letter from unit B (not addressed to A)
        $letter = $this->createLetter($unitB->id, $adminUnitB->id);

        $response = $this->actingAs($adminUnitA)->get("/letters/{$letter->id}");

        $response->assertStatus(403);
    }

    public function test_admin_unit_gets_403_when_downloading_pdf_from_other_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $adminUnitB = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $letter = $this->createLetter($unitB->id, $adminUnitB->id);

        $response = $this->actingAs($adminUnitA)->get("/letters/{$letter->id}/pdf");

        $response->assertStatus(403);
    }

    public function test_admin_unit_gets_403_when_downloading_attachment_from_other_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        $adminUnitB = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $letter = $this->createLetter($unitB->id, $adminUnitB->id);

        // Create a dummy attachment record (no real file needed for 403 test)
        $attachment = LetterAttachment::create([
            'letter_id' => $letter->id,
            'original_name' => 'test.pdf',
            'path' => 'letters/' . $letter->id . '/test.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'uploaded_by_user_id' => $adminUnitB->id,
        ]);

        $response = $this->actingAs($adminUnitA)->get("/letters/{$letter->id}/attachments/{$attachment->id}");

        $response->assertStatus(403);
    }

    public function test_super_admin_can_view_letter_endpoint(): void
    {
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $creator = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $letter = $this->createLetter($unitB->id, $creator->id);

        $response = $this->actingAs($superAdmin)->get("/letters/{$letter->id}");

        $response->assertStatus(200);
    }

    public function test_creator_can_always_view_their_own_letter(): void
    {
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $creator = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitB->id,
        ]);

        $letter = $this->createLetter($unitB->id, $creator->id);

        $response = $this->actingAs($creator)->get("/letters/{$letter->id}");

        $response->assertStatus(200);
    }
}
