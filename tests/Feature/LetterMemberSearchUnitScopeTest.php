<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LetterMemberSearchUnitScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_admin_unit_search_only_returns_own_unit_members(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Create members with same searchable name pattern in both units
        $memberA = Member::factory()->create([
            'organization_unit_id' => $unitA->id,
            'full_name' => 'John Smith',
            'nra' => 'NRA001',
            'email' => 'john.a@test.com',
            'status' => 'aktif',
        ]);

        $memberB = Member::factory()->create([
            'organization_unit_id' => $unitB->id,
            'full_name' => 'John Doe',
            'nra' => 'NRA002',
            'email' => 'john.b@test.com',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($adminUnitA)
            ->getJson('/api/members/search?q=John');

        $response->assertStatus(200);

        $data = $response->json();

        // Should only have 1 result (from unit A)
        $this->assertCount(1, $data);
        $this->assertEquals($memberA->id, $data[0]['id']);
        $this->assertStringContains('John Smith', $data[0]['label']);

        // Unit B member should NOT be in results
        $ids = collect($data)->pluck('id')->toArray();
        $this->assertNotContains($memberB->id, $ids);
    }

    public function test_admin_unit_search_does_not_expose_email(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        Member::factory()->create([
            'organization_unit_id' => $unitA->id,
            'full_name' => 'Test Member',
            'nra' => 'NRA123',
            'email' => 'secret@test.com',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($adminUnitA)
            ->getJson('/api/members/search?q=Test');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(1, $data);

        // Label should NOT contain email
        $this->assertStringNotContainsString('secret@test.com', $data[0]['label']);
        $this->assertStringNotContainsString('@', $data[0]['label']);
    }

    public function test_super_admin_search_returns_all_units_and_includes_email(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        Member::factory()->create([
            'organization_unit_id' => $unitA->id,
            'full_name' => 'Alice Admin',
            'nra' => 'NRA001',
            'email' => 'alice@test.com',
            'status' => 'aktif',
        ]);

        Member::factory()->create([
            'organization_unit_id' => $unitB->id,
            'full_name' => 'Alice Baker',
            'nra' => 'NRA002',
            'email' => 'alice.b@test.com',
            'status' => 'aktif',
        ]);

        $response = $this->actingAs($superAdmin)
            ->getJson('/api/members/search?q=Alice');

        $response->assertStatus(200);

        $data = $response->json();

        // Should have both results (global access)
        $this->assertCount(2, $data);

        // Labels should contain email for super_admin
        $labels = collect($data)->pluck('label')->implode(' ');
        $this->assertStringContainsString('@', $labels);
    }

    public function test_admin_unit_cannot_search_by_email(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        Member::factory()->create([
            'organization_unit_id' => $unitA->id,
            'full_name' => 'Test Member',
            'nra' => 'NRA123',
            'email' => 'unique.email@test.com',
            'status' => 'aktif',
        ]);

        // Search by email should return empty (email search disabled for non-global)
        $response = $this->actingAs($adminUnitA)
            ->getJson('/api/members/search?q=unique.email');

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertCount(0, $data);
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '$haystack' contains '$needle'"
        );
    }
}
