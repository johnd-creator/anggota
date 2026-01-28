<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengurusRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $pengurus;

    private OrganizationUnit $unit;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $rolePengurus = Role::firstOrCreate(['name' => 'pengurus'], ['label' => 'Pengurus']);
        $roleUnit = Role::firstOrCreate(['name' => 'admin_unit'], ['label' => 'Admin Unit']);

        $this->unit = OrganizationUnit::factory()->create(['code' => 'UNIT-01', 'name' => 'Unit Test 01']);

        $this->member = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'status' => 'aktif',
        ]);

        $this->pengurus = User::factory()->create([
            'role_id' => $rolePengurus->id,
            'member_id' => $this->member->id,
            'organization_unit_id' => $this->unit->id,
        ]);

        $this->actingAs($this->pengurus);
    }

    public function test_pengurus_can_access_dashboard_with_finance_and_dues_summary()
    {
        $response = $this->get('/dashboard');

        $response->assertStatus(200);
        $page = $response->viewData('page');
        $this->assertIsArray($page['props']);
        $this->assertArrayHasKey('finance', $page['props']);
        $this->assertArrayHasKey('dues_summary', $page['props']);
    }

    public function test_pengurus_can_view_members_read_only()
    {
        $response = $this->get('/admin/members');
        $response->assertStatus(200);

        $otherMember = Member::factory()->create(['organization_unit_id' => $this->unit->id]);
        $response = $this->get("/admin/members/{$otherMember->id}");
        $response->assertStatus(200);

        $response = $this->post('/admin/members', [
            'full_name' => 'Test Member',
            'kta_number' => 'TEST-001',
            'nip' => 'NIP-TEST',
            'personal_email' => 'test@gmail.com',
            'join_date' => now()->toDateString(),
            'organization_unit_id' => $this->unit->id,
            'status' => 'aktif',
        ]);
        $response->assertStatus(403);

        $response = $this->put("/admin/members/{$otherMember->id}", [
            'full_name' => 'Updated Name',
        ]);
        $response->assertStatus(403);
    }

    public function test_pengurus_can_view_finance_read_only()
    {
        $response = $this->get('/finance/categories');
        $response->assertStatus(200);

        $response = $this->get('/finance/ledgers');
        $response->assertStatus(200);

        $response = $this->get('/finance/dues');
        $response->assertStatus(200);

        $response = $this->post('/finance/categories', [
            'name' => 'Test Category',
            'code' => 'CAT-TEST',
            'type' => 'income',
        ]);
        $response->assertStatus(403);

        $response = $this->post('/finance/dues/update', [
            'member_id' => $this->member->id,
            'period' => now()->format('Y-m'),
            'status' => 'paid',
            'amount' => 30000,
        ]);
        $response->assertStatus(403);
    }

    public function test_pengurus_cannot_access_kelola_aspirasi()
    {
        $response = $this->get('/admin/aspirations');
        $response->assertStatus(403);
    }

    public function test_pengurus_cannot_access_kelola_pengumuman()
    {
        $response = $this->get('/admin/announcements');
        $response->assertStatus(403);
    }

    public function test_pengurus_maintains_member_access()
    {
        $response = $this->get('/member/profile');
        $response->assertStatus(200);

        $response = $this->get('/member/dues');
        $response->assertStatus(200);

        $response = $this->get('/member/aspirations');
        $response->assertStatus(200);

        $response = $this->get('/letters/inbox');
        $response->assertStatus(200);

        $response = $this->get('/letters/outbox');
        $response->assertStatus(200);
    }

    public function test_pengurus_can_search_members_in_admin()
    {
        $member = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'full_name' => 'Test Member Search',
            'phone' => '08123456789',
        ]);

        $response = $this->get('/admin/members/search-by-phone-or-nip?q=08123456789&limit=10');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertEquals('Test Member Search', $data['data'][0]['full_name']);
    }

    public function test_pengurus_can_search_members_api_for_letter()
    {
        $member = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'full_name' => 'Test API Search',
            'nra' => 'NRA001',
            'status' => 'aktif',
        ]);

        $response = $this->get('/api/search?q=Test&types[]=members&limit=5');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertGreaterThan(0, count($data['results']['members']));
        $this->assertStringContainsString('Test API Search', $data['results']['members'][0]['title']);
        $this->assertEquals('NRA001', $data['results']['members'][0]['snippet']);
    }

    public function test_pengurus_can_search_members_via_topbar_api()
    {
        $member = Member::factory()->create([
            'organization_unit_id' => $this->unit->id,
            'full_name' => 'Test Search Member',
            'phone' => '08123456789',
        ]);

        $response = $this->get('/api/search?q=Test&types[]=members&limit=5');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('members', $data['results']);
        $this->assertCount(1, $data['results']['members']);
        $this->assertEquals('Test Search Member', $data['results']['members'][0]['title']);
    }

    public function test_pengurus_search_members_scoped_to_unit()
    {
        $pengurus = $this->pengurus;
        $pengurusUnit = $this->unit;

        $member1 = Member::factory()->create([
            'organization_unit_id' => $pengurusUnit->id,
            'full_name' => 'Unit Member',
        ]);

        $otherUnit = OrganizationUnit::factory()->create(['code' => 'UNIT-02', 'name' => 'Unit Test 02']);
        $member2 = Member::factory()->create([
            'organization_unit_id' => $otherUnit->id,
            'full_name' => 'Other Unit Member',
        ]);

        $response = $this->actingAs($pengurus)
            ->get('/api/search?q=Member&types[]=members&limit=10');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(1, $data['results']['members']);
        $this->assertEquals('Unit Member', $data['results']['members'][0]['title']);
    }

    public function test_pengurus_can_create_aspiration_via_member()
    {
        $aspirationCategory = \App\Models\AspirationCategory::factory()->create(['name' => 'Category Test']);

        $aspirationData = [
            'category_id' => $aspirationCategory->id,
            'title' => 'Test Aspirasi Pengurus',
            'body' => 'Isi aspirasi pengurus',
        ];

        $response = $this->post('/member/aspirations', $aspirationData);

        $response->assertStatus(302);
    }

    public function test_pengurus_can_view_aspirations_via_member()
    {
        $aspirasi = \App\Models\Aspiration::factory()->create([
            'member_id' => $this->member->id,
            'title' => 'Test Aspirasi Member',
            'status' => 'pending',
        ]);

        $response = $this->get('/member/aspirations');

        $response->assertStatus(200);
        $this->assertStringContainsString('Test Aspirasi Member', $response->inertia('page'));
    }
}
