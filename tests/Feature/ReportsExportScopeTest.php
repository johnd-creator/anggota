<?php

namespace Tests\Feature;

use App\Models\Aspiration;
use App\Models\AspirationCategory;
use App\Models\AuditLog;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsExportScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    // ========== MEMBERS EXPORT TESTS ==========

    public function test_admin_unit_cannot_inject_unit_id_exports_own_unit_only(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Create members in both units
        Member::factory()->count(3)->create(['organization_unit_id' => $unitA->id]);
        Member::factory()->count(5)->create(['organization_unit_id' => $unitB->id]);

        // Try to inject unit B - should be ignored
        $response = $this->actingAs($adminUnitA)->get('/reports/export?type=members&unit_id=' . $unitB->id);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');

        // Parse CSV content - should only contain Unit A members (3)
        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($content)), fn($line) => !empty(trim($line)));

        // Header + 3 data rows = 4 lines
        $this->assertCount(4, $lines, 'Expected header + 3 member rows for Unit A only');
    }

    public function test_admin_pusat_can_filter_by_unit_id(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminPusat = User::factory()->create([
            'role_id' => Role::where('name', 'admin_pusat')->first()->id,
        ]);

        Member::factory()->count(2)->create(['organization_unit_id' => $unitA->id]);
        Member::factory()->count(4)->create(['organization_unit_id' => $unitB->id]);

        // Filter by Unit B - should work for global user
        $response = $this->actingAs($adminPusat)->get('/reports/export?type=members&unit_id=' . $unitB->id);

        $response->assertStatus(200);

        // Parse CSV content - should only contain Unit B members (4)
        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($content)));

        // Header + 4 data rows
        $this->assertCount(5, $lines);
    }

    public function test_super_admin_without_unit_id_exports_all(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        Member::factory()->count(2)->create(['organization_unit_id' => $unitA->id]);
        Member::factory()->count(3)->create(['organization_unit_id' => $unitB->id]);

        // No unit_id filter - should export all
        $response = $this->actingAs($superAdmin)->get('/reports/export?type=members');

        $response->assertStatus(200);

        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($content)));

        // Header + 5 data rows (2 + 3)
        $this->assertCount(6, $lines);
    }

    public function test_audit_log_created_with_correct_payload(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test Unit']);

        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        Member::factory()->count(2)->create(['organization_unit_id' => $unit->id]);

        $this->actingAs($adminUnit)->get('/reports/export?type=members');

        // Check audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $adminUnit->id,
            'event' => 'export.reports.members',
            'event_category' => 'export',
        ]);

        $auditLog = AuditLog::where('user_id', $adminUnit->id)
            ->where('event', 'export.reports.members')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('reports.members', $auditLog->payload['report_type']);
        $this->assertEquals($unit->id, $auditLog->payload['unit_id']);
        $this->assertEquals(2, $auditLog->payload['row_count']);
        $this->assertEquals('csv', $auditLog->payload['format']);
    }

    public function test_feature_flag_disabled_returns_503(): void
    {
        config(['features.reports' => false]);

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $response = $this->actingAs($superAdmin)->get('/reports/export?type=members');

        $response->assertStatus(503);
    }

    public function test_anggota_role_denied_access(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test Unit']);

        $anggota = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        $response = $this->actingAs($anggota)->get('/reports/export?type=members');

        // Role middleware should deny access
        $response->assertStatus(403);
    }

    public function test_bendahara_can_export_own_unit(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test Unit']);

        $bendahara = User::factory()->create([
            'role_id' => Role::where('name', 'bendahara')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        Member::factory()->count(3)->create(['organization_unit_id' => $unit->id]);

        $response = $this->actingAs($bendahara)->get('/reports/export?type=members');

        $response->assertStatus(200);

        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($content)));

        // Header + 3 data rows
        $this->assertCount(4, $lines);
    }

    public function test_unimplemented_type_returns_501_with_audit(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        // Use 'dues' as unimplemented type (aspirations is now implemented)
        $response = $this->actingAs($superAdmin)->get('/reports/export?type=dues');

        $response->assertStatus(501);
        $response->assertJson([
            'error' => 'Not implemented',
        ]);

        // Should still audit the attempt
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $superAdmin->id,
            'event' => 'export.reports.dues',
        ]);
    }

    public function test_invalid_type_returns_422(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        // Use JSON request to get 422 instead of redirect
        $response = $this->actingAs($superAdmin)
            ->withHeader('Accept', 'application/json')
            ->get('/reports/export?type=invalid_type');

        $response->assertStatus(422);
    }

    // ========== ASPIRATIONS EXPORT TESTS ==========

    public function test_aspirations_admin_unit_cannot_inject_unit_id(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);
        $category = AspirationCategory::factory()->create();

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Create aspirations in both units
        Aspiration::factory()->count(2)->create([
            'organization_unit_id' => $unitA->id,
            'category_id' => $category->id,
        ]);
        Aspiration::factory()->count(4)->create([
            'organization_unit_id' => $unitB->id,
            'category_id' => $category->id,
        ]);

        // Try to inject unit B - should be ignored
        $response = $this->actingAs($adminUnitA)->get('/reports/export?type=aspirations&unit_id=' . $unitB->id);

        $response->assertStatus(200);

        // Parse CSV content - should only contain Unit A aspirations (2)
        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($content)), fn($line) => !empty(trim($line)));

        // Header + 2 data rows = 3 lines
        $this->assertCount(3, $lines, 'Expected header + 2 aspiration rows for Unit A only');
    }

    public function test_aspirations_admin_pusat_can_filter_by_unit_id(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);
        $category = AspirationCategory::factory()->create();

        $adminPusat = User::factory()->create([
            'role_id' => Role::where('name', 'admin_pusat')->first()->id,
        ]);

        Aspiration::factory()->count(1)->create([
            'organization_unit_id' => $unitA->id,
            'category_id' => $category->id,
        ]);
        Aspiration::factory()->count(3)->create([
            'organization_unit_id' => $unitB->id,
            'category_id' => $category->id,
        ]);

        // Filter by Unit B
        $response = $this->actingAs($adminPusat)->get('/reports/export?type=aspirations&unit_id=' . $unitB->id);

        $response->assertStatus(200);

        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($content)), fn($line) => !empty(trim($line)));

        // Header + 3 data rows
        $this->assertCount(4, $lines);
    }

    public function test_aspirations_super_admin_exports_all(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);
        $category = AspirationCategory::factory()->create();

        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        Aspiration::factory()->count(2)->create([
            'organization_unit_id' => $unitA->id,
            'category_id' => $category->id,
        ]);
        Aspiration::factory()->count(3)->create([
            'organization_unit_id' => $unitB->id,
            'category_id' => $category->id,
        ]);

        // No unit_id filter - should export all
        $response = $this->actingAs($superAdmin)->get('/reports/export?type=aspirations');

        $response->assertStatus(200);

        $content = $response->streamedContent();
        $lines = array_filter(explode("\n", trim($content)), fn($line) => !empty(trim($line)));

        // Header + 5 data rows (2 + 3)
        $this->assertCount(6, $lines);
    }

    public function test_aspirations_audit_log_created(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test Unit']);
        $category = AspirationCategory::factory()->create();

        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        Aspiration::factory()->count(2)->create([
            'organization_unit_id' => $unit->id,
            'category_id' => $category->id,
        ]);

        $this->actingAs($adminUnit)->get('/reports/export?type=aspirations');

        // Check audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $adminUnit->id,
            'event' => 'export.reports.aspirations',
            'event_category' => 'export',
        ]);

        $auditLog = AuditLog::where('user_id', $adminUnit->id)
            ->where('event', 'export.reports.aspirations')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('reports.aspirations', $auditLog->payload['report_type']);
        $this->assertEquals($unit->id, $auditLog->payload['unit_id']);
        $this->assertEquals(2, $auditLog->payload['row_count']);
    }

    public function test_members_search_query_hashed_in_audit(): void
    {
        $unit = OrganizationUnit::factory()->create(['name' => 'Test Unit']);

        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'full_name' => 'John Doe',
        ]);

        $this->actingAs($adminUnit)->get('/reports/export?type=members&q=John');

        $auditLog = AuditLog::where('user_id', $adminUnit->id)
            ->where('event', 'export.reports.members')
            ->first();

        $this->assertNotNull($auditLog);
        // q should not be in filters raw
        $this->assertArrayNotHasKey('q', $auditLog->payload['filters']);
        // But q_len and q_hash should be present
        $this->assertEquals(4, $auditLog->payload['filters']['q_len']);
        $this->assertNotEmpty($auditLog->payload['filters']['q_hash']);
    }
}

