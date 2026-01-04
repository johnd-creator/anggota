<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Aspiration;
use App\Models\Letter;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchApiTest extends TestCase
{
    use RefreshDatabase;

    protected $unitA;
    protected $unitB;
    protected $adminUnitA;
    protected $memberA;
    protected $memberB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $this->unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $roleAdminUnit = Role::firstOrCreate(['name' => 'admin_unit', 'label' => 'Admin Unit']);
        $roleAnggota = Role::firstOrCreate(['name' => 'anggota', 'label' => 'Anggota']);

        $this->adminUnitA = User::factory()->create(['role_id' => $roleAdminUnit->id, 'organization_unit_id' => $this->unitA->id]);

        $memberModelA = Member::factory()->create(['organization_unit_id' => $this->unitA->id, 'full_name' => 'Member Alpha']);
        $this->memberA = User::factory()->create(['role_id' => $roleAnggota->id, 'member_id' => $memberModelA->id, 'email' => 'alpha@example.com']);

        $memberModelB = Member::factory()->create(['organization_unit_id' => $this->unitB->id, 'full_name' => 'Member Beta']);
        $this->memberB = User::factory()->create(['role_id' => $roleAnggota->id, 'member_id' => $memberModelB->id, 'email' => 'beta@example.com']);
    }

    public function test_api_search_requires_query_length()
    {
        $response = $this->actingAs($this->memberA)->getJson(route('search.api', ['q' => 'a']));
        $response->assertStatus(422); // Validation error min 2
    }

    public function test_anggota_can_search_own_aspirations_but_not_others()
    {
        // Aspiration for Member A
        Aspiration::factory()->create(['member_id' => $this->memberA->member_id, 'title' => 'Fix AC Unit A']);
        // Aspiration for Member B
        Aspiration::factory()->create(['member_id' => $this->memberB->member_id, 'title' => 'Fix AC Unit B']);

        // Search as Member A
        $response = $this->actingAs($this->memberA)->getJson(route('search.api', ['q' => 'Fix AC', 'types' => ['aspirations']]));

        $response->assertOk();
        $results = $response->json('results.aspirations');

        $this->assertCount(1, $results);
        $this->assertEquals('Fix AC Unit A', $results[0]['title']);
    }

    public function test_admin_unit_can_search_members_in_own_unit_only()
    {
        // Search as Admin Unit A
        $response = $this->actingAs($this->adminUnitA)->getJson(route('search.api', ['q' => 'Member', 'types' => ['members']]));

        $response->assertOk();
        $results = $response->json('results.members');

        // Should find Member Alpha (Unit A), but NOT Member Beta (Unit B)
        $names = collect($results)->pluck('title')->toArray();
        $this->assertContains('Member Alpha', $names);
        $this->assertNotContains('Member Beta', $names);
    }

    public function test_admin_unit_can_search_aspirations_in_own_unit()
    {
        // Aspiration in Unit A
        Aspiration::factory()->create(['organization_unit_id' => $this->unitA->id, 'title' => 'Project A']);
        // Aspiration in Unit B
        Aspiration::factory()->create(['organization_unit_id' => $this->unitB->id, 'title' => 'Project B']);

        $response = $this->actingAs($this->adminUnitA)->getJson(route('search.api', ['q' => 'Project', 'types' => ['aspirations']]));

        $response->assertOk();
        $results = $response->json('results.aspirations');

        $titles = collect($results)->pluck('title')->toArray();
        $this->assertContains('Project A', $titles);
        $this->assertNotContains('Project B', $titles);
    }

    public function test_announcement_visibility()
    {
        $creatorId = $this->adminUnitA->id;
        // Global announcement
        Announcement::create(['title' => 'Global News', 'body' => 'Content', 'scope_type' => 'global_all', 'is_active' => true, 'created_by' => $creatorId]);
        // Unit A announcement
        Announcement::create(['title' => 'Unit A News', 'body' => 'Content', 'scope_type' => 'unit', 'organization_unit_id' => $this->unitA->id, 'is_active' => true, 'created_by' => $creatorId]);
        // Unit B announcement
        Announcement::create(['title' => 'Unit B News', 'body' => 'Content', 'scope_type' => 'unit', 'organization_unit_id' => $this->unitB->id, 'is_active' => true, 'created_by' => $creatorId]);

        // Search as Member A (Unit A)
        $response = $this->actingAs($this->memberA)->getJson(route('search.api', ['q' => 'News']));

        $response->assertOk();
        $results = $response->json('results.announcements');
        $titles = collect($results)->pluck('title')->toArray();

        $this->assertContains('Global News', $titles);
        $this->assertContains('Unit A News', $titles);
        $this->assertNotContains('Unit B News', $titles);
    }
}
