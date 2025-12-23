<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Test that failed login creates an audit log with category auth_failed.
     */
    public function test_login_failed_creates_audit_log_with_auth_failed_category(): void
    {
        // Clear any existing audit logs
        AuditLog::truncate();

        $response = $this->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(302); // Redirect back with errors

        // Check audit log was created
        $auditLog = AuditLog::where('event', 'login_failed')->first();

        $this->assertNotNull($auditLog, 'Audit log for login_failed should exist');
        $this->assertEquals('auth_failed', $auditLog->event_category);
        $this->assertNotNull($auditLog->request_id, 'request_id should be filled');
        $this->assertNotNull($auditLog->ip_address);
        $this->assertEquals('password', $auditLog->payload['provider'] ?? null);
    }

    /**
     * Test that successful login creates an audit log with category auth.
     */
    public function test_login_success_creates_audit_log_with_auth_category(): void
    {
        $user = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
            'password' => bcrypt('secret123'),
        ]);

        AuditLog::truncate();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));

        $auditLog = AuditLog::where('event', 'login_success')->first();

        $this->assertNotNull($auditLog, 'Audit log for login_success should exist');
        $this->assertEquals('auth', $auditLog->event_category);
        $this->assertEquals($user->id, $auditLog->user_id);
        $this->assertNotNull($auditLog->request_id);
    }

    /**
     * Test that super_admin can access audit logs page.
     */
    public function test_super_admin_can_access_audit_logs(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        $response = $this->actingAs($superAdmin)->get('/audit-logs');

        $response->assertStatus(200);
        $response->assertInertia(fn($page) => $page->component('Admin/AuditLogs'));
    }

    /**
     * Test that non-super_admin users get 403 on audit logs page.
     */
    public function test_non_super_admin_gets_403_on_audit_logs(): void
    {
        $adminUnit = User::factory()->create([
            'role_id' => Role::where('name', 'admin_unit')->first()->id,
        ]);

        $response = $this->actingAs($adminUnit)->get('/audit-logs');

        $response->assertStatus(403);
    }

    /**
     * Test that anggota role gets 403 on audit logs page.
     */
    public function test_anggota_gets_403_on_audit_logs(): void
    {
        $anggota = User::factory()->create([
            'role_id' => Role::where('name', 'anggota')->first()->id,
        ]);

        $response = $this->actingAs($anggota)->get('/audit-logs');

        $response->assertStatus(403);
    }

    /**
     * Test that audit log created mid-request gets status_code and duration_ms filled.
     */
    public function test_audit_log_has_status_code_and_duration_after_response(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        AuditLog::truncate();

        // Access audit-logs page which creates an 'audit_log.accessed' event via middleware
        $response = $this->actingAs($superAdmin)->get('/audit-logs');
        $response->assertStatus(200);

        // Find the audit_log.accessed event (created by PrivilegedAccessAuditMiddleware)
        $auditLog = AuditLog::where('event', 'audit_log.accessed')->first();

        $this->assertNotNull($auditLog, 'Audit log for audit_log.accessed should exist');
        $this->assertNotNull($auditLog->status_code, 'status_code should be filled');
        $this->assertNotNull($auditLog->duration_ms, 'duration_ms should be filled');
        $this->assertEquals(200, $auditLog->status_code);
        $this->assertGreaterThanOrEqual(0, $auditLog->duration_ms);
        $this->assertEquals('system', $auditLog->event_category);
    }

    /**
     * Test that login_failed audit log gets status_code and duration_ms filled.
     */
    public function test_login_failed_audit_log_has_status_code_and_duration(): void
    {
        AuditLog::truncate();

        $this->post('/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $auditLog = AuditLog::where('event', 'login_failed')->first();

        $this->assertNotNull($auditLog);
        $this->assertNotNull($auditLog->status_code, 'status_code should be filled');
        $this->assertNotNull($auditLog->duration_ms, 'duration_ms should be filled');
        // Login failed redirects back, so status code is 302
        $this->assertEquals(302, $auditLog->status_code);
    }

    /**
     * Test that reguler user gets 403 on audit logs page.
     */
    public function test_reguler_user_gets_403_on_audit_logs(): void
    {
        $reguler = User::factory()->create([
            'role_id' => Role::where('name', 'reguler')->first()->id,
        ]);

        $response = $this->actingAs($reguler)->get('/audit-logs');

        $response->assertStatus(403);
    }
}
