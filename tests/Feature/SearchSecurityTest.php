<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_anggota_cannot_search_members_explicitly()
    {
        $user = User::factory()->create(['role_id' => null]); // Anggota

        $response = $this->actingAs($user)->get('/search?q=test&type=members');

        $response->assertStatus(403);
    }

    public function test_admin_unit_cannot_see_cross_unit_members()
    {
        // Setup scenarios
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $roleAdminUnit = \App\Models\Role::firstOrCreate(['name' => 'admin_unit', 'label' => 'Admin Unit']);
        $adminA = User::factory()->create(['role_id' => $roleAdminUnit->id, 'organization_unit_id' => $unitA->id]); // Admin Unit

        $memberA = Member::factory()->create(['full_name' => 'Member Alpha', 'organization_unit_id' => $unitA->id]);
        $memberB = Member::factory()->create(['full_name' => 'Member Beta', 'organization_unit_id' => $unitB->id]);

        // Search for "Member" as Admin A
        $service = app(SearchService::class);
        $results = $service->search($adminA, 'Member', ['members'], 10)['results']['members'];

        // Assertions
        $this->assertCount(1, $results);
        $this->assertEquals('Member Alpha', $results[0]['title']);
        // Should not contain Beta
    }

    public function test_pii_is_masked_for_global_admin()
    {
        $roleAdminPusat = \App\Models\Role::firstOrCreate(['name' => 'admin_pusat', 'label' => 'Admin Pusat']);
        $adminPusat = User::factory()->create(['role_id' => $roleAdminPusat->id]); // Admin Pusat
        $member = Member::factory()->create([
            'full_name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'phone' => '08123456789'
        ]);

        $service = app(SearchService::class);
        // Admin Pusat can see members
        $results = $service->search($adminPusat, 'John', ['members'], 5)['results']['members'];
        $item = $results[0];

        // Check Masking
        $this->assertArrayHasKey('email', $item['meta']);
        $this->assertStringContainsString('*', $item['meta']['email']);
        $this->assertStringContainsString('@', $item['meta']['email']);
        $this->assertStringNotContainsString('johndoe@example.com', $item['meta']['email']);
    }

    public function test_privileged_search_is_audited()
    {
        $roleAdminPusat = \App\Models\Role::firstOrCreate(['name' => 'admin_pusat', 'label' => 'Admin Pusat']);
        $adminPusat = User::factory()->create(['role_id' => $roleAdminPusat->id]); // Admin Pusat

        $this->actingAs($adminPusat)
            ->getJson('/api/search?q=secretquery&limit=5');

        $log = AuditLog::where('event', 'search.api')->latest()->first();

        $this->assertNotNull($log);
        $this->assertEquals($adminPusat->id, $log->user_id);
        $this->assertEquals(hash('sha256', 'secretquery'), $log->payload['query_hash']);
    }

    public function test_regular_search_is_not_audited()
    {
        $anggota = User::factory()->create(['role_id' => null]);

        $this->actingAs($anggota)
            ->getJson('/api/search?q=commonquery&limit=5');

        $log = AuditLog::where('event', 'search.api')->where('user_id', $anggota->id)->first();

        $this->assertNull($log);
    }
}
