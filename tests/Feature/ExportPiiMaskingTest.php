<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\OrganizationUnit;
use App\Models\Role;
use App\Models\User;
use App\Services\ExportScopeHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportPiiMaskingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Test admin_unit export masks PII fields.
     */
    public function test_admin_unit_export_masks_pii(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        // Create test members with PII
        Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'email' => 'john.doe@example.com',
            'phone' => '081234567890',
            'nip' => '12345678',
        ]);

        $response = $this->actingAs($adminUnit)->get('/admin/members-export');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // Should contain masked email (jo***@example.com)
        $this->assertStringContainsString('jo***@example.com', $content);
        // Should NOT contain full email
        $this->assertStringNotContainsString('john.doe@example.com', $content);
        // Should contain masked phone
        $this->assertStringContainsString('08****7890', $content);
        // Should contain masked NIP
        $this->assertStringContainsString('****5678', $content);
    }

    /**
     * Test super_admin sees full PII.
     */
    public function test_global_admin_sees_full_pii(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        // Create test member with PII
        Member::factory()->create([
            'organization_unit_id' => $unit->id,
            'email' => 'jane.doe@example.com',
            'phone' => '089876543210',
            'nip' => '87654321',
        ]);

        $response = $this->actingAs($superAdmin)->get('/admin/members-export');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // Should contain full email
        $this->assertStringContainsString('jane.doe@example.com', $content);
        // Should contain full phone
        $this->assertStringContainsString('089876543210', $content);
        // Should contain full NIP
        $this->assertStringContainsString('87654321', $content);
    }

    /**
     * Test export creates audit log.
     */
    public function test_export_audit_logged(): void
    {
        $unit = OrganizationUnit::factory()->create();
        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unit->id,
        ]);

        Member::factory()->count(3)->create([
            'organization_unit_id' => $unit->id,
        ]);

        $this->actingAs($adminUnit)->get('/admin/members-export');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $adminUnit->id,
            'event' => 'export.members',
            'event_category' => 'export',
        ]);

        // Check payload contains row_count
        $log = AuditLog::where('event', 'export.members')->first();
        $this->assertNotNull($log->payload);
        $this->assertEquals(3, $log->payload['row_count']);
    }

    /**
     * Test admin_unit is scoped to own unit (IDOR prevention).
     */
    public function test_admin_unit_scoped_to_own_unit(): void
    {
        $unitA = OrganizationUnit::factory()->create(['name' => 'Unit A']);
        $unitB = OrganizationUnit::factory()->create(['name' => 'Unit B']);

        $adminUnitA = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
            'organization_unit_id' => $unitA->id,
        ]);

        // Create members in both units
        Member::factory()->create([
            'organization_unit_id' => $unitA->id,
            'full_name' => 'Unit A Member',
        ]);
        Member::factory()->create([
            'organization_unit_id' => $unitB->id,
            'full_name' => 'Unit B Member',
        ]);

        // Admin Unit A tries to export Unit B (should be ignored)
        $response = $this->actingAs($adminUnitA)->get('/admin/members-export?unit_id=' . $unitB->id);

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // Should only see Unit A member
        $this->assertStringContainsString('Unit A Member', $content);
        // Should NOT see Unit B member
        $this->assertStringNotContainsString('Unit B Member', $content);
    }

    /**
     * Test ExportScopeHelper::maskPii works correctly.
     */
    public function test_mask_pii_helper(): void
    {
        // Email masking
        $this->assertEquals('jo***@example.com', ExportScopeHelper::maskPii('john@example.com', 'email'));
        $this->assertEquals('ab***@test.org', ExportScopeHelper::maskPii('abcdef@test.org', 'email'));

        // Phone masking  
        $this->assertEquals('08****7890', ExportScopeHelper::maskPii('081234567890', 'phone'));

        // NIP masking
        $this->assertEquals('****5678', ExportScopeHelper::maskPii('12345678', 'nip'));

        // Null handling
        $this->assertNull(ExportScopeHelper::maskPii(null, 'email'));
        $this->assertEquals('', ExportScopeHelper::maskPii('', 'email'));
    }
}
