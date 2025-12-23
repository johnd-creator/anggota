<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivilegedAccessAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Test successful privileged access creates audit log with correct data.
     */
    public function test_privileged_access_creates_audit_log_on_success(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        AuditLog::truncate();

        // Access a privileged route
        $response = $this->actingAs($superAdmin)->get('/audit-logs');

        $response->assertStatus(200);

        // Verify audit log was created
        $auditLog = AuditLog::where('event', 'audit_log.accessed')->first();

        $this->assertNotNull($auditLog, 'Audit log should exist for privileged route access');
        $this->assertEquals($superAdmin->id, $auditLog->user_id);
        $this->assertNotNull($auditLog->request_id);
        $this->assertNotNull($auditLog->status_code);
        $this->assertEquals(200, $auditLog->status_code);
        $this->assertNotNull($auditLog->duration_ms);
        $this->assertEquals('system', $auditLog->event_category);
        $this->assertEquals('audit-logs', $auditLog->payload['route_name'] ?? null);
        $this->assertEquals('audit_logs_accessed', $auditLog->payload['action'] ?? null);
    }

    /**
     * Test forbidden privileged access creates audit log with 403 status.
     */
    public function test_privileged_access_creates_audit_log_on_403(): void
    {
        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
        ]);

        AuditLog::truncate();

        // Access a privileged route without permission
        $response = $this->actingAs($adminUnit)->get('/audit-logs');

        $response->assertStatus(403);

        // Verify audit log was created even for 403
        $auditLog = AuditLog::where('event', 'audit_log.accessed')->first();

        $this->assertNotNull($auditLog, 'Audit log should exist even for 403 forbidden');
        $this->assertEquals($adminUnit->id, $auditLog->user_id);
        $this->assertNotNull($auditLog->request_id);
        $this->assertEquals(403, $auditLog->status_code);
        $this->assertNotNull($auditLog->duration_ms);
        $this->assertEquals('system', $auditLog->event_category);
    }

    /**
     * Test reports export creates audit log.
     */
    public function test_reports_export_creates_audit_log(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        AuditLog::truncate();

        // Trigger the privileged export endpoint (streamed CSV response).
        $response = $this->actingAs($superAdmin)->post('/reports/growth/export');

        $response->assertStatus(200);

        $auditLog = AuditLog::where('event', 'export.report')->first();
        $this->assertNotNull($auditLog, 'Audit log should exist for reports export');
        $this->assertEquals('export', $auditLog->event_category);
        $this->assertEquals(200, $auditLog->status_code);
    }

    /**
     * Test non-privileged route does not create audit log.
     */
    public function test_non_privileged_route_does_not_create_audit_log(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        AuditLog::truncate();

        // Access dashboard (not a privileged route)
        $response = $this->actingAs($superAdmin)->get('/dashboard');

        $response->assertStatus(200);

        // No privileged access log should exist (only login-related logs if any)
        $privilegedLog = AuditLog::whereIn('event', [
            'export.report',
            'export.members',
            'document.member_card',
            'audit_log.accessed',
        ])->first();

        $this->assertNull($privilegedLog, 'No privileged access log should be created for dashboard');
    }

    /**
     * Test member sessions view creates audit log.
     */
    public function test_sessions_view_creates_audit_log(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        AuditLog::truncate();

        // Access admin sessions page
        $response = $this->actingAs($superAdmin)->get('/admin/sessions');

        $response->assertStatus(200);

        // Verify audit log was created
        $auditLog = AuditLog::where('event', 'audit_log.sessions_viewed')->first();

        $this->assertNotNull($auditLog, 'Audit log should exist for sessions view');
        $this->assertEquals('system', $auditLog->event_category);
        $this->assertEquals(200, $auditLog->status_code);
    }

    /**
     * Test activity logs view creates audit log.
     */
    public function test_activity_logs_view_creates_audit_log(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        AuditLog::truncate();

        // Access admin activity logs page
        $response = $this->actingAs($superAdmin)->get('/admin/activity-logs');

        $response->assertStatus(200);

        // Verify audit log was created
        $auditLog = AuditLog::where('event', 'audit_log.activity_viewed')->first();

        $this->assertNotNull($auditLog, 'Audit log should exist for activity logs view');
        $this->assertEquals('system', $auditLog->event_category);
        $this->assertEquals(200, $auditLog->status_code);
    }

    /**
     * Test privileged route protected by role middleware still logs 403 attempts.
     */
    public function test_role_protected_privileged_route_logs_403_attempt(): void
    {
        $anggota = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
        ]);

        AuditLog::truncate();

        $response = $this->actingAs($anggota)->get('/admin/sessions');
        $response->assertStatus(403);

        $auditLog = AuditLog::where('event', 'audit_log.sessions_viewed')->first();
        $this->assertNotNull($auditLog, 'Audit log should exist for denied admin sessions access');
        $this->assertEquals(403, $auditLog->status_code);
        $this->assertEquals('system', $auditLog->event_category);
    }
}
