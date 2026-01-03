<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentUnitIdConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Test that admin_unit with organization_unit_id=null but member_id with valid unit
     * still works correctly for letter creation.
     */
    public function test_admin_unit_with_unit_from_member_can_create_letter(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test Unit']);

        // Create member first
        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'aktif',
        ]);

        // Create admin_unit with NO direct organization_unit_id but WITH member_id
        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => null, // No direct unit
            'member_id' => $member->id,     // But has member with unit
        ]);

        // Verify currentUnitId() returns member's unit
        $this->assertEquals($unit->id, $adminUnit->currentUnitId());

        // Seed letter category
        $this->seed(\Database\Seeders\LetterCategorySeeder::class);

        // Now try to create a letter (should use currentUnitId, not crash)
        $response = $this->actingAs($adminUnit)->post('/letters', [
            'letter_category_id' => \App\Models\LetterCategory::first()->id,
            'subject' => 'Test Letter',
            'body' => 'Test body content',
            'to_type' => 'admin_pusat',
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'signer_type' => 'ketua',
        ]);

        // Should redirect (success) not error
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        // Verify the letter was created with correct from_unit_id
        $letter = \App\Models\Letter::latest()->first();
        $this->assertNotNull($letter);
        $this->assertEquals($unit->id, $letter->from_unit_id);
    }

    /**
     * Test that isRecipientUser works for user whose unit comes from member.
     */
    public function test_letter_recipient_check_uses_current_unit_id(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test Unit']);

        $member = Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'status' => 'aktif',
        ]);

        // User with unit from member only
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'organization_unit_id' => null,
            'member_id' => $member->id,
        ]);

        // Seed letter category
        $this->seed(\Database\Seeders\LetterCategorySeeder::class);

        // Create a letter addressed to the unit
        $letter = \App\Models\Letter::create([
            'letter_category_id' => \App\Models\LetterCategory::first()->id,
            'subject' => 'To Unit',
            'body' => 'Content',
            'to_type' => 'unit',
            'to_unit_id' => $unit->id,
            'urgency' => 'biasa',
            'confidentiality' => 'biasa',
            'status' => 'sent',
            'signer_type' => 'ketua',
            'creator_user_id' => User::factory()->create(['role_id' => Role::where('name', 'super_admin')->first()->id])->id,
        ]);

        // User should be able to view this letter (as recipient)
        $response = $this->actingAs($user)->get("/letters/{$letter->id}");

        // Should succeed (200 or redirect to show page)
        $this->assertNotEquals(403, $response->status());
    }

    /**
     * Test that non-global user cannot inject unit_id in aspiration index.
     */
    public function test_admin_unit_cannot_inject_unit_id_in_aspiration_index(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Create aspirations in both units
        $memberA = Member::factory()->create(['organization_unit_id' => $unitA->id]);
        $memberB = Member::factory()->create(['organization_unit_id' => $unitB->id]);

        // Seed aspiration category
        \App\Models\AspirationCategory::create(['name' => 'Test Category']);

        \App\Models\Aspiration::create([
            'title' => 'Aspiration A',
            'body' => 'From unit A',
            'member_id' => $memberA->id,
            'organization_unit_id' => $unitA->id,
            'category_id' => \App\Models\AspirationCategory::first()->id,
            'status' => 'new',
        ]);

        \App\Models\Aspiration::create([
            'title' => 'Aspiration B',
            'body' => 'From unit B',
            'member_id' => $memberB->id,
            'organization_unit_id' => $unitB->id,
            'category_id' => \App\Models\AspirationCategory::first()->id,
            'status' => 'new',
        ]);

        // Admin A tries to inject unit_id=B
        $response = $this->actingAs($adminUnitA)
            ->get('/admin/aspirations?unit_id=' . $unitB->id);

        $response->assertStatus(200);

        // Should only see Unit A's aspiration, not Unit B's
        $aspirations = $response->original->getData()['page']['props']['aspirations']['data'] ?? [];

        $titles = collect($aspirations)->pluck('title')->toArray();
        $this->assertContains('Aspiration A', $titles);
        $this->assertNotContains('Aspiration B', $titles);
    }

    /**
     * Test that non-global user cannot inject unit_id in finance category store.
     */
    public function test_admin_unit_cannot_inject_unit_id_in_finance_category(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        // Create bendahara role if not exists
        $bendaharaRole = Role::where('name', 'bendahara')->first();

        $bendahara = User::factory()->create([
            'role_id' => $bendaharaRole->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Try to create category with unit_id = B (injection attempt)
        $response = $this->actingAs($bendahara)->post('/finance/categories', [
            'name' => 'Injected Category',
            'type' => 'income',
            'organization_unit_id' => $unitB->id, // Injection attempt
        ]);

        // Should redirect (success)
        $response->assertRedirect();

        // Verify category was created with Unit A, not Unit B
        $category = \App\Models\FinanceCategory::where('name', 'Injected Category')->first();
        $this->assertNotNull($category);
        $this->assertEquals($unitA->id, $category->organization_unit_id);
    }
}
